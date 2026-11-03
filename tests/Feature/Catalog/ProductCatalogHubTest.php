<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Support\Money\Money;
use App\Support\Permissions\SystemRoles;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\CatalogWorld;

afterEach(fn () => app(TenantContext::class)->forget());

/** A second active branch of the world's tenant, assigned to its user. */
function secondBranch(CatalogWorld $world): Branch
{
    $branch = app(TenantContext::class)->withoutTenancy(fn () => Branch::factory()->for($world->tenant)->create(['name' => 'Branch 2', 'is_active' => true]));
    $world->user->branches()->attach($branch);

    return $branch;
}

function onHandIn(CatalogWorld $world, Branch $branch, Product $product, int $units): void
{
    app(TenantContext::class)->withoutTenancy(fn () => BranchStock::factory()->create([
        'tenant_id' => $world->tenant->id, 'branch_id' => $branch->id, 'product_id' => $product->id, 'qty_on_hand' => Quantity::ofUnits($units),
    ]));
}

/** 1×1 transparent PNG: real image bytes without needing ext-gd. */
function tinyPng(string $name = 'photo.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', true));
}

it('gives the owner one stock column per active branch, a total, and totals over the whole filtered list', function (): void {
    $world = CatalogWorld::make();
    $second = secondBranch($world);
    $shirt = $world->product(['name' => 'Shirt', 'sku' => 'A-1', 'sale_price' => Money::ofPaisa(100000), 'cost_price' => Money::ofPaisa(60000)]);
    $pant = $world->product(['name' => 'Pant', 'sku' => 'B-1', 'sale_price' => Money::ofPaisa(50000), 'cost_price' => Money::ofPaisa(30000)]);
    $service = $world->product(['name' => 'Tailoring', 'sku' => 'C-1', 'track_stock' => false, 'sale_price' => Money::ofPaisa(2000)]);
    onHandIn($world, $world->branch, $shirt, 3);
    onHandIn($world, $second, $shirt, 12);
    onHandIn($world, $second, $pant, 4);

    $this->actingAs($world->user)->withSession(['branch_id' => $world->branch->id])->get('/app/products?per_page=10&sort=sku')
        ->assertInertia(fn (Assert $page) => $page->component('products/index')
            ->has('branches', 2)
            ->where('branches.0.id', $world->branch->id)->where('branches.0.current', true)
            ->where('branches.1.name', 'Branch 2')->where('branches.1.current', false)
            ->where('rows.0.sku', 'A-1')->where('rows.0.stock', [3000, 12000])->where('rows.0.total_stock', 15000)->where('rows.0.on_hand', 3000)
            ->where('rows.1.stock', [0, 4000])->where('rows.1.total_stock', 4000)
            ->where('rows.2.stock', null)->where('rows.2.total_stock', null)->where('rows.2.track_stock', false)
            ->where('totals.sale_price', 152000)->where('totals.cost_price', 90000)
            ->where('totals.stock', [3000, 16000])->where('totals.total_stock', 19000)
            ->where('editor', null)->where('form', null));

    // Totals follow the filters, not the page.
    $this->actingAs($world->user)->withSession(['branch_id' => $world->branch->id])->get('/app/products?q=pant&per_page=10')
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('totals.sale_price', 50000)->where('totals.stock', [0, 4000]));
    expect($service->track_stock)->toBeFalse();
});

it('shows a manager only the current branch column and hides cost totals without products.view_cost', function (): void {
    $world = CatalogWorld::make(SystemRoles::MANAGER);
    $second = secondBranch($world);
    $item = $world->product(['name' => 'Kurti']);
    onHandIn($world, $world->branch, $item, 2);
    onHandIn($world, $second, $item, 9);

    $this->actingAs($world->user)->withSession(['branch_id' => $world->branch->id])->get('/app/products')
        ->assertInertia(fn (Assert $page) => $page->has('branches', 1)->where('branches.0.id', $world->branch->id)
            ->where('rows.0.stock', [2000])->where('totals.stock', [2000]));

    $cashier = CatalogWorld::make(SystemRoles::CASHIER);
    $cashier->product();
    $this->actingAs($cashier->user)->get('/app/products')
        ->assertInertia(fn (Assert $page) => $page->where('totals.cost_price', null)->where('rows.0.cost_price', null));
});

it('filters by stock level, inactive status and date added', function (): void {
    $world = CatalogWorld::make();
    $out = $world->product(['name' => 'Out One']);
    $low = $world->product(['name' => 'Low One', 'reorder_level' => Quantity::ofUnits(5)]);
    $fine = $world->product(['name' => 'Fine One', 'reorder_level' => Quantity::ofUnits(5)]);
    $old = $world->product(['name' => 'Old Inactive', 'is_active' => false]);
    onHandIn($world, $world->branch, $low, 2);
    onHandIn($world, $world->branch, $fine, 20);
    app(TenantContext::class)->withoutTenancy(fn () => Product::query()->whereKey($old->id)->update(['created_at' => now()->subDays(60)]));

    $names = fn (string $query): array => $this->actingAs($world->user)->get("/app/products?{$query}")->viewData('page')['props']['rows'];

    expect(array_column($names('filter[stock]=out'), 'name'))->toEqualCanonicalizing(['Out One', 'Old Inactive'])
        ->and(array_column($names('filter[stock]=low'), 'name'))->toBe(['Low One'])
        ->and(array_column($names('filter[stock]=in'), 'name'))->toBe(['Fine One'])
        ->and(array_column($names('filter[status]=inactive'), 'name'))->toBe(['Old Inactive'])
        ->and(array_column($names('filter[added]=30d'), 'name'))->not->toContain('Old Inactive')
        ->and(array_column($names('filter[added]=90d'), 'name'))->toContain('Old Inactive');
    expect($out->exists)->toBeTrue();
});

it('renders create and edit as the list with the product dialog open', function (): void {
    $world = CatalogWorld::make();
    $world->unit();
    $product = $world->product(['name' => 'Editable', 'sku' => 'ED-1']);

    $this->actingAs($world->user)->get('/app/products/create?q=edit')
        ->assertInertia(fn (Assert $page) => $page->component('products/index')
            ->has('rows', 1)
            ->where('editor.product', null)
            ->has('form.units')->has('form.categories')->has('form.taxRates')->has('form.vendors')->has('form.variantOptions'));

    $this->actingAs($world->user)->get("/app/products/{$product->uuid}/edit")
        ->assertInertia(fn (Assert $page) => $page->component('products/index')
            ->where('editor.product.uuid', $product->uuid)->where('editor.product.sku', 'ED-1')
            ->has('form.units')->has('rows', 1));
});

it('keeps the list query when it redirects back after a save or delete', function (): void {
    $world = CatalogWorld::make();
    $product = $world->product(['name' => 'Old', 'sku' => 'Q-1']);

    $this->actingAs($world->user)->put("/app/products/{$product->uuid}?q=old&filter[category]=3&page=2&evil=1", [
        'name' => 'New', 'sku' => 'Q-1', 'type' => 'standard', 'unit_id' => $product->unit_id, 'sale_price' => 100, 'track_stock' => true, 'is_active' => true,
    ])->assertRedirect('/app/products?'.http_build_query(['q' => 'old', 'filter' => ['category' => '3'], 'page' => '2']));

    $this->actingAs($world->user)->delete("/app/products/{$product->uuid}?q=new")->assertRedirect('/app/products?q=new');
});

it('stores an uploaded product photo on the public disk and replaces it', function (): void {
    Storage::fake('public');
    $world = CatalogWorld::make();
    $unit = $world->unit();

    $this->actingAs($world->user)->post('/app/products', [
        'name' => 'Photo Shirt', 'sku' => 'PH-1', 'type' => 'standard', 'unit_id' => $unit->id, 'sale_price' => 1000, 'image' => tinyPng(),
    ])->assertRedirect('/app/products');

    $product = app(TenantContext::class)->withoutTenancy(fn () => Product::query()->where('sku', 'PH-1')->firstOrFail());
    expect($product->image_path)->toStartWith('products/');
    Storage::disk('public')->assertExists((string) $product->image_path);

    $this->actingAs($world->user)->get('/app/products')
        ->assertInertia(fn (Assert $page) => $page->where('rows.0.image_url', Storage::disk('public')->url((string) $product->image_path)));

    $first = (string) $product->image_path;
    $this->actingAs($world->user)->post("/app/products/{$product->uuid}", [
        '_method' => 'put', 'name' => 'Photo Shirt', 'sku' => 'PH-1', 'type' => 'standard', 'unit_id' => $unit->id, 'sale_price' => 1000, 'image' => tinyPng('second.png'),
    ])->assertRedirect('/app/products');

    $fresh = app(TenantContext::class)->withoutTenancy(fn () => Product::query()->findOrFail($product->id));
    expect($fresh->image_path)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists((string) $fresh->image_path);
});

it('rejects a photo that is not an image or is too large', function (): void {
    Storage::fake('public');
    $world = CatalogWorld::make();
    $unit = $world->unit();
    $base = ['name' => 'Bad Photo', 'sku' => 'BP-1', 'type' => 'standard', 'unit_id' => $unit->id, 'sale_price' => 1000];

    $this->actingAs($world->user)->post('/app/products', [...$base, 'image' => UploadedFile::fake()->createWithContent('notes.pdf', '%PDF-1.4 fake')])
        ->assertSessionHasErrors('image');
    $this->actingAs($world->user)->post('/app/products', [...$base, 'image' => UploadedFile::fake()->create('huge.png', 5000, 'image/png')])
        ->assertSessionHasErrors('image');

    expect(app(TenantContext::class)->withoutTenancy(fn () => Product::query()->where('sku', 'BP-1')->exists()))->toBeFalse()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('never lets a photo be uploaded onto another tenant\'s product', function (): void {
    Storage::fake('public');
    $mine = CatalogWorld::make();
    $theirs = CatalogWorld::make()->product(['sku' => 'THEIRS']);

    $this->actingAs($mine->user)->post("/app/products/{$theirs->uuid}", [
        '_method' => 'put', 'name' => 'Hijack', 'sku' => 'THEIRS', 'type' => 'standard', 'unit_id' => $theirs->unit_id, 'sale_price' => 1, 'image' => tinyPng(),
    ])->assertNotFound();
    $this->actingAs($mine->user)->get("/app/products/{$theirs->uuid}/edit")->assertNotFound();

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('never counts another tenant\'s stock in the branch columns or totals', function (): void {
    $mine = CatalogWorld::make();
    $other = CatalogWorld::make();
    $item = $mine->product(['name' => 'Mine']);
    onHandIn($mine, $mine->branch, $item, 1);
    $foreign = $other->product();
    onHandIn($other, $other->branch, $foreign, 50);

    $this->actingAs($mine->user)->get('/app/products')
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1)->has('branches', 1)->where('totals.stock', [1000])->where('totals.total_stock', 1000));
});
