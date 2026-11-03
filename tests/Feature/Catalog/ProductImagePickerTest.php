<?php

declare(strict_types=1);

use App\Models\Product;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\CatalogWorld;

afterEach(fn () => app(TenantContext::class)->forget());

it('lists the distinct photos already on this tenant\'s products, newest first, searchable by name', function (): void {
    Storage::fake('public');
    $world = CatalogWorld::make(SystemRoles::CASHIER);
    $world->product(['name' => 'Blue Shirt', 'image_path' => 'products/blue.jpg']);
    $world->product(['name' => 'Blue Shirt XL', 'image_path' => 'products/blue.jpg']);
    $world->product(['name' => 'Red Panjabi', 'image_path' => 'products/red.jpg']);
    $world->product(['name' => 'No Photo', 'image_path' => null]);

    $this->actingAs($world->user)->getJson('/app/products/images')
        ->assertOk()
        ->assertJsonCount(2, 'images')
        ->assertJsonPath('images.0.path', 'products/red.jpg')
        ->assertJsonPath('images.0.name', 'Red Panjabi')
        ->assertJsonPath('images.0.url', Storage::disk('public')->url('products/red.jpg'))
        ->assertJsonPath('images.1.path', 'products/blue.jpg')
        ->assertJsonPath('images.1.uses', 2);

    $this->actingAs($world->user)->getJson('/app/products/images?q=panj')
        ->assertOk()
        ->assertJsonCount(1, 'images')
        ->assertJsonPath('images.0.path', 'products/red.jpg');

    $this->actingAs($world->user)->getJson('/app/products/images?q=%25')
        ->assertOk()->assertJsonCount(0, 'images');
});

it('never shows another tenant\'s photos', function (): void {
    $mine = CatalogWorld::make();
    $mine->product(['name' => 'Mine', 'image_path' => 'products/mine.jpg']);
    CatalogWorld::make()->product(['name' => 'Theirs', 'image_path' => 'products/theirs.jpg']);

    $this->actingAs($mine->user)->getJson('/app/products/images')
        ->assertOk()
        ->assertJsonCount(1, 'images')
        ->assertJsonPath('images.0.path', 'products/mine.jpg');
});

it('needs products.view and a login', function (): void {
    $world = CatalogWorld::make(null);
    $world->product(['image_path' => 'products/x.jpg']);

    $this->actingAs($world->user)->getJson('/app/products/images')->assertForbidden();
    auth()->logout();
    $this->getJson('/app/products/images')->assertUnauthorized();
});

it('copies a reused photo onto a product without copying the file, and keeps a shared file when one product moves on', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('products/shared.jpg', 'x');
    $world = CatalogWorld::make();
    $source = $world->product(['name' => 'Source', 'sku' => 'SRC-1', 'image_path' => 'products/shared.jpg']);
    $target = $world->product(['name' => 'Target', 'sku' => 'TGT-1']);
    $body = fn (Product $p): array => ['_method' => 'put', 'name' => $p->name, 'sku' => $p->sku, 'type' => 'standard', 'unit_id' => $p->unit_id, 'sale_price' => 1000];

    $this->actingAs($world->user)->post("/app/products/{$target->uuid}", [...$body($target), 'existing_image' => 'products/shared.jpg'])
        ->assertRedirect('/app/products');
    $fresh = fn (Product $p): Product => app(TenantContext::class)->withoutTenancy(fn () => Product::query()->findOrFail($p->id));
    expect($fresh($target)->image_path)->toBe('products/shared.jpg')
        ->and(Storage::disk('public')->allFiles())->toBe(['products/shared.jpg']);

    // The source drops its photo: the target still uses the file, so it stays.
    $this->actingAs($world->user)->post("/app/products/{$source->uuid}", [...$body($source), 'remove_image' => true])
        ->assertRedirect('/app/products');
    expect($fresh($source)->image_path)->toBeNull();
    Storage::disk('public')->assertExists('products/shared.jpg');

    // The last user drops it: now the file goes.
    $this->actingAs($world->user)->post("/app/products/{$target->uuid}", [...$body($target), 'remove_image' => true])
        ->assertRedirect('/app/products');
    Storage::disk('public')->assertMissing('products/shared.jpg');
});

it('refuses to reuse a path that is not one of this tenant\'s photos', function (): void {
    Storage::fake('public');
    $mine = CatalogWorld::make();
    $target = $mine->product(['sku' => 'TGT-2']);
    CatalogWorld::make()->product(['image_path' => 'products/theirs.jpg']);
    $base = ['_method' => 'put', 'name' => 'T', 'sku' => 'TGT-2', 'type' => 'standard', 'unit_id' => $target->unit_id, 'sale_price' => 1000];

    $this->actingAs($mine->user)->post("/app/products/{$target->uuid}", [...$base, 'existing_image' => 'products/theirs.jpg'])
        ->assertSessionHasErrors('existing_image');
    $this->actingAs($mine->user)->post("/app/products/{$target->uuid}", [...$base, 'existing_image' => '../../.env'])
        ->assertSessionHasErrors('existing_image');

    expect(app(TenantContext::class)->withoutTenancy(fn () => Product::query()->findOrFail($target->id))->image_path)->toBeNull();
});
