<?php

declare(strict_types=1);

use App\Actions\Purchasing\ReceivePurchase;
use App\Actions\Sales\CollectCustomerPayment;
use App\Actions\Sales\Data\CustomerPaymentDraft;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\MembershipCard;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Reports\CardsIssued;
use App\Reports\CollectionsByUser;
use App\Reports\PurchasesByVendor;
use App\Reports\ReportParameters;
use App\Reports\ReportRegistry;
use App\Reports\SalesByBrand;
use App\Reports\SalesByCategory;
use App\Reports\SalesByFamily;
use App\Reports\SalesByProduct;
use App\Reports\SalesSummary;
use App\Reports\StockMovements;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\SalesWorld;

/** 2026-11-03 14:00 in Dhaka (UTC+6). */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-11-03 08:00:00', 'UTC'));
    $this->world = SalesWorld::make();
    $tenantId = $this->world->tenant->id;

    $this->category = Category::factory()->create(['tenant_id' => $tenantId, 'name' => 'Shirts']);
    $this->brand = Brand::factory()->create(['tenant_id' => $tenantId, 'name' => 'Acme']);
    $this->family = ProductFamily::factory()->create(['tenant_id' => $tenantId, 'name' => 'Polo']);
    $this->shirt = $this->world->product(100_000, 60_000, [
        'name' => 'Shirt', 'category_id' => $this->category->id, 'brand_id' => $this->brand->id, 'family_id' => $this->family->id,
    ]);
    $this->pant = $this->world->product(250_000, 150_000, ['name' => 'Pant']); // no category, brand or family
    $this->world->stock($this->shirt, 100, 60_000);
    $this->world->stock($this->pant, 100, 150_000);

    $this->group = CustomerGroup::factory()->create(['tenant_id' => $tenantId, 'name' => 'Gold']);
    $this->alice = $this->world->customer(['name' => 'Alice', 'customer_group_id' => $this->group->id]);
    $this->bob = $this->world->customer(['name' => 'Bob']);

    // Owner: 2 shirts paid in cash. Manager: 1 pant on credit. Both today.
    $this->aliceSale = $this->world->sell([$this->world->line($this->shirt, 2)], [$this->world->tender('cash', 200_000)], $this->alice);
    $this->bobSale = $this->world->sell([$this->world->line($this->pant, 1)], [], $this->bob, 'Manager');
    // An older sale (September) that falls outside the day.
    $this->old = $this->world->sell([$this->world->line($this->shirt, 1)], [$this->world->tender('cash', 100_000)], $this->alice);
    DB::table('sales')->where('id', $this->old->id)->update(['sold_at' => Carbon::parse('2026-09-02 06:00:00', 'UTC')]);
    // Its tender moves with it: a sale-time tender's paid_at equals the sale's sold_at.
    DB::table('payments')
        ->whereIn('id', DB::table('payment_allocations')->where('allocatable_type', 'sale')->where('allocatable_id', $this->old->id)->select('payment_id'))
        ->update(['paid_at' => Carbon::parse('2026-09-02 06:00:00', 'UTC')]);

    $this->params = fn (string $from = '2026-11-03', string $to = '2026-11-03', ?array $branches = null, bool $profit = true, array $filters = []): ReportParameters => new ReportParameters($from, $to, 'Asia/Dhaka', $branches ?? [$this->world->branch->id], $profit, $filters);

    // A second tenant with data in every area the reports read.
    $this->foreign = app(TenantContext::class)->withoutTenancy(function (): array {
        $other = Tenant::factory()->create();
        $branch = Branch::factory()->for($other)->create();
        $customer = Customer::factory()->create(['tenant_id' => $other->id, 'name' => 'Mallory']);
        $product = Product::factory()->create(['tenant_id' => $other->id]);
        $sale = Sale::factory()->create([
            'tenant_id' => $other->id, 'branch_id' => $branch->id, 'customer_id' => $customer->id,
            'sold_at' => Carbon::parse('2026-11-03 06:00:00', 'UTC'), 'total' => Money::ofPaisa(9_999_900), 'status' => 'completed',
        ]);
        SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'line_total' => Money::ofPaisa(9_999_900)]);
        StockMovement::factory()->create(['tenant_id' => $other->id, 'branch_id' => $branch->id, 'product_id' => $product->id]);
        Purchase::factory()->create(['tenant_id' => $other->id, 'branch_id' => $branch->id, 'status' => 'received', 'total' => Money::ofPaisa(7_777_700)]);
        MembershipCard::factory()->create(['tenant_id' => $other->id, 'customer_id' => $customer->id]);
        Payment::factory()->create(['tenant_id' => $other->id, 'branch_id' => $branch->id, 'direction' => 'in', 'party_type' => 'customer', 'party_id' => $customer->id, 'amount' => Money::ofPaisa(5_550_000)]);

        return [$other, $branch];
    });
    $this->all = new ReportParameters('2026-01-01', '2026-12-31', 'Asia/Dhaka', null, true);
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

function moreBy(array $rows, string $key, string $value): array
{
    return collect($rows)->firstWhere($key, $value) ?? [];
}

it('registers the eight additional reports after the existing ones', function (): void {
    $names = array_map(static fn ($r) => $r->name(), app(ReportRegistry::class)->all());

    expect(array_slice($names, -8))->toBe([
        'sales-summary', 'sales-by-category', 'sales-by-brand', 'sales-by-family',
        'stock-movements', 'purchases-by-vendor', 'collections-by-user', 'cards-issued',
    ]);
});

it('summarises the period in one row and narrows by cashier', function (): void {
    $result = app(SalesSummary::class)->run(($this->params)());

    expect($result->rows)->toHaveCount(1)
        ->and($result->rows[0])->toMatchArray(['invoices' => 2, 'gross' => 450_000, 'total' => 450_000, 'paid' => 200_000, 'due' => 250_000, 'returned' => 0, 'net' => 450_000]);

    $manager = app(SalesSummary::class)->run(($this->params)(filters: ['cashier' => (string) $this->world->user('Manager')->id]));
    expect($manager->rows[0])->toMatchArray(['invoices' => 1, 'total' => 250_000]);
});

it('returns a zero summary for an empty period', function (): void {
    $result = app(SalesSummary::class)->run(($this->params)('2026-01-01', '2026-01-02'));

    expect($result->rows[0])->toMatchArray(['invoices' => 0, 'total' => 0, 'net' => 0]);
});

it('rolls sales up by category, brand and family with an uncategorised row', function (): void {
    $category = app(SalesByCategory::class)->run(($this->params)());
    $brand = app(SalesByBrand::class)->run(($this->params)());
    $family = app(SalesByFamily::class)->run(($this->params)());

    expect(moreBy($category->rows, 'category', 'Shirts'))->toMatchArray(['sold' => 2_000, 'sales' => 200_000, 'cogs' => 120_000, 'profit' => 80_000, 'margin_bp' => 4_000])
        ->and(moreBy($category->rows, 'category', 'Uncategorised'))->toMatchArray(['sold' => 1_000, 'sales' => 250_000])
        ->and(moreBy($brand->rows, 'brand', 'Acme')['sales'])->toBe(200_000)
        ->and(moreBy($brand->rows, 'brand', 'No brand')['sales'])->toBe(250_000)
        ->and(moreBy($family->rows, 'family', 'Polo')['sales'])->toBe(200_000)
        ->and(moreBy($family->rows, 'family', 'No family')['sales'])->toBe(250_000);
});

it('reconciles the roll-ups with sales by product', function (): void {
    $product = app(SalesByProduct::class)->run(($this->params)('2026-01-01', '2026-12-31'))->totals;

    foreach ([SalesByCategory::class, SalesByBrand::class, SalesByFamily::class] as $report) {
        $totals = app($report)->run(($this->params)('2026-01-01', '2026-12-31'))->totals;
        expect($totals['sales'])->toBe($product['sales'])
            ->and($totals['cogs'])->toBe($product['cogs'])
            ->and($totals['sold'])->toBe($product['sold']);
    }
});

it('filters the roll-ups by cashier, brand and category', function (): void {
    $owner = (string) $this->world->user('Owner')->id;

    expect(app(SalesByCategory::class)->run(($this->params)(filters: ['cashier' => $owner]))->totals['sales'])->toBe(200_000)
        ->and(app(SalesByCategory::class)->run(($this->params)(filters: ['brand' => (string) $this->brand->id]))->totals['sales'])->toBe(200_000)
        ->and(app(SalesByBrand::class)->run(($this->params)(filters: ['category' => (string) $this->category->id]))->totals['sales'])->toBe(200_000)
        ->and(app(SalesByFamily::class)->run(($this->params)(filters: ['category' => (string) $this->category->id, 'brand' => (string) $this->brand->id]))->totals['sales'])->toBe(200_000);
});

it('returns empty roll-ups for an empty period', function (): void {
    $result = app(SalesByCategory::class)->run(($this->params)('2026-01-01', '2026-01-02'));

    expect($result->rows)->toBe([])->and($result->totals['sales'])->toBe(0);
});

it('flags the cost and margin columns of the roll-ups as profit columns', function (): void {
    $columns = collect(app(SalesByBrand::class)->run(($this->params)())->columns)->keyBy('key');

    expect($columns['cogs']->profit)->toBeTrue()
        ->and($columns['profit']->profit)->toBeTrue()
        ->and($columns['margin_bp']->profit)->toBeTrue()
        ->and($columns['sales']->profit)->toBeFalse();
});

it('lists the stock ledger of the period with quantities in and out', function (): void {
    $result = app(StockMovements::class)->run(($this->params)());

    $sale = collect($result->rows)->first(fn (array $r): bool => $r['type'] === 'sale' && $r['sku'] === $this->shirt->sku && $r['qty_out'] === 2_000);
    $opening = collect($result->rows)->first(fn (array $r): bool => $r['type'] === 'opening' && $r['sku'] === $this->shirt->sku);
    expect($sale)->toMatchArray(['qty_in' => 0, 'qty_out' => 2_000, 'user' => $this->world->user('Owner')->name])
        ->and($opening)->toMatchArray(['qty_in' => 100_000, 'qty_out' => 0])
        ->and($result->totals['qty_in'])->toBeGreaterThanOrEqual(200_000);

    $filtered = app(StockMovements::class)->run(($this->params)(filters: ['category' => (string) $this->category->id]));
    expect(array_unique(array_column($filtered->rows, 'sku')))->toBe([$this->shirt->sku]);
});

it('returns no stock movements for an empty period and honours the branch', function (): void {
    expect(app(StockMovements::class)->run(($this->params)('2026-01-01', '2026-01-02'))->rows)->toBe([]);

    $second = Branch::factory()->for($this->world->tenant)->create(['code' => 'DF2']);
    expect(app(StockMovements::class)->run(($this->params)(branches: [$second->id]))->rows)->toBe([]);
});

it('totals received purchase bills per vendor and skips cancelled ones', function (): void {
    $vendor = Vendor::factory()->create(['tenant_id' => $this->world->tenant->id, 'name' => 'Acme Textiles']);
    $bill = fn (int $units): Purchase => app(ReceivePurchase::class)->handle($this->world->user('Owner'), [
        'vendor_id' => $vendor->id,
        'items' => [['product_id' => $this->shirt->id, 'qty' => Quantity::ofUnits($units), 'unit_cost' => Money::ofPaisa(50_000)]],
        'payment' => ['amount' => Money::ofPaisa(100_000), 'payment_method_id' => $this->world->methods['cash']->id],
    ]);
    $bill(4); // 200,000 total, 100,000 paid
    $cancelled = $bill(2);
    DB::table('purchases')->where('id', $cancelled->id)->update(['status' => 'cancelled']);

    $result = app(PurchasesByVendor::class)->run(($this->params)());

    expect($result->rows)->toHaveCount(1)
        ->and($result->rows[0])->toMatchArray(['vendor' => 'Acme Textiles', 'bills' => 1, 'total' => 200_000, 'paid' => 100_000, 'due' => 100_000])
        ->and(app(PurchasesByVendor::class)->run(($this->params)(filters: ['vendor' => '999999']))->rows)->toBe([])
        ->and(app(PurchasesByVendor::class)->run(($this->params)('2026-01-01', '2026-01-02'))->rows)->toBe([]);
});

it('counts only due collections, not sale-time tenders', function (): void {
    $this->travel(10)->minutes();
    app(CollectCustomerPayment::class)->handle($this->world->user('Owner'), new CustomerPaymentDraft($this->bob->id, Money::ofPaisa(100_000), $this->world->methods['cash']->id));
    app(CollectCustomerPayment::class)->handle($this->world->user('Manager'), new CustomerPaymentDraft($this->bob->id, Money::ofPaisa(50_000), $this->world->methods['cash']->id));

    $result = app(CollectionsByUser::class)->run(($this->params)());

    expect(array_column($result->rows, 'user'))->toBe([$this->world->user('Owner')->name, $this->world->user('Manager')->name])
        ->and($result->rows[0])->toMatchArray(['collections' => 1, 'amount' => 100_000, 'customers' => 1, 'average' => 100_000])
        ->and($result->totals)->toMatchArray(['collections' => 2, 'amount' => 150_000, 'average' => 75_000]);

    $only = app(CollectionsByUser::class)->run(($this->params)(filters: ['cashier' => (string) $this->world->user('Manager')->id]));
    expect($only->rows)->toHaveCount(1)->and($only->rows[0]['amount'])->toBe(50_000);
});

it('has no collections when nothing was collected after a sale', function (): void {
    expect(app(CollectionsByUser::class)->run(($this->params)())->rows)->toBe([]);
});

it('counts new and reissued membership cards per customer group', function (): void {
    $tenantId = $this->world->tenant->id;
    $issuer = $this->world->user('Owner')->id;
    $card = fn ($customer, int $issueNo, string $status, string $at = '2026-11-03 07:00:00') => MembershipCard::factory()->create([
        'tenant_id' => $tenantId, 'customer_id' => $customer->id, 'issue_no' => $issueNo, 'status' => $status,
        'issued_at' => Carbon::parse($at, 'UTC'), 'issued_by' => $issuer,
    ]);
    $card($this->alice, 1, 'replaced');
    $card($this->alice, 2, 'active');
    $card($this->bob, 1, 'active');
    $card($this->bob, 1, 'lost', '2026-09-01 07:00:00'); // outside the period

    $result = app(CardsIssued::class)->run(($this->params)());

    expect(moreBy($result->rows, 'group', 'Gold'))->toMatchArray(['issued' => 2, 'first_cards' => 1, 'reissued' => 1, 'active' => 1, 'replaced' => 1])
        ->and(moreBy($result->rows, 'group', 'No group'))->toMatchArray(['issued' => 1, 'first_cards' => 1, 'active' => 1])
        ->and($result->totals['issued'])->toBe(3)
        ->and(json_encode($result->rows))->not->toContain('pin');
});

it('returns no cards for an empty period', function (): void {
    expect(app(CardsIssued::class)->run(($this->params)())->rows)->toBe([]);
});

it('never counts another tenant in any of the new reports', function (): void {
    $payments = app(CollectionsByUser::class)->run($this->all);

    expect(app(SalesSummary::class)->run($this->all)->rows[0]['total'])->toBe(450_000 + 100_000)
        ->and(app(SalesByCategory::class)->run($this->all)->totals['sales'])->toBe(550_000)
        ->and(app(SalesByBrand::class)->run($this->all)->totals['sales'])->toBe(550_000)
        ->and(app(SalesByFamily::class)->run($this->all)->totals['sales'])->toBe(550_000)
        ->and(array_sum(array_column(app(StockMovements::class)->run($this->all)->rows, 'qty_in')) + array_sum(array_column(app(StockMovements::class)->run($this->all)->rows, 'qty_out')))->toBeLessThan(1_000_000)
        ->and(app(PurchasesByVendor::class)->run($this->all)->rows)->toBe([])
        ->and($payments->rows)->toBe([])
        ->and(app(CardsIssued::class)->run($this->all)->rows)->toBe([]);
});

it('restricts the new sales reports to the chosen branch', function (): void {
    $second = Branch::factory()->for($this->world->tenant)->create(['code' => 'DF3']);

    expect(app(SalesSummary::class)->run(($this->params)(branches: [$second->id]))->rows[0]['invoices'])->toBe(0)
        ->and(app(SalesByCategory::class)->run(($this->params)(branches: [$second->id]))->rows)->toBe([])
        ->and(app(SalesSummary::class)->run(($this->params)(branches: null))->rows[0]['invoices'])->toBe(2);
});
