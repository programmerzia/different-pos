<?php

declare(strict_types=1);

use App\Actions\Catalog\DeleteBrand;
use App\Actions\Catalog\DeleteCategory;
use App\Actions\Catalog\SaveBrand;
use App\Actions\Catalog\SaveCategory;
use App\Actions\Catalog\SaveTaxRate;
use App\Actions\Catalog\SaveUnit;
use App\Enums\DeleteOutcome;
use App\Exceptions\Domain\InvalidCatalogData;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Fixtures\StockWorld;

beforeEach(function (): void {
    $this->world = StockWorld::make();
    $this->user = $this->world->user;
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('creates a category and dedupes by name case-insensitively', function (): void {
    $first = app(SaveCategory::class)->handle($this->user, ['name' => 'Shirts']);
    $again = app(SaveCategory::class)->handle($this->user, ['name' => '  shirts ', 'name_bn' => 'শার্ট']);

    expect($again->id)->toBe($first->id)
        ->and($again->name_bn)->toBe('শার্ট')
        ->and(Category::query()->count())->toBe(1);
});

it('refuses to rename a category onto another one under the same parent', function (): void {
    app(SaveCategory::class)->handle($this->user, ['name' => 'Shirts']);
    $pants = app(SaveCategory::class)->handle($this->user, ['name' => 'Pants']);

    app(SaveCategory::class)->handle($this->user, ['name' => 'SHIRTS'], $pants);
})->throws(InvalidCatalogData::class);

it('allows the same name under different parents but limits nesting to two levels', function (): void {
    $men = app(SaveCategory::class)->handle($this->user, ['name' => 'Men']);
    $women = app(SaveCategory::class)->handle($this->user, ['name' => 'Women']);
    $menOther = app(SaveCategory::class)->handle($this->user, ['name' => 'Other', 'parent_id' => $men->id]);
    $womenOther = app(SaveCategory::class)->handle($this->user, ['name' => 'Other', 'parent_id' => $women->id]);

    expect($menOther->id)->not->toBe($womenOther->id);
    expect(fn () => app(SaveCategory::class)->handle($this->user, ['name' => 'Deep', 'parent_id' => $menOther->id]))
        ->toThrow(InvalidCatalogData::class);
    expect(fn () => app(SaveCategory::class)->handle($this->user, ['parent_id' => $women->id], $men))
        ->toThrow(InvalidCatalogData::class);
});

it('requires a name', function (): void {
    app(SaveBrand::class)->handle($this->user, ['name' => '   ']);
})->throws(InvalidCatalogData::class);

it('deletes an unreferenced category and only deactivates a referenced one', function (): void {
    $free = app(SaveCategory::class)->handle($this->user, ['name' => 'Free']);
    $used = app(SaveCategory::class)->handle($this->user, ['name' => 'Used']);
    $this->world->product(['category_id' => $used->id]);
    $parent = app(SaveCategory::class)->handle($this->user, ['name' => 'Parent']);
    app(SaveCategory::class)->handle($this->user, ['name' => 'Child', 'parent_id' => $parent->id]);

    expect(app(DeleteCategory::class)->handle($this->user, $free))->toBe(DeleteOutcome::Deleted)
        ->and(Category::query()->find($free->id))->toBeNull()
        ->and(app(DeleteCategory::class)->handle($this->user, $used))->toBe(DeleteOutcome::Deactivated)
        ->and($used->refresh()->is_active)->toBeFalse()
        ->and(app(DeleteCategory::class)->handle($this->user, $parent))->toBe(DeleteOutcome::Deactivated);
});

it('restores a soft-deleted category when its name is saved again', function (): void {
    $cat = app(SaveCategory::class)->handle($this->user, ['name' => 'Seasonal']);
    app(DeleteCategory::class)->handle($this->user, $cat);

    $back = app(SaveCategory::class)->handle($this->user, ['name' => 'seasonal']);

    expect($back->id)->toBe($cat->id)->and($back->trashed())->toBeFalse();
});

it('creates, dedupes, deletes and deactivates brands', function (): void {
    $brand = app(SaveBrand::class)->handle($this->user, ['name' => 'Acme']);
    $same = app(SaveBrand::class)->handle($this->user, ['name' => 'ACME']);
    $this->world->product(['brand_id' => $brand->id]);

    expect($same->id)->toBe($brand->id)
        ->and(app(DeleteBrand::class)->handle($this->user, $brand))->toBe(DeleteOutcome::Deactivated)
        ->and($brand->refresh()->is_active)->toBeFalse();

    $free = app(SaveBrand::class)->handle($this->user, ['name' => 'Free']);
    expect(app(DeleteBrand::class)->handle($this->user, $free))->toBe(DeleteOutcome::Deleted);
});

it('saves units and dedupes on name or short name', function (): void {
    $unit = app(SaveUnit::class)->handle($this->user, ['name' => 'Piece', 'short_name' => 'pc']);
    $again = app(SaveUnit::class)->handle($this->user, ['name' => 'Pieces', 'short_name' => 'PC', 'allows_decimal' => false]);
    $kg = app(SaveUnit::class)->handle($this->user, ['name' => 'Kilogram', 'short_name' => 'kg', 'allows_decimal' => true]);

    expect($again->id)->toBe($unit->id)->and($kg->allows_decimal)->toBeTrue();
    expect(fn () => app(SaveUnit::class)->handle($this->user, ['name' => 'Pieces'], $kg))->toThrow(InvalidCatalogData::class);
});

it('saves tax rates in basis points with a single default', function (): void {
    $std = app(SaveTaxRate::class)->handle($this->user, ['name' => 'VAT 15%', 'rate_bp' => 1500, 'is_default' => true]);
    $zero = app(SaveTaxRate::class)->handle($this->user, ['name' => 'Zero', 'rate_bp' => 0, 'is_default' => true]);

    expect($std->refresh()->is_default)->toBeFalse()
        ->and($zero->is_default)->toBeTrue()
        ->and(TaxRate::query()->where('is_default', true)->count())->toBe(1);
    expect(fn () => app(SaveTaxRate::class)->handle($this->user, ['name' => 'Bad', 'rate_bp' => 10001]))->toThrow(InvalidCatalogData::class);
    expect(fn () => app(SaveTaxRate::class)->handle($this->user, ['name' => 'No rate']))->toThrow(InvalidCatalogData::class);
});

it('keeps catalog masters isolated per tenant', function (): void {
    [$foreignCategory, $foreignBrand, $foreignUnit] = app(TenantContext::class)->withoutTenancy(function (): array {
        $tenant = Tenant::factory()->create();

        return [
            Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Shirts']),
            Brand::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Acme']),
            Unit::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Piece', 'short_name' => 'pc']),
        ];
    });

    // Same names in this tenant create new rows instead of touching the foreign ones.
    $category = app(SaveCategory::class)->handle($this->user, ['name' => 'Shirts']);
    $brand = app(SaveBrand::class)->handle($this->user, ['name' => 'Acme']);
    $unit = app(SaveUnit::class)->handle($this->user, ['name' => 'Piece', 'short_name' => 'pc']);

    expect($category->id)->not->toBe($foreignCategory->id)
        ->and($brand->id)->not->toBe($foreignBrand->id)
        ->and($unit->id)->not->toBe($foreignUnit->id);

    // A foreign parent, row or product reference is a 404.
    expect(fn () => app(SaveCategory::class)->handle($this->user, ['name' => 'Kid', 'parent_id' => $foreignCategory->id]))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(DeleteBrand::class)->handle($this->user, $foreignBrand))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(SaveUnit::class)->handle($this->user, ['name' => 'X'], $foreignUnit))->toThrow(ModelNotFoundException::class);
    expect(app(TenantContext::class)->withoutTenancy(fn (): int => Product::query()->count()))->toBe(0);
});
