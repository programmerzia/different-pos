<?php

declare(strict_types=1);

use App\Actions\Purchasing\PayVendor;
use App\Actions\Purchasing\ReceivePurchase;
use App\Actions\Sales\CreateSaleReturn;
use App\Actions\Sales\Data\Discount;
use App\Actions\Sales\Data\ReturnDraft;
use App\Actions\Sales\Data\ReturnLineDraft;
use App\Enums\RefundMode;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Models\TaxRate;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Reports\BranchTransfers;
use App\Reports\CustomersByArea;
use App\Reports\DeadStock;
use App\Reports\Discounts;
use App\Reports\LowStock;
use App\Reports\ProfitAndLoss;
use App\Reports\ReportParameters;
use App\Reports\ReportRegistry;
use App\Reports\ReturnsByReason;
use App\Reports\SalesByProduct;
use App\Reports\VatOutput;
use App\Reports\VendorPayables;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\SalesWorld;

/**
 * 2026-11-03 14:00 in Dhaka. VAT exclusive. Known sales, returns and purchases, so every report
 * asserts exact figures.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-11-03 08:00:00', 'UTC'));
    $this->world = SalesWorld::make();
    $tenantId = $this->world->tenant->id;
    $vat = TaxRate::factory()->create(['tenant_id' => $tenantId, 'rate_bp' => 1500]);

    $this->shirt = $this->world->product(100_000, 60_000, ['name' => 'Shirt']);
    $this->pant = $this->world->product(250_000, 150_000, ['name' => 'Pant', 'reorder_level' => Quantity::ofUnits(5)]);
    $this->taxed = $this->world->product(10_000, 6_000, ['name' => 'Taxed cap', 'tax_rate_id' => $vat->id]);
    $this->old = $this->world->product(30_000, 20_000, ['name' => 'Old scarf']);
    $this->world->stock($this->shirt, 100, 60_000);
    $this->world->stock($this->pant, 100, 150_000);
    $this->world->stock($this->taxed, 1, 6_000);
    $this->world->stock($this->old, 5, 20_000);

    $this->alice = $this->world->customer(['name' => 'Alice']);

    // A (Owner, today): 2 shirts, 10 % line discount, ৳100 order discount → 170,000, cash.
    $this->saleA = $this->world->sell(
        [$this->world->line($this->shirt, 2, discount: Discount::percentBp(1_000))],
        [$this->world->tender('cash', 170_000)],
        $this->alice,
        orderDiscount: Discount::amount(Money::ofPaisa(10_000)),
    );
    // B (Manager, today): 1 taxed cap → 10,000 + 1,500 VAT, cash.
    $this->saleB = $this->world->sell([$this->world->line($this->taxed, 1)], [$this->world->tender('cash', 11_500)], $this->alice, 'Manager');
    // C (Owner, 10 Oct): 1 pant, cash.
    $this->saleC = $this->world->sell([$this->world->line($this->pant, 1)], [$this->world->tender('cash', 250_000)], $this->alice);
    DB::table('sales')->where('id', $this->saleC->id)->update(['sold_at' => Carbon::parse('2026-10-10 06:00:00', 'UTC')]);

    // Returns today: 1 shirt of A (size, restocked), the pant of C (defect, written off).
    $this->returnA = app(CreateSaleReturn::class)->handle($this->world->user('Manager'), new ReturnDraft(
        $this->saleA->id, [new ReturnLineDraft($this->saleA->items()->first()->id, Quantity::ofUnits(1))], RefundMode::Refund, 'size',
    ));
    $this->returnC = app(CreateSaleReturn::class)->handle($this->world->user('Manager'), new ReturnDraft(
        $this->saleC->id, [new ReturnLineDraft($this->saleC->items()->first()->id, Quantity::ofUnits(1), restock: false)], RefundMode::Refund, 'defect',
    ));

    // Purchases: P1 (1 Oct) 10 shirts @ 500 = 500,000, 200,000 paid → 300,000 due; P2 fully paid.
    $this->vendor = Vendor::factory()->create(['tenant_id' => $tenantId, 'name' => 'Acme Textiles', 'company' => 'Acme Ltd']);
    $this->p1 = app(ReceivePurchase::class)->handle($this->world->user('Owner'), [
        'vendor_id' => $this->vendor->id, 'vendor_ref' => 'INV-77',
        'items' => [['product_id' => $this->shirt->id, 'qty' => Quantity::ofUnits(10), 'unit_cost' => Money::ofPaisa(50_000)]],
    ]);
    DB::table('purchases')->where('id', $this->p1->id)->update(['purchased_at' => Carbon::parse('2026-10-01 06:00:00', 'UTC')]);
    app(PayVendor::class)->handle($this->world->user('Owner'), [
        'vendor_id' => $this->vendor->id, 'amount' => Money::ofPaisa(200_000), 'payment_method_id' => $this->world->methods['cash']->id,
        'allocations' => [['purchase_id' => $this->p1->id, 'amount' => Money::ofPaisa(200_000)]],
    ]);
    $this->p2 = app(ReceivePurchase::class)->handle($this->world->user('Owner'), [
        'vendor_id' => $this->vendor->id,
        'items' => [['product_id' => $this->pant->id, 'qty' => Quantity::ofUnits(2), 'unit_cost' => Money::ofPaisa(100_000)]],
        'payment' => ['amount' => Money::ofPaisa(200_000), 'payment_method_id' => $this->world->methods['cash']->id],
    ]);

    $this->params = fn (string $from = '2026-11-03', string $to = '2026-11-03', ?array $branches = null, bool $profit = true, array $filters = []): ReportParameters => new ReportParameters($from, $to, 'Asia/Dhaka', $branches ?? [$this->world->branch->id], $profit, $filters);
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

function rowBy(array $rows, string $key, string $value): array
{
    return collect($rows)->firstWhere($key, $value) ?? [];
}

it('lists the fourteen mockup reports first, then the extra ones', function (): void {
    $names = array_map(static fn ($r) => $r->name(), app(ReportRegistry::class)->all());

    expect(array_slice($names, 0, 14))->toBe([
        'daily-cash', 'discounts', 'returns-by-reason', 'sales-by-day', 'sales-by-product', 'dues-aging', 'top-customers',
        'stock-valuation', 'low-stock', 'dead-stock', 'branch-transfers', 'vendor-payables', 'pnl', 'vat-output',
    ])->and(array_slice($names, 14))->toBe(['sales-by-user', 'sales-by-tender', 'returns', 'customers-last-purchase', 'customers-by-area']);
});

it('breaks discounts down by cashier and kind with the effective rate', function (): void {
    $result = app(Discounts::class)->run(($this->params)());

    expect(array_column($result->rows, 'user'))->toBe([$this->world->user('Owner')->name, $this->world->user('Manager')->name]);
    $owner = $result->rows[0];
    expect($owner)->toMatchArray(['invoices' => 1, 'discounted' => 1, 'gross' => 200_000, 'line_discounts' => 20_000, 'order_discounts' => 10_000, 'discounts' => 30_000, 'discount_bp' => 1_500])
        ->and($result->rows[1])->toMatchArray(['invoices' => 1, 'discounted' => 0, 'gross' => 10_000, 'discounts' => 0, 'discount_bp' => 0])
        ->and($result->totals)->toMatchArray(['invoices' => 2, 'discounts' => 30_000, 'discount_bp' => 1_429]);

    $only = app(Discounts::class)->run(($this->params)(filters: ['cashier' => (string) $this->world->user('Manager')->id]));
    expect($only->rows)->toHaveCount(1)->and($only->rows[0]['gross'])->toBe(10_000);
});

it('reports returns by reason: restocked vs written off, share and return rate', function (): void {
    $result = app(ReturnsByReason::class)->run(($this->params)());
    $size = rowBy($result->rows, 'reason', 'size');
    $defect = rowBy($result->rows, 'reason', 'defect');
    $sizeValue = $this->returnA->total->paisa;
    $todaySales = 170_000 + 11_500;

    expect($defect)->toMatchArray(['returns_count' => 1, 'qty' => 1_000, 'restocked' => 0, 'written_off' => 1_000, 'total' => 250_000, 'refunded' => 250_000, 'credited' => 0])
        ->and($size)->toMatchArray(['returns_count' => 1, 'qty' => 1_000, 'restocked' => 1_000, 'written_off' => 0, 'total' => $sizeValue, 'refunded' => $sizeValue])
        ->and($size['share_bp'] + $defect['share_bp'])->toBeGreaterThanOrEqual(9_999)
        ->and($result->totals['total'])->toBe(250_000 + $sizeValue)
        ->and($result->totals['share_bp'])->toBe(10_000)
        ->and($result->totals['rate_bp'])->toBe((int) round((250_000 + $sizeValue) * 10_000 / $todaySales));

    $only = app(ReturnsByReason::class)->run(($this->params)(filters: ['reason' => 'defect']));
    expect(array_column($only->rows, 'reason'))->toBe(['defect']);
    expect(app(ReturnsByReason::class)->run(($this->params)('2026-10-01', '2026-10-31'))->rows)->toBe([]);
});

it('adds velocity and gross margin to product sales', function (): void {
    $result = app(SalesByProduct::class)->run(($this->params)());
    $shirt = rowBy($result->rows, 'product', 'Shirt');

    expect($shirt)->toMatchArray(['sold' => 2_000, 'returned' => 1_000, 'net_qty' => 1_000, 'velocity' => 1_000, 'sales' => 180_000, 'cogs' => 120_000, 'profit' => 60_000, 'margin_bp' => 3_333])
        ->and(rowBy($result->rows, 'product', 'Taxed cap'))->toMatchArray(['sales' => 11_500, 'cogs' => 6_000, 'margin_bp' => 4_783])
        ->and($result->totals['margin_bp'])->toBe((int) round((180_000 + 11_500 - 126_000) * 10_000 / 191_500))
        ->and(array_keys($result->totals))->not->toContain('velocity_x');

    $plain = $result->withoutProfit();
    expect(array_column($plain->columns, 'key'))->not->toContain('margin_bp')->not->toContain('cogs');
});

it('flags low stock against the reorder level and the 30-day run rate', function (): void {
    $rows = app(LowStock::class)->run(($this->params)())->rows;

    expect(array_column($rows, 'product'))->toBe(['Taxed cap'])
        ->and($rows[0])->toMatchArray(['on_hand' => 0, 'reorder_level' => 0, 'sold_30d' => 1_000, 'run_rate' => 33, 'days_cover' => 0, 'suggested' => 1_000]);

    // Pant: 101 on hand (100 + 2 bought − 1 sold; the return was written off) vs reorder level 5: fine. Raise the level and it shows.
    $this->pant->update(['reorder_level' => Quantity::ofUnits(150)]);
    $rows = app(LowStock::class)->run(($this->params)())->rows;
    $pant = rowBy($rows, 'product', 'Pant');
    expect($pant)->toMatchArray(['on_hand' => 101_000, 'reorder_level' => 150_000, 'sold_30d' => 0, 'run_rate' => 0, 'days_cover' => null, 'suggested' => 49_000]);
});

it('lists dead stock: on hand with no sale in 90 days, at cost and retail', function (): void {
    $result = app(DeadStock::class)->run(($this->params)());

    expect(array_column($result->rows, 'product'))->toBe(['Old scarf'])
        ->and($result->rows[0])->toMatchArray(['on_hand' => 5_000, 'cost_value' => 100_000, 'retail_value' => 150_000, 'last_sold' => null, 'idle_days' => null])
        ->and($result->totals)->toMatchArray(['on_hand' => 5_000, 'cost_value' => 100_000, 'retail_value' => 150_000])
        ->and($result->totals)->not->toHaveKey('idle_days');

    // A sale 100 days ago makes the pant dead stock too, with its idle days.
    DB::table('sales')->where('id', $this->saleC->id)->update(['sold_at' => Carbon::parse('2026-07-26 06:00:00', 'UTC')]);
    $pant = rowBy(app(DeadStock::class)->run(($this->params)())->rows, 'product', 'Pant');
    expect($pant['last_sold'])->toBe('2026-07-26')->and($pant['idle_days'])->toBe(100)->and($pant['on_hand'])->toBe(101_000);
});

it('reads inter-branch transfers from the ledger and is empty without any', function (): void {
    expect(app(BranchTransfers::class)->run(($this->params)())->rows)->toBe([]);

    $base = ['tenant_id' => $this->world->tenant->id, 'branch_id' => $this->world->branch->id, 'product_id' => $this->shirt->id];
    StockMovement::factory()->create([...$base, 'type' => StockMovementType::TransferOut, 'qty' => Quantity::ofUnits(3)->negated()]);
    StockMovement::factory()->create([...$base, 'type' => StockMovementType::TransferIn, 'qty' => Quantity::ofUnits(2)]);

    $result = app(BranchTransfers::class)->run(($this->params)());
    expect($result->rows)->toHaveCount(1)
        ->and($result->rows[0])->toMatchArray(['product' => 'Shirt', 'dispatches' => 1, 'receipts' => 1, 'dispatched' => 3_000, 'received' => 2_000, 'variance' => 1_000, 'in_transit' => 0])
        ->and($result->totals['variance'])->toBe(1_000);
});

it('lists open vendor bills with age, bucket and the vendor balance', function (): void {
    $result = app(VendorPayables::class)->run(($this->params)());

    expect($result->rows)->toHaveCount(1)
        ->and($result->rows[0])->toMatchArray([
            'vendor' => 'Acme Textiles', 'company' => 'Acme Ltd', 'number' => $this->p1->number, 'vendor_ref' => 'INV-77',
            'date' => '2026-10-01', 'age' => 33, 'aging' => 'd60', 'total' => 500_000, 'paid' => 200_000, 'due' => 300_000, 'vendor_balance' => 300_000,
        ])
        ->and($result->totals)->toMatchArray(['total' => 500_000, 'paid' => 200_000, 'due' => 300_000])
        ->and($result->totals)->not->toHaveKey('age')->not->toHaveKey('vendor_balance');

    $other = Vendor::factory()->create(['tenant_id' => $this->world->tenant->id, 'name' => 'Nobody']);
    expect(app(VendorPayables::class)->run(($this->params)(filters: ['vendor' => (string) $other->id]))->rows)->toBe([]);
});

it('builds a P&L that foots: net revenue, COGS net of restocked returns, gross profit', function (): void {
    $result = app(ProfitAndLoss::class)->run(($this->params)());
    $line = fn (string $key): array => rowBy($result->rows, 'line', $key);
    $returned = $this->returnA->total->paisa + 250_000; // both returns carry no VAT
    $cogsReversed = (int) DB::table('sale_return_items')->sum('cogs_reversed');
    $net = 180_000 - $returned;

    expect(array_column($result->rows, 'line'))->toBe(['gross_sales', 'discounts', 'other', 'returns', 'net_revenue', 'vat_collected', 'cogs', 'gross_profit'])
        ->and($line('gross_sales')['amount'])->toBe(210_000)
        ->and($line('discounts')['amount'])->toBe(-30_000)
        ->and($line('other')['amount'])->toBe(0)
        ->and($line('returns')['amount'])->toBe(-$returned)
        ->and($line('net_revenue'))->toMatchArray(['amount' => $net, 'pct_bp' => 10_000])
        ->and($line('vat_collected')['amount'])->toBe(1_500)
        ->and($line('cogs')['amount'])->toBe(-(126_000 - $cogsReversed))
        ->and($line('gross_profit')['amount'])->toBe($net - 126_000 + $cogsReversed)
        ->and($result->totals)->toBeNull();

    $plain = app(ProfitAndLoss::class)->run(($this->params)(profit: false));
    expect(array_column($plain->rows, 'line'))->not->toContain('cogs')->not->toContain('gross_profit');
});

it('summarises output VAT by rate with the order discount shared over the lines', function (): void {
    $result = app(VatOutput::class)->run(($this->params)());

    expect(array_column($result->rows, 'vat_rate_bp'))->toBe([0, 1_500])
        ->and($result->rows[0])->toMatchArray(['invoices' => 1, 'taxable' => 170_000, 'vat' => 0, 'returns_count' => 2, 'returned_taxable' => $this->returnA->total->paisa + 250_000, 'returned_vat' => 0])
        ->and($result->rows[1])->toMatchArray(['invoices' => 1, 'taxable' => 10_000, 'vat' => 1_500, 'returns_count' => 0, 'net_taxable' => 10_000, 'net_vat' => 1_500])
        ->and($result->totals['vat'])->toBe(1_500)
        ->and($result->totals)->not->toHaveKey('vat_rate_bp');
});

it('groups customers and sales by area once the geo tables exist', function (): void {
    if (! Schema::hasTable('geo_districts')) {
        $this->markTestSkipped('geo_* reference tables are not migrated yet (built concurrently); CustomersByArea returns no rows until then.');
    }

    if (DB::table('geo_districts')->doesntExist()) {
        $divisionId = DB::table('geo_divisions')->insertGetId(['code' => 'T1', 'name_en' => 'Testland', 'name_bn' => 'টেস্টল্যান্ড']);
        DB::table('geo_districts')->insert(['division_id' => $divisionId, 'code' => 'T1-1', 'name_en' => 'Testpur', 'name_bn' => 'টেস্টপুর']);
    }
    $district = DB::table('geo_districts')->first();
    $this->alice->update(['geo_district_id' => $district->id]);
    $this->world->customer(['name' => 'Nowhere']);

    $rows = app(CustomersByArea::class)->run(($this->params)(filters: ['level' => 'district']))->rows;
    $area = rowBy($rows, 'area', (string) $district->name_en);
    expect($area)->toMatchArray(['customers' => 1, 'buyers' => 1, 'invoices' => 2, 'net' => 170_000 + 11_500 - $this->returnA->total->paisa, 'dues' => 0])
        ->and($area['area_bn'])->toBe((string) $district->name_bn)
        ->and(collect($rows)->firstWhere('area', null))->toMatchArray(['customers' => 1, 'invoices' => 0]);
});

it('returns an empty area report instead of failing when the geo tables are missing', function (): void {
    if (Schema::hasTable('geo_districts')) {
        $this->markTestSkipped('geo tables exist; the empty-fallback branch is not reachable.');
    }

    $result = app(CustomersByArea::class)->run(($this->params)());
    expect($result->rows)->toBe([])->and($result->totals['customers'])->toBe(0);
});

it('counts only the selected branch unless all branches are asked for', function (): void {
    $tenant = $this->world->tenant;
    $second = Branch::factory()->for($tenant)->create(['code' => 'DF2']);
    app(TenantContext::class)->withoutTenancy(function () use ($tenant, $second): void {
        Sale::factory()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $second->id, 'customer_id' => $this->alice->id, 'sold_by' => $this->world->user('Cashier')->id,
            'sold_at' => Carbon::parse('2026-11-03 06:00:00', 'UTC'), 'subtotal' => Money::ofPaisa(80_000), 'order_discount' => Money::ofPaisa(10_000), 'total' => Money::ofPaisa(70_000), 'status' => 'completed',
        ]);
        Purchase::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $second->id, 'vendor_id' => $this->vendor->id, 'total' => Money::ofPaisa(90_000), 'paid' => Money::zero(), 'due' => Money::ofPaisa(90_000), 'status' => 'received']);
        BranchStock::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $second->id, 'product_id' => $this->old->id, 'qty_on_hand' => Quantity::ofUnits(7)]);
    });

    $current = ($this->params)();
    $all = ($this->params)(branches: null);
    $all = new ReportParameters($all->fromDate, $all->toDate, $all->timezone, null, true);

    expect(app(Discounts::class)->run($current)->totals['order_discounts'])->toBe(10_000)
        ->and(app(Discounts::class)->run($all)->totals['order_discounts'])->toBe(20_000)
        ->and(app(VendorPayables::class)->run($current)->totals['due'])->toBe(300_000)
        ->and(app(VendorPayables::class)->run($all)->totals['due'])->toBe(390_000)
        ->and(app(DeadStock::class)->run($current)->totals['on_hand'])->toBe(5_000)
        ->and(app(DeadStock::class)->run($all)->totals['on_hand'])->toBe(12_000)
        ->and(rowBy(app(ProfitAndLoss::class)->run($current)->rows, 'line', 'gross_sales')['amount'])->toBe(210_000)
        ->and(rowBy(app(ProfitAndLoss::class)->run($all)->rows, 'line', 'gross_sales')['amount'])->toBe(290_000);
});

it('never counts another tenant in any of the new reports', function (): void {
    $context = app(TenantContext::class);
    $context->withoutTenancy(function (): void {
        $other = Tenant::factory()->create();
        $branch = Branch::factory()->for($other)->create();
        $customer = Customer::factory()->create(['tenant_id' => $other->id, 'name' => 'Mallory']);
        $vendor = Vendor::factory()->create(['tenant_id' => $other->id, 'name' => 'Evil Vendor']);
        $product = Product::factory()->create(['tenant_id' => $other->id, 'name' => 'Evil product']);
        $sale = Sale::factory()->create([
            'tenant_id' => $other->id, 'branch_id' => $branch->id, 'customer_id' => $customer->id,
            'sold_at' => Carbon::parse('2026-11-03 06:00:00', 'UTC'), 'subtotal' => Money::ofPaisa(9_999_900), 'order_discount' => Money::ofPaisa(999_900), 'total' => Money::ofPaisa(9_000_000), 'status' => 'completed',
        ]);
        SaleReturn::factory()->create(['tenant_id' => $other->id, 'branch_id' => $branch->id, 'sale_id' => $sale->id, 'customer_id' => $customer->id, 'returned_at' => Carbon::parse('2026-11-03 06:00:00', 'UTC'), 'reason' => 'colour', 'total' => Money::ofPaisa(5_000_000)]);
        Purchase::factory()->create(['tenant_id' => $other->id, 'branch_id' => $branch->id, 'vendor_id' => $vendor->id, 'total' => Money::ofPaisa(7_000_000), 'due' => Money::ofPaisa(7_000_000), 'status' => 'received']);
        BranchStock::factory()->create(['tenant_id' => $other->id, 'branch_id' => $branch->id, 'product_id' => $product->id, 'qty_on_hand' => Quantity::ofUnits(70)]);
        StockMovement::factory()->create(['tenant_id' => $other->id, 'branch_id' => $branch->id, 'product_id' => $product->id, 'type' => StockMovementType::TransferIn, 'qty' => Quantity::ofUnits(9)]);
    });

    $all = new ReportParameters('2026-11-03', '2026-11-03', 'Asia/Dhaka', null, true);

    expect(app(Discounts::class)->run($all)->totals['order_discounts'])->toBe(10_000)
        ->and(array_column(app(ReturnsByReason::class)->run($all)->rows, 'reason'))->not->toContain('colour')
        ->and(array_column(app(VendorPayables::class)->run($all)->rows, 'vendor'))->not->toContain('Evil Vendor')
        ->and(array_column(app(DeadStock::class)->run($all)->rows, 'product'))->not->toContain('Evil product')
        ->and(array_column(app(LowStock::class)->run($all)->rows, 'product'))->not->toContain('Evil product')
        ->and(app(BranchTransfers::class)->run($all)->rows)->toBe([])
        ->and(rowBy(app(ProfitAndLoss::class)->run($all)->rows, 'line', 'gross_sales')['amount'])->toBe(210_000)
        ->and(app(VatOutput::class)->run($all)->totals['taxable'])->toBe(180_000)
        ->and(app(CustomersByArea::class)->run($all)->totals['customers'])->toBeLessThanOrEqual(1);
});
