<?php

declare(strict_types=1);

use App\Actions\Catalog\AttachProductToFamily;
use App\Actions\Catalog\SaveBrand;
use App\Actions\Catalog\SaveProduct;
use App\Actions\Purchasing\ReceivePurchase;
use App\Actions\Sales\CreateExchange;
use App\Actions\Sales\Data\ExchangeDraft;
use App\Actions\Sales\Data\ReturnLineDraft;
use App\Actions\Sales\Data\SaleDraft;
use App\Actions\Stock\AdjustStock;
use App\Enums\AdjustmentReason;
use App\Enums\ProductItemType;
use App\Enums\RefundMode;
use App\Exceptions\Domain\InvalidCatalogData;
use App\Exceptions\Domain\InvalidSaleDocument;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Unit;
use App\Models\VariantOption;
use App\Models\VariantOptionValue;
use App\Models\Vendor;
use App\Reports\DeadStock;
use App\Reports\LowStock;
use App\Reports\ReportParameters;
use App\Reports\StockValuation;
use App\Support\Money\Money;
use App\Support\Permissions\SystemRoles;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Tests\Fixtures\CatalogWorld;
use Tests\Fixtures\SalesWorld;

afterEach(fn () => app(TenantContext::class)->forget());

function rawSave(SalesWorld $world, array $data = [], ?Product $product = null): Product
{
    return app(SaveProduct::class)->handle($world->user(), [
        'name' => 'Cotton fabric', 'sku' => 'FAB-1', 'unit_id' => $product?->unit_id ?? Unit::query()->first()?->id ?? Unit::factory()->create(['tenant_id' => $world->tenant->id])->id, 'item_type' => ProductItemType::RawMaterial,
        'cost_price' => Money::ofPaisa(45000), ...$data,
    ], $product);
}

it('stores a raw material with a zero retail price, tracked stock and no wholesale or minimum price', function (): void {
    $world = SalesWorld::make();
    $raw = rawSave($world, ['sale_price' => Money::ofPaisa(99900), 'wholesale_price' => Money::ofPaisa(5), 'min_sale_price' => Money::ofPaisa(7), 'track_stock' => false]);

    expect($raw->item_type)->toBe(ProductItemType::RawMaterial)
        ->and($raw->sale_price->paisa)->toBe(0)
        ->and($raw->wholesale_price)->toBeNull()
        ->and($raw->min_sale_price)->toBeNull()
        ->and($raw->track_stock)->toBeTrue()
        ->and($raw->cost_price->paisa)->toBe(45000)
        ->and($raw->barcode)->not->toBeNull();
});

it('defaults every product to finished and still requires its retail price', function (): void {
    $world = SalesWorld::make();
    $finished = app(SaveProduct::class)->handle($world->user(), ['name' => 'Polo', 'sku' => 'P-1', 'unit_id' => Unit::factory()->create(['tenant_id' => $world->tenant->id])->id, 'sale_price' => Money::ofPaisa(1000)]);

    expect($finished->item_type)->toBe(ProductItemType::Finished)
        ->and(fn () => app(SaveProduct::class)->handle($world->user(), ['name' => 'No price', 'sku' => 'P-2', 'unit_id' => $finished->unit_id]))
        ->toThrow(InvalidCatalogData::class, 'Sale price');
});

it('auto-generates the SKU when none is given and never reuses one', function (): void {
    $world = SalesWorld::make();
    $a = rawSave($world, ['sku' => '']);
    $b = rawSave($world, ['sku' => '', 'name' => 'Zip']);
    $f = app(SaveProduct::class)->handle($world->user(), ['name' => 'Cap', 'unit_id' => $a->unit_id, 'sale_price' => Money::ofPaisa(100)]);

    expect($a->sku)->toBe('RM-000001')->and($b->sku)->toBe('RM-000002')->and($f->sku)->toBe('SKU-000001');
    $b->delete();
    expect(rawSave($world, ['sku' => '', 'name' => 'Zip 2'])->sku)->toBe('RM-000003');
});

it('refuses to turn a sold finished product into a raw material, but allows an unsold one', function (): void {
    $world = SalesWorld::make();
    $sold = $world->product(100_000, 40_000);
    $world->stock($sold, 3, 40_000);
    $world->sell([$world->line($sold)], [$world->tender('cash', 100_000)]);
    $unsold = $world->product(100_000, 40_000);

    expect(fn () => rawSave($world, ['sku' => $sold->sku, 'name' => $sold->name], $sold))->toThrow(InvalidCatalogData::class, 'has been sold');
    expect(rawSave($world, ['sku' => $unsold->sku, 'name' => $unsold->name], $unsold)->item_type)->toBe(ProductItemType::RawMaterial);
});

it('lets a raw material become finished again with a retail price', function (): void {
    $world = SalesWorld::make();
    $raw = rawSave($world);
    $back = app(SaveProduct::class)->handle($world->user(), ['item_type' => ProductItemType::Finished, 'sale_price' => Money::ofPaisa(250000)], $raw);

    expect($back->item_type)->toBe(ProductItemType::Finished)->and($back->sale_price->paisa)->toBe(250000);
});

it('refuses a raw material that is a service or already in a family, and keeps raw materials out of families', function (): void {
    $world = SalesWorld::make();
    expect(fn () => rawSave($world, ['type' => 'service']))->toThrow(InvalidCatalogData::class, 'standalone');

    $raw = rawSave($world);
    $option = VariantOption::factory()->create(['tenant_id' => $world->tenant->id]);
    $value = VariantOptionValue::factory()->create(['tenant_id' => $world->tenant->id, 'variant_option_id' => $option->id]);
    $family = ProductFamily::factory()->create(['tenant_id' => $world->tenant->id, 'option1_id' => $option->id, 'unit_id' => $raw->unit_id]);

    expect(fn () => app(AttachProductToFamily::class)->handle($world->user(), $family, $raw, $value->id))->toThrow(InvalidCatalogData::class, 'standalone');
});

it('refuses a raw material on CompleteSale, even when it is the only line or mixed in', function (): void {
    $world = SalesWorld::make();
    $shirt = $world->product(150_000, 60_000);
    $world->stock($shirt, 5, 60_000);
    $raw = rawSave($world);
    $world->stock($raw, 20, 45_000);

    expect(fn () => $world->sell([$world->line($raw)], [$world->tender('cash', 100_000)]))->toThrow(InvalidSaleDocument::class, 'raw material')
        ->and(fn () => $world->sell([$world->line($shirt), $world->line($raw)], [$world->tender('cash', 500_000)]))->toThrow(InvalidSaleDocument::class, 'Cotton fabric');

    expect($shirt->branchStocks()->first()->qty_on_hand->toDecimalString())->toBe('5.000')
        ->and($raw->branchStocks()->first()->qty_on_hand->toDecimalString())->toBe('20.000');
});

it('refuses a raw material as the replacement line of an exchange', function (): void {
    $world = SalesWorld::make();
    $shirt = $world->product(150_000, 60_000);
    $world->stock($shirt, 5, 60_000);
    $raw = rawSave($world);
    $world->stock($raw, 20, 45_000);
    $customer = $world->customer();
    $sale = $world->sell([$world->line($shirt)], [$world->tender('cash', 150_000)], $customer);

    expect(fn () => app(CreateExchange::class)->handle($world->user('Manager'), new ExchangeDraft(
        $sale->id,
        [new ReturnLineDraft($sale->items[0]->id, Quantity::ofUnits(1))],
        new SaleDraft([$world->line($raw)], []),
        RefundMode::Refund,
    )))->toThrow(InvalidSaleDocument::class, 'raw material');

    expect($shirt->branchStocks()->first()->qty_on_hand->toDecimalString())->toBe('4.000');
});

it('keeps raw materials out of the POS catalogue, even on an exact barcode or SKU hit', function (): void {
    $world = SalesWorld::make();
    $shirt = $world->product(150_000, 60_000, ['name' => 'Cotton Shirt', 'sku' => 'SH-1']);
    $raw = rawSave($world, ['name' => 'Cotton fabric', 'barcode' => '5550001', 'unit_id' => $shirt->unit_id]);
    $world->stock($raw, 10, 45_000);
    $world->user()->branches()->attach($world->branch);

    $search = $this->actingAs($world->user())->getJson('/app/api/pos/products?q=Cotton')->assertOk();
    expect(collect($search->json('items'))->pluck('name')->all())->toBe(['Cotton Shirt']);

    foreach (['5550001', 'FAB-1'] as $code) {
        $exact = $this->actingAs($world->user())->getJson('/app/api/pos/products?q='.$code)->assertOk();
        expect($exact->json('exact'))->toBeFalse()->and($exact->json('items'))->toBe([]);
    }
    expect($this->actingAs($world->user())->getJson('/app/api/pos/products')->json('items'))->toHaveCount(1)
        ->and($shirt->item_type)->toBe(ProductItemType::Finished);
});

it('finds raw materials and products in the GRN lookup, each marked with its type, and filters by type', function (): void {
    $world = CatalogWorld::make();
    $unit = $world->unit();
    $world->product(['name' => 'Denim jacket', 'sku' => 'DJ-1', 'unit_id' => $unit->id]);
    $world->product(['name' => 'Denim fabric', 'sku' => 'RM-9', 'item_type' => ProductItemType::RawMaterial, 'unit_id' => $unit->id, 'cost_price' => Money::ofPaisa(30000)]);

    $all = $this->actingAs($world->user)->getJson('/app/purchases/lookup?code=denim')->assertOk();
    expect(collect($all->json('products'))->pluck('item_type', 'sku')->sortKeys()->all())->toBe(['DJ-1' => 'finished', 'RM-9' => 'raw_material']);

    $raw = $this->actingAs($world->user)->getJson('/app/purchases/lookup?code=denim&type=raw_material')->json('products');
    expect($raw)->toHaveCount(1)->and($raw[0]['sku'])->toBe('RM-9')->and($raw[0]['cost_price'])->toBe(30000)->and($raw[0]['unit'])->not->toBeNull();

    $finished = $this->actingAs($world->user)->getJson('/app/purchases/lookup?code=denim&type=finished')->json('products');
    expect($finished)->toHaveCount(1)->and($finished[0]['sku'])->toBe('DJ-1');
    expect($this->actingAs($world->user)->getJson('/app/purchases/lookup?code=RM-9&type=finished')->json('products'))->toBe([]);
});

it('receives fractional raw material stock on a purchase and records production use as an adjustment', function (): void {
    $world = SalesWorld::make();
    $raw = rawSave($world);
    $vendor = Vendor::factory()->create(['tenant_id' => $world->tenant->id]);

    app(ReceivePurchase::class)->handle($world->user(), [
        'vendor_id' => $vendor->id,
        'items' => [['product_id' => $raw->id, 'qty' => Quantity::parse('12.500'), 'unit_cost' => Money::ofPaisa(45000)]],
    ]);
    expect($raw->branchStocks()->first()->qty_on_hand->toDecimalString())->toBe('12.500');

    $adjustment = app(AdjustStock::class)->handle($world->user(), [
        'reason' => AdjustmentReason::ProductionUse,
        'items' => [['product_id' => $raw->id, 'qty' => Quantity::parse('-2.250')]],
    ]);
    expect($adjustment->reason)->toBe(AdjustmentReason::ProductionUse)
        ->and($raw->branchStocks()->first()->qty_on_hand->toDecimalString())->toBe('10.250');
});

it('keeps raw materials in stock valuation and low stock but out of dead stock', function (): void {
    $world = SalesWorld::make();
    $raw = rawSave($world, ['reorder_level' => Quantity::ofUnits(50)]);
    $world->stock($raw, 10, 45_000);
    $idle = $world->product(100_000, 40_000, ['name' => 'Idle shirt']);
    $world->stock($idle, 2, 40_000);

    $params = new ReportParameters('2026-11-01', '2026-11-30', 'Asia/Dhaka', null);
    $skus = static fn (object $report): array => array_column($report->run($params)->rows, 'sku');

    expect($skus(new StockValuation))->toContain('FAB-1', $idle->sku)
        ->and($skus(new LowStock))->toContain('FAB-1')
        ->and($skus(new DeadStock))->not->toContain('FAB-1')
        ->and($skus(new DeadStock))->toContain($idle->sku);
});

// ---- HTTP: product form -------------------------------------------------------------------------------

it('saves a raw material through the product form without a retail price, and refuses variants for it', function (): void {
    $world = CatalogWorld::make();
    $unit = $world->unit();

    $this->actingAs($world->user)->post('/app/products', [
        'name' => 'Linen', 'sku' => 'RM-L', 'type' => 'standard', 'item_type' => 'raw_material', 'unit_id' => $unit->id, 'cost_price' => 12000, 'is_active' => true,
    ])->assertRedirect();
    $linen = Product::withoutGlobalScopes()->where('sku', 'RM-L')->sole();
    expect($linen->item_type)->toBe(ProductItemType::RawMaterial)->and($linen->sale_price->paisa)->toBe(0)->and($linen->cost_price->paisa)->toBe(12000);

    $this->actingAs($world->user)->post('/app/products', [
        'name' => 'Cotton', 'sku' => 'RM-C', 'type' => 'standard', 'item_type' => 'raw_material', 'unit_id' => $unit->id,
        'variants' => ['option1_id' => 1, 'rows' => [['option1_value_id' => 1]]],
    ])->assertSessionHasErrors('variants');

    $this->actingAs($world->user)->post('/app/products', [
        'name' => 'Odd', 'sku' => 'RM-O', 'type' => 'standard', 'item_type' => 'bogus', 'unit_id' => $unit->id, 'sale_price' => 1,
    ])->assertSessionHasErrors('item_type');
    $this->actingAs($world->user)->post('/app/products', [
        'name' => 'Needs price', 'sku' => 'F-1', 'type' => 'standard', 'item_type' => 'finished', 'unit_id' => $unit->id,
    ])->assertSessionHasErrors('sale_price');
});

it('filters the product list by type and marks the row', function (): void {
    $world = CatalogWorld::make();
    $world->product(['name' => 'Shirt', 'sku' => 'S-1']);
    $world->product(['name' => 'Fabric', 'sku' => 'R-1', 'item_type' => ProductItemType::RawMaterial]);

    $rows = fn (string $query) => collect($this->actingAs($world->user)->get('/app/products'.$query)->viewData('page')['props']['rows'])->pluck('item_type', 'sku')->all();

    expect($rows(''))->toBe(['R-1' => 'raw_material', 'S-1' => 'finished'])
        ->and($rows('?filter[type]=raw_material'))->toBe(['R-1' => 'raw_material'])
        ->and($rows('?filter[type]=finished'))->toBe(['S-1' => 'finished'])
        ->and($rows('?filter[type]=nonsense'))->toHaveCount(2);
});

// ---- HTTP: GRN inline create --------------------------------------------------------------------------

it('creates an item inline from the GRN: raw material by default, auto SKU, ready for the bill', function (): void {
    $world = CatalogWorld::make();
    $unit = $world->unit();

    $response = $this->actingAs($world->user)->postJson('/app/purchases/items', ['name' => 'Silk', 'unit_id' => $unit->id, 'cost_price' => 90000])
        ->assertCreated();

    expect($response->json('item_type'))->toBe('raw_material')
        ->and($response->json('sku'))->toBe('RM-000001')
        ->and($response->json('cost_price'))->toBe(90000)
        ->and($response->json('sale_price'))->toBe(0)
        ->and($response->json('unit'))->toBe($unit->short_name)
        ->and($response->json('on_hand'))->toBe(0);
    $this->assertDatabaseHas('products', ['id' => $response->json('id'), 'tenant_id' => $world->tenant->id, 'item_type' => 'raw_material']);

    $finished = $this->actingAs($world->user)->postJson('/app/purchases/items', ['name' => 'Cap', 'unit_id' => $unit->id, 'item_type' => 'finished', 'sale_price' => 50000])->assertCreated();
    expect($finished->json('item_type'))->toBe('finished')->and($finished->json('sku'))->toBe('SKU-000001')->and($finished->json('sale_price'))->toBe(50000);
});

it('validates the inline item and never leaks across tenants', function (): void {
    $world = CatalogWorld::make();
    $other = CatalogWorld::make();
    $unit = $world->unit();

    $this->actingAs($world->user)->postJson('/app/purchases/items', ['unit_id' => $unit->id])->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->actingAs($world->user)->postJson('/app/purchases/items', ['name' => 'X'])->assertUnprocessable()->assertJsonValidationErrors('unit_id');
    $this->actingAs($world->user)->postJson('/app/purchases/items', ['name' => 'X', 'unit_id' => $unit->id, 'cost_price' => -1])->assertUnprocessable()->assertJsonValidationErrors('cost_price');
    $this->actingAs($world->user)->postJson('/app/purchases/items', ['name' => 'X', 'unit_id' => $unit->id, 'item_type' => 'finished'])->assertUnprocessable()->assertJsonValidationErrors('sale_price');
    // A unit of another tenant does not exist for this tenant.
    $this->actingAs($world->user)->postJson('/app/purchases/items', ['name' => 'X', 'unit_id' => $other->unit()->id])->assertUnprocessable()->assertJsonValidationErrors('unit_id');

    $created = $this->actingAs($world->user)->postJson('/app/purchases/items', ['name' => 'Mine', 'unit_id' => $unit->id])->assertCreated();
    $this->assertDatabaseHas('products', ['id' => $created->json('id'), 'tenant_id' => $world->tenant->id]);
    // The other tenant neither sees it in its GRN lookup nor shares its SKU counter.
    expect($this->actingAs($other->user)->getJson('/app/purchases/lookup?code=Mine')->json('products'))->toBe([]);
    $theirs = $this->actingAs($other->user)->postJson('/app/purchases/items', ['name' => 'Theirs', 'unit_id' => $other->unit()->id])->assertCreated();
    expect($theirs->json('sku'))->toBe('RM-000001');
});

it('requires products.create for the inline item, and is a 403 for a member without it', function (): void {
    $cashier = CatalogWorld::make(SystemRoles::CASHIER);
    $none = CatalogWorld::make(role: null);

    $this->actingAs($cashier->user)->postJson('/app/purchases/items', ['name' => 'X', 'unit_id' => $cashier->unit()->id])->assertForbidden();
    $this->actingAs($none->user)->postJson('/app/purchases/items', ['name' => 'X', 'unit_id' => $none->unit()->id])->assertForbidden();
    expect(Product::withoutGlobalScopes()->where('name', 'X')->exists())->toBeFalse();
});

it('does not store a cost the member may not see on the inline item', function (): void {
    $world = CatalogWorld::make(role: null);
    $world->user->givePermissionTo(['products.create', 'purchases.create']);
    $unit = $world->unit();

    $created = $this->actingAs($world->user)->postJson('/app/purchases/items', ['name' => 'Y', 'unit_id' => $unit->id, 'cost_price' => 5000])->assertCreated();
    expect($created->json('cost_price'))->toBe(0)
        ->and(Product::withoutGlobalScopes()->find($created->json('id'))->cost_price->paisa)->toBe(0);
});

it('shows the GRN page its units and whether the member may create items', function (): void {
    $world = CatalogWorld::make();
    $unit = $world->unit();
    $page = $this->actingAs($world->user)->get('/app/purchases/create')->viewData('page')['props'];
    expect($page['canCreateItem'])->toBeTrue()->and(collect($page['units'])->pluck('id')->all())->toContain($unit->id);

    $buyer = CatalogWorld::make(role: null);
    $buyer->user->givePermissionTo(['purchases.create']);
    expect($this->actingAs($buyer->user)->get('/app/purchases/create')->viewData('page')['props']['canCreateItem'])->toBeFalse();
});

// ---- Inline brand -------------------------------------------------------------------------------------

it('creates a brand inline as JSON and returns it for the select', function (): void {
    $world = CatalogWorld::make();

    $response = $this->actingAs($world->user)->postJson('/app/products/brands', ['name' => 'Different Fashion'])->assertCreated();

    expect($response->json('name'))->toBe('Different Fashion');
    $this->assertDatabaseHas('brands', ['id' => $response->json('id'), 'tenant_id' => $world->tenant->id, 'name' => 'Different Fashion']);
});

it('refuses a duplicate brand name inline with a field error, case-insensitively, and restores a deleted one', function (): void {
    $world = CatalogWorld::make();
    $this->actingAs($world->user)->postJson('/app/products/brands', ['name' => 'Different Fashion'])->assertCreated();

    $this->actingAs($world->user)->postJson('/app/products/brands', ['name' => 'different fashion'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->actingAs($world->user)->postJson('/app/products/brands', ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors('name');
    expect(Brand::withoutGlobalScopes()->where('tenant_id', $world->tenant->id)->count())->toBe(1);

    Brand::withoutGlobalScopes()->where('tenant_id', $world->tenant->id)->first()->delete();
    $this->actingAs($world->user)->postJson('/app/products/brands', ['name' => 'Different Fashion'])->assertCreated();
    expect(Brand::withoutGlobalScopes()->where('tenant_id', $world->tenant->id)->count())->toBe(1);
});

it('keeps inline brands per tenant and needs products.create', function (): void {
    $a = CatalogWorld::make();
    $b = CatalogWorld::make();
    $this->actingAs($a->user)->postJson('/app/products/brands', ['name' => 'Shared Name'])->assertCreated();
    // The same name in another tenant is not a duplicate.
    $this->actingAs($b->user)->postJson('/app/products/brands', ['name' => 'Shared Name'])->assertCreated();
    expect(Brand::withoutGlobalScopes()->where('name', 'Shared Name')->pluck('tenant_id')->sort()->values()->all())
        ->toBe(collect([$a->tenant->id, $b->tenant->id])->sort()->values()->all());

    $cashier = CatalogWorld::make(SystemRoles::CASHIER);
    $this->actingAs($cashier->user)->postJson('/app/products/brands', ['name' => 'Nope'])->assertForbidden();
});

it('still saves a brand from the taxonomy page as before (a same name updates, no error)', function (): void {
    $world = CatalogWorld::make();
    $this->actingAs($world->user)->post('/app/products/brands', ['name' => 'Old Brand'])->assertRedirect();
    $this->actingAs($world->user)->post('/app/products/brands', ['name' => 'old brand'])->assertRedirect()->assertSessionHasNoErrors();
    expect(Brand::withoutGlobalScopes()->where('tenant_id', $world->tenant->id)->count())->toBe(1);
});

it('SaveBrand rejects a live duplicate only when asked to', function (): void {
    $world = CatalogWorld::make()->enter();
    $first = app(SaveBrand::class)->handle($world->user, ['name' => 'Alpha']);

    expect(app(SaveBrand::class)->handle($world->user, ['name' => 'ALPHA'])->id)->toBe($first->id)
        ->and(fn () => app(SaveBrand::class)->handle($world->user, ['name' => 'ALPHA'], null, true))->toThrow(InvalidCatalogData::class, 'already exists');
});
