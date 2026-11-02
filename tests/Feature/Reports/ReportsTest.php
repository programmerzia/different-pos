<?php

declare(strict_types=1);

use App\Actions\Sales\CreateSaleReturn;
use App\Actions\Sales\Data\ReturnDraft;
use App\Actions\Sales\Data\ReturnLineDraft;
use App\Enums\RefundMode;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Tenant;
use App\Reports\CustomersByLastPurchase;
use App\Reports\DailyCashSummary;
use App\Reports\DuesAging;
use App\Reports\ReportParameters;
use App\Reports\Returns;
use App\Reports\SalesByDay;
use App\Reports\SalesByProduct;
use App\Reports\SalesByTender;
use App\Reports\SalesByUser;
use App\Reports\StockValuation;
use App\Reports\TopCustomersByPurchase;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\SalesWorld;

/** 2026-11-03 14:00 in Dhaka (UTC+6) */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-11-03 08:00:00', 'UTC'));
    $this->world = SalesWorld::make();
    $this->category = Category::factory()->create(['tenant_id' => $this->world->tenant->id, 'name' => 'Shirts']);
    $this->shirt = $this->world->product(100_000, 60_000, ['name' => 'Shirt', 'category_id' => $this->category->id]);
    $this->pant = $this->world->product(250_000, 150_000, ['name' => 'Pant', 'reorder_level' => Quantity::ofUnits(5)]);
    $this->world->stock($this->shirt, 100, 60_000);
    $this->world->stock($this->pant, 100, 150_000);

    $this->alice = $this->world->customer(['name' => 'Alice', 'shop_name' => 'Alice Store']);
    $this->bob = $this->world->customer(['name' => 'Bob']);
    $this->carol = $this->world->customer(['name' => 'Carol']);
    $this->dave = $this->world->customer(['name' => 'Dave']);
    $this->erin = $this->world->customer(['name' => 'Erin']); // never bought

    // The sale window forbids real back-dating, so sell now and move the sale (and its payments) in time.
    $this->sellAt = function ($customer, $product, int $units, string $when, int $pay = 0, string $role = 'Owner'): Sale {
        $sale = $this->world->sell([$this->world->line($product, $units)], $pay > 0 ? [$this->world->tender('cash', $pay)] : [], $customer, $role);
        DB::table('sales')->where('id', $sale->id)->update(['sold_at' => Carbon::parse($when, 'UTC')]);
        DB::table('payments')->whereIn('id', DB::table('payment_allocations')->where('allocatable_type', 'sale')->where('allocatable_id', $sale->id)->select('payment_id'))
            ->update(['paid_at' => Carbon::parse($when, 'UTC')]);

        return $sale->refresh();
    };
    $sell = $this->sellAt;
    // Alice: today 14:00 Dhaka (2 shirts, paid) and Oct (1 pant, unpaid)
    $this->aliceToday = $sell($this->alice, $this->shirt, 2, '2026-11-03 07:00:00', 200_000);
    $this->aliceOct = $sell($this->alice, $this->pant, 1, '2026-10-10 06:00:00');
    // Bob: Sept 2, unpaid, 2 pants
    $this->bobSept = $sell($this->bob, $this->pant, 2, '2026-09-02 06:00:00', 0, 'Manager');
    // Carol: early July, 1 shirt paid
    $this->carolJuly = $sell($this->carol, $this->shirt, 1, '2026-07-05 06:00:00', 100_000);
    // Dave: Aug 20
    $this->daveAug = $sell($this->dave, $this->shirt, 3, '2026-08-20 06:00:00', 300_000);

    $this->params = fn (string $from = '2026-11-01', string $to = '2026-11-03', ?array $branches = null, bool $profit = true, array $filters = []): ReportParameters => new ReportParameters($from, $to, 'Asia/Dhaka', $branches ?? [$this->world->branch->id], $profit, $filters);
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

function byKey(array $rows, string $key, string $value): array
{
    return collect($rows)->firstWhere($key, $value) ?? [];
}

it('ranks top customers by net sales in the period with totals', function (): void {
    $result = app(TopCustomersByPurchase::class)->run(($this->params)('2026-09-01', '2026-11-03'));

    expect(array_column($result->rows, 'customer'))->toBe(['Bob', 'Alice'])
        ->and(array_column($result->rows, 'rank'))->toBe([1, 2]);
    $alice = byKey($result->rows, 'customer', 'Alice');
    expect($alice['invoices'])->toBe(2)
        ->and($alice['qty'])->toBe(3_000)
        ->and($alice['net'])->toBe(200_000 + 250_000)
        ->and($alice['dues'])->toBe(250_000)
        ->and($result->totals['net'])->toBe(500_000 + 450_000)
        ->and($result->totals)->not->toHaveKey('rank');
});

it('ignores walk-in sales and honours the limit filter', function (): void {
    $this->world->sell([$this->world->line($this->shirt, 1)], [$this->world->tender('cash', 100_000)], null, soldAt: Carbon::parse('2026-11-03 06:00:00', 'UTC'));

    $all = app(TopCustomersByPurchase::class)->run(($this->params)('2026-09-01', '2026-11-03'));
    expect(array_column($all->rows, 'customer'))->not->toContain('Walk-in');

    $one = app(TopCustomersByPurchase::class)->run(($this->params)('2026-09-01', '2026-11-03', filters: ['limit' => '1']));
    expect($one->rows)->toHaveCount(1);
});

it('buckets customers by months since the last purchase, longest-lapsed first, never last', function (): void {
    $report = app(CustomersByLastPurchase::class);
    $rows = $report->run(($this->params)())->rows;

    expect(array_column($rows, 'customer'))->toBe(['Carol', 'Dave', 'Bob', 'Alice', 'Erin'])
        ->and(array_column($rows, 'bucket'))->toBe(['m4_plus', 'm3', 'm2', 'this_month', 'never']);
    $dave = byKey($rows, 'customer', 'Dave');
    expect($dave['last_purchase'])->toBe('2026-08-20')
        ->and($dave['days_since'])->toBe(75);
    $alice = byKey($rows, 'customer', 'Alice');
    expect($alice['lifetime'])->toBe(450_000)->and($alice['due'])->toBe(250_000);

    // Oct 10 is "1 month ago"
    ($this->sellAt)($this->bob, $this->shirt, 1, '2026-10-31 20:00:00'); // Nov 1 02:00 Dhaka: this month
    $filtered = $report->run(($this->params)(filters: ['bucket' => 'this_month']))->rows;
    expect(array_column($filtered, 'customer'))->toEqualCanonicalizing(['Alice', 'Bob']);
});

it('puts a sale just after midnight in tenant time on the next day', function (): void {
    // 2026-11-02 18:30 UTC = 2026-11-03 00:30 Dhaka; 2026-11-02 17:59 UTC = 2026-11-02 23:59 Dhaka
    ($this->sellAt)($this->bob, $this->shirt, 1, '2026-11-02 18:30:00');
    ($this->sellAt)($this->bob, $this->shirt, 1, '2026-11-02 17:59:00');

    $rows = app(SalesByDay::class)->run(($this->params)('2026-11-02', '2026-11-03'))->rows;

    expect(byKey($rows, 'date', '2026-11-02')['invoices'])->toBe(1)
        ->and(byKey($rows, 'date', '2026-11-03')['invoices'])->toBe(2);

    $totalOnlyThe3rd = app(TopCustomersByPurchase::class)->run(($this->params)('2026-11-03', '2026-11-03'));
    expect(byKey($totalOnlyThe3rd->rows, 'customer', 'Bob')['invoices'])->toBe(1);
});

it('reports sales by day with every day of the range and a totals row', function (): void {
    $result = app(SalesByDay::class)->run(($this->params)('2026-10-09', '2026-10-11'));

    expect(array_column($result->rows, 'date'))->toBe(['2026-10-09', '2026-10-10', '2026-10-11'])
        ->and(byKey($result->rows, 'date', '2026-10-10')['total'])->toBe(250_000)
        ->and(byKey($result->rows, 'date', '2026-10-10')['profit'])->toBe(100_000)
        ->and(byKey($result->rows, 'date', '2026-10-09')['invoices'])->toBe(0)
        ->and($result->totals['invoices'])->toBe(1);
});

it('reports sales by product with category and cost, and filters by category', function (): void {
    $result = app(SalesByProduct::class)->run(($this->params)('2026-07-01', '2026-11-03'));
    $shirt = byKey($result->rows, 'product', 'Shirt');

    expect($shirt['category'])->toBe('Shirts')
        ->and($shirt['sold'])->toBe(6_000)
        ->and($shirt['sales'])->toBe(600_000)
        ->and($shirt['cogs'])->toBe(360_000)
        ->and($shirt['profit'])->toBe(240_000)
        ->and($result->rows[0]['product'])->toBe('Pant'); // 750_000 beats 600_000

    $filtered = app(SalesByProduct::class)->run(($this->params)('2026-07-01', '2026-11-03', filters: ['category' => (string) $this->category->id]));
    expect(array_column($filtered->rows, 'product'))->toBe(['Shirt']);
});

it('strips profit columns without reports.profit', function (): void {
    $result = app(SalesByProduct::class)->run(($this->params)('2026-07-01', '2026-11-03', profit: false))->withoutProfit();

    expect(array_column($result->columns, 'key'))->not->toContain('cogs', 'profit')
        ->and($result->rows[0])->not->toHaveKey('cogs')
        ->and($result->totals)->not->toHaveKey('profit');
});

it('reports sales by user and by tender', function (): void {
    $users = app(SalesByUser::class)->run(($this->params)('2026-07-01', '2026-11-03'));
    expect(byKey($users->rows, 'user', $this->world->user('Manager')->name)['invoices'])->toBe(1)
        ->and($users->totals['invoices'])->toBe(5);

    $tender = app(SalesByTender::class)->run(($this->params)('2026-07-01', '2026-11-03'));
    expect(byKey($tender->rows, 'method', 'Cash')['received'])->toBe(200_000 + 100_000 + 300_000)
        ->and($tender->totals['net'])->toBe(600_000);
});

it('ages dues into buckets as of today', function (): void {
    $result = app(DuesAging::class)->run(($this->params)());

    $alice = byKey($result->rows, 'customer', 'Alice'); // Oct 10: 24 days
    $bob = byKey($result->rows, 'customer', 'Bob'); // Sep 2: 62 days
    expect($alice['d30'])->toBe(250_000)->and($alice['total'])->toBe(250_000)
        ->and($bob['d90'])->toBe(500_000)
        ->and($result->totals['total'])->toBe(750_000)
        ->and(array_column($result->rows, 'customer'))->toBe(['Bob', 'Alice']);
});

it('values stock at batch cost and retail', function (): void {
    $result = app(StockValuation::class)->run(($this->params)());
    $shirt = byKey($result->rows, 'product', 'Shirt');

    // 100 received, 2 + 1 + 3 sold
    expect($shirt['qty'])->toBe(94_000)
        ->and($shirt['cost_value'])->toBe(94 * 60_000)
        ->and($shirt['retail_value'])->toBe(94 * 100_000)
        ->and($result->totals['cost_value'])->toBe(94 * 60_000 + 97 * 150_000);

    $hidden = $result->withoutProfit();
    expect(array_column($hidden->columns, 'key'))->not->toContain('cost_value');
});

it('summarises the cash by day and method, with refunds and returns', function (): void {
    $sale = $this->aliceToday->load('items');
    $return = app(CreateSaleReturn::class)->handle(
        $this->world->user('Manager'),
        new ReturnDraft($sale->id, [new ReturnLineDraft($sale->items[0]->id, Quantity::ofUnits(1))], RefundMode::Refund, 'size'),
    );

    $result = app(DailyCashSummary::class)->run(($this->params)('2026-11-03', '2026-11-03'));
    $row = $result->rows[0];
    $cashKey = collect($result->columns)->firstWhere('label', 'Cash')->key;

    expect($row['sales'])->toBe(100_000)
        ->and($row[$cashKey])->toBe(200_000)
        ->and($row['collections'])->toBe(200_000)
        ->and($row['refunds'])->toBe(100_000)
        ->and($row['net_cash'])->toBe(100_000);

    $returns = app(Returns::class)->run(($this->params)());
    expect($returns->rows)->toHaveCount(1)
        ->and($returns->rows[0]['number'])->toBe($return->number)
        ->and($returns->rows[0]['total'])->toBe(100_000)
        ->and($returns->rows[0]['refunded'])->toBe(100_000)
        ->and($returns->totals['total'])->toBe(100_000);
    expect(app(Returns::class)->run(($this->params)('2026-10-01', '2026-10-31'))->rows)->toBe([]);
});

it('counts only the selected branch unless all branches are asked for', function (): void {
    $second = Branch::factory()->for($this->world->tenant)->create(['code' => 'DF2']);
    app(TenantContext::class)->withoutTenancy(fn () => Sale::factory()->create([
        'tenant_id' => $this->world->tenant->id, 'branch_id' => $second->id, 'customer_id' => $this->bob->id,
        'sold_at' => Carbon::parse('2026-11-03 06:00:00', 'UTC'), 'total' => Money::ofPaisa(70_000), 'status' => 'completed',
    ]));

    $current = app(SalesByDay::class)->run(($this->params)('2026-11-03', '2026-11-03'));
    $all = app(SalesByDay::class)->run(new ReportParameters('2026-11-03', '2026-11-03', 'Asia/Dhaka', null));
    $other = app(SalesByDay::class)->run(new ReportParameters('2026-11-03', '2026-11-03', 'Asia/Dhaka', [$second->id]));

    expect($current->totals['invoices'])->toBe(1)
        ->and($all->totals['invoices'])->toBe(2)
        ->and($other->totals['invoices'])->toBe(1)
        ->and($other->totals['total'])->toBe(70_000);
});

it('never counts another tenant', function (): void {
    $context = app(TenantContext::class);
    $context->withoutTenancy(function (): void {
        $other = Tenant::factory()->create();
        $branch = Branch::factory()->for($other)->create();
        $customer = Customer::factory()->create(['tenant_id' => $other->id, 'name' => 'Mallory']);
        Sale::factory()->create([
            'tenant_id' => $other->id, 'branch_id' => $branch->id, 'customer_id' => $customer->id,
            'sold_at' => Carbon::parse('2026-11-03 06:00:00', 'UTC'), 'total' => Money::ofPaisa(9_999_900),
            'due' => Money::ofPaisa(9_999_900), 'status' => 'completed',
        ]);
    });

    $all = new ReportParameters('2026-09-01', '2026-11-03', 'Asia/Dhaka', null, true);

    expect(array_column(app(TopCustomersByPurchase::class)->run($all)->rows, 'customer'))->not->toContain('Mallory')
        ->and(array_column(app(CustomersByLastPurchase::class)->run($all)->rows, 'customer'))->not->toContain('Mallory')
        ->and(array_column(app(DuesAging::class)->run($all)->rows, 'customer'))->not->toContain('Mallory')
        ->and(app(SalesByDay::class)->run($all)->totals['invoices'])->toBe(3);
});

it('formats a decimal CSV for money and quantity cells', function (): void {
    $user = $this->world->user('Owner');
    $user->branches()->attach($this->world->branch);

    $response = $this->actingAs($user)->post(route('reports.export', ['report' => 'sales-by-product', 'from' => '2026-07-01', 'to' => '2026-11-03']));

    $csv = $response->assertOk()->streamedContent();
    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('SKU,Product')
        ->and($csv)->toContain(',6.000,0.000,6.000,6000.00,3600.00,2400.00')
        ->and($csv)->toContain('Total');
});
