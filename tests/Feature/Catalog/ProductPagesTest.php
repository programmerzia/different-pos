<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\VariantOption;
use App\Models\VariantOptionValue;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\CatalogWorld;

afterEach(fn () => app(TenantContext::class)->forget());

it('sends guests to the login page', function (): void {
    $this->get('/app/products')->assertRedirect();
});

it('refuses users without products.view', function (): void {
    $world = CatalogWorld::make(role: null);
    $this->actingAs($world->user)->get('/app/products')->assertForbidden();
});

it('lets a cashier look but not create or edit', function (): void {
    $world = CatalogWorld::make(SystemRoles::CASHIER);
    $product = $world->product();

    $this->actingAs($world->user)->get('/app/products')->assertOk();
    $this->actingAs($world->user)->get('/app/products/create')->assertForbidden();
    $this->actingAs($world->user)->get("/app/products/{$product->uuid}/edit")->assertForbidden();
    $this->actingAs($world->user)->delete("/app/products/{$product->uuid}")->assertForbidden();
});

it('lists products with the branch stock, search and a category filter, and hides cost without products.view_cost', function (): void {
    $world = CatalogWorld::make(SystemRoles::CASHIER);
    $category = app(TenantContext::class)->withoutTenancy(fn () => Category::factory()->create(['tenant_id' => $world->tenant->id, 'name' => 'Shirts']));
    $world->product(['name' => 'Blue Shirt', 'sku' => 'SH-1', 'category_id' => $category->id]);
    $world->product(['name' => 'Trousers', 'sku' => 'TR-1']);

    $this->actingAs($world->user)->get('/app/products?q=shirt')
        ->assertInertia(fn (Assert $page) => $page->component('products/index')
            ->has('rows', 1)
            ->where('rows.0.name', 'Blue Shirt')
            ->where('rows.0.cost_price', null)
            ->where('canViewCost', false));

    $this->actingAs($world->user)->get("/app/products?filter[category]={$category->id}")
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.sku', 'SH-1'));
});

it('shows cost to users who may see it', function (): void {
    $world = CatalogWorld::make();
    $world->product(['name' => 'Costly']);

    $this->actingAs($world->user)->get('/app/products')
        ->assertInertia(fn (Assert $page) => $page->where('canViewCost', true)->whereType('rows.0.cost_price', 'integer'));
});

it('never shows or opens another tenant\'s product', function (): void {
    $mine = CatalogWorld::make();
    $other = CatalogWorld::make();
    $theirs = $other->product(['name' => 'Secret Product']);

    $this->actingAs($mine->user)->get('/app/products')
        ->assertInertia(fn (Assert $page) => $page->has('rows', 0));
    $this->actingAs($mine->user)->get("/app/products/{$theirs->uuid}/edit")->assertNotFound();
    $this->actingAs($mine->user)->put("/app/products/{$theirs->uuid}", ['name' => 'x'])->assertNotFound();
    $this->actingAs($mine->user)->delete("/app/products/{$theirs->uuid}")->assertNotFound();
});

it('creates a product with money in paisa and an auto barcode', function (): void {
    $world = CatalogWorld::make();
    $unit = $world->unit();

    $this->actingAs($world->user)->post('/app/products', [
        'name' => 'Polo Shirt', 'sku' => 'POLO-1', 'type' => 'standard', 'unit_id' => $unit->id,
        'sale_price' => 125050, 'cost_price' => 80000, 'reorder_level' => 5000, 'track_stock' => true, 'is_active' => true,
    ])->assertRedirect('/app/products');

    $product = app(TenantContext::class)->withoutTenancy(fn () => Product::query()->where('sku', 'POLO-1')->firstOrFail());
    expect($product->sale_price->paisa)->toBe(125050)
        ->and($product->cost_price->paisa)->toBe(80000)
        ->and($product->reorder_level->milli)->toBe(5000)
        ->and($product->barcode)->not->toBeNull()
        ->and($product->tenant_id)->toBe($world->tenant->id);
});

it('ignores a submitted cost price without products.view_cost', function (): void {
    $world = CatalogWorld::make(SystemRoles::STOCK_KEEPER);
    $world->user->revokePermissionTo('products.view_cost');
    $world->user->syncRoles([]);
    $world->user->givePermissionTo(['products.view', 'products.create']);
    $unit = $world->unit();

    $this->actingAs($world->user)->post('/app/products', [
        'name' => 'No Cost', 'sku' => 'NC-1', 'type' => 'standard', 'unit_id' => $unit->id,
        'sale_price' => 5000, 'cost_price' => 4000, 'track_stock' => true, 'is_active' => true,
    ])->assertRedirect();

    expect(app(TenantContext::class)->withoutTenancy(fn () => Product::query()->where('sku', 'NC-1')->firstOrFail()->cost_price->paisa))->toBe(0);
});

it('reports a duplicate SKU and invalid input as form errors', function (): void {
    $world = CatalogWorld::make();
    $unit = $world->unit();
    $world->product(['sku' => 'DUP-1']);

    $this->actingAs($world->user)->post('/app/products', [
        'name' => 'Again', 'sku' => 'DUP-1', 'type' => 'standard', 'unit_id' => $unit->id, 'sale_price' => 100,
    ])->assertSessionHasErrors('form');

    $this->actingAs($world->user)->post('/app/products', ['name' => '', 'sku' => '', 'type' => 'bogus'])
        ->assertSessionHasErrors(['name', 'sku', 'type', 'unit_id', 'sale_price']);
});

it('rejects a category from another tenant', function (): void {
    $mine = CatalogWorld::make();
    $other = CatalogWorld::make();
    $foreign = app(TenantContext::class)->withoutTenancy(fn () => Category::factory()->create(['tenant_id' => $other->tenant->id, 'name' => 'Theirs']));

    $this->actingAs($mine->user)->post('/app/products', [
        'name' => 'X', 'sku' => 'X-1', 'type' => 'standard', 'unit_id' => $mine->unit()->id, 'sale_price' => 100, 'category_id' => $foreign->id,
    ])->assertSessionHasErrors('category_id');
});

it('updates a product and toggles it inactive', function (): void {
    $world = CatalogWorld::make();
    $product = $world->product(['name' => 'Old', 'sku' => 'OLD-1']);

    $this->actingAs($world->user)->put("/app/products/{$product->uuid}", [
        'name' => 'New', 'sku' => 'OLD-1', 'type' => 'standard', 'unit_id' => $product->unit_id, 'sale_price' => 9900, 'track_stock' => true, 'is_active' => true,
    ])->assertRedirect('/app/products');
    $this->actingAs($world->user)->patch("/app/products/{$product->uuid}/active", ['is_active' => false])->assertRedirect();

    $fresh = app(TenantContext::class)->withoutTenancy(fn () => Product::query()->findOrFail($product->id));
    expect($fresh->name)->toBe('New')->and($fresh->sale_price->paisa)->toBe(9900)->and($fresh->is_active)->toBeFalse();
});

it('deletes an unused product', function (): void {
    $world = CatalogWorld::make();
    $product = $world->product();

    $this->actingAs($world->user)->delete("/app/products/{$product->uuid}")->assertRedirect('/app/products');

    expect(app(TenantContext::class)->withoutTenancy(fn () => Product::query()->whereKey($product->id)->exists()))->toBeFalse();
});

it('creates a style and its size x colour variants from the product form', function (): void {
    $world = CatalogWorld::make();
    $unit = $world->unit();
    [$size, $colour, $s, $m, $red] = app(TenantContext::class)->withoutTenancy(function () use ($world): array {
        $size = VariantOption::factory()->create(['tenant_id' => $world->tenant->id, 'name' => 'Size']);
        $colour = VariantOption::factory()->create(['tenant_id' => $world->tenant->id, 'name' => 'Colour']);
        $value = fn (VariantOption $o, string $v, string $c) => VariantOptionValue::factory()->create(['tenant_id' => $world->tenant->id, 'variant_option_id' => $o->id, 'value' => $v, 'code' => $c]);

        return [$size, $colour, $value($size, 'S', 'S'), $value($size, 'M', 'M'), $value($colour, 'Red', 'RE')];
    });

    $response = $this->actingAs($world->user)->post('/app/products', [
        'name' => 'Tee', 'sku' => 'TEE', 'type' => 'standard', 'unit_id' => $unit->id, 'sale_price' => 50000, 'cost_price' => 30000, 'track_stock' => true, 'is_active' => true,
        'variants' => [
            'option1_id' => $size->id, 'option2_id' => $colour->id,
            'rows' => [
                ['option1_value_id' => $s->id, 'option2_value_id' => $red->id, 'sale_price' => 52000],
                ['option1_value_id' => $m->id, 'option2_value_id' => $red->id],
            ],
        ],
    ]);

    $family = app(TenantContext::class)->withoutTenancy(fn () => ProductFamily::query()->where('code', 'TEE')->firstOrFail());
    $response->assertRedirect("/app/products/families/{$family->uuid}");
    $variants = app(TenantContext::class)->withoutTenancy(fn () => Product::query()->where('family_id', $family->id)->orderBy('sku')->get());
    expect($variants->pluck('sku')->all())->toBe(['TEE-M-RE', 'TEE-S-RE'])
        ->and($variants[1]->sale_price->paisa)->toBe(52000)
        ->and($variants[0]->sale_price->paisa)->toBe(50000);

    $this->actingAs($world->user)->get("/app/products/families/{$family->uuid}")
        ->assertInertia(fn (Assert $page) => $page->component('products/family')->has('variants', 2)->where('family.code', 'TEE'));
});

it('updates a variant grid and refuses another tenant\'s family', function (): void {
    $mine = CatalogWorld::make();
    $other = CatalogWorld::make();
    $family = app(TenantContext::class)->withoutTenancy(function () use ($other): ProductFamily {
        $size = VariantOption::factory()->create(['tenant_id' => $other->tenant->id, 'name' => 'Size']);

        return ProductFamily::factory()->create(['tenant_id' => $other->tenant->id, 'code' => 'THEIRS', 'name' => 'Theirs', 'unit_id' => $other->unit()->id, 'option1_id' => $size->id]);
    });

    $this->actingAs($mine->user)->get("/app/products/families/{$family->uuid}")->assertNotFound();
    $this->actingAs($mine->user)->post("/app/products/families/{$family->uuid}/variants", ['rows' => [['option1_value_id' => 1]]])->assertNotFound();
});

it('manages categories and brands with isolation', function (): void {
    $world = CatalogWorld::make();
    $other = CatalogWorld::make();

    $this->actingAs($world->user)->post('/app/products/categories', ['name' => 'Menswear'])->assertRedirect();
    $this->actingAs($world->user)->post('/app/products/brands', ['name' => 'Acme'])->assertRedirect();
    $this->actingAs($world->user)->get('/app/products/categories')
        ->assertInertia(fn (Assert $page) => $page->component('catalog/taxonomy')->where('kind', 'category')->has('rows', 1)->where('rows.0.name', 'Menswear'));
    $this->actingAs($world->user)->get('/app/products/brands')
        ->assertInertia(fn (Assert $page) => $page->where('kind', 'brand')->has('rows', 1));

    $theirs = app(TenantContext::class)->withoutTenancy(fn () => Brand::factory()->create(['tenant_id' => $other->tenant->id, 'name' => 'Theirs']));
    $this->actingAs($world->user)->put("/app/products/brands/{$theirs->id}", ['name' => 'Hijack'])->assertNotFound();
    $this->actingAs($world->user)->delete("/app/products/brands/{$theirs->id}")->assertNotFound();
});

it('forbids category changes without products.update', function (): void {
    $world = CatalogWorld::make(SystemRoles::CASHIER);
    $this->actingAs($world->user)->post('/app/products/categories', ['name' => 'Nope'])->assertForbidden();
});
