<?php

declare(strict_types=1);

use App\Actions\Catalog\AttachProductToFamily;
use App\Actions\Catalog\DetachProductFromFamily;
use App\Actions\Catalog\GenerateFamilyVariants;
use App\Actions\Catalog\SaveProductFamily;
use App\Actions\Catalog\SaveVariantOption;
use App\Exceptions\Domain\InvalidCatalogData;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\VariantOption;
use App\Models\VariantOptionValue;
use App\Support\Catalog\Ean13;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Fixtures\StockWorld;

beforeEach(function (): void {
    $this->world = StockWorld::make();
    $this->user = $this->world->user;
    $this->unit = app(TenantContext::class)->withoutTenancy(fn (): Unit => Unit::factory()->create(['tenant_id' => $this->world->tenant->id]));

    $this->size = app(SaveVariantOption::class)->handle($this->user, ['name' => 'Size', 'values' => [
        ['value' => 'XS'], ['value' => 'M'], ['value' => 'L'], ['value' => '3XL'],
    ]]);
    $this->colour = app(SaveVariantOption::class)->handle($this->user, ['name' => 'Colour', 'values' => [
        ['value' => 'Black'], ['value' => 'Blue'], ['value' => 'Red'],
    ]]);
    $this->ids = fn (VariantOption $option, array $values): array => $option->values->whereIn('value', $values)->pluck('id')->all();
    $this->family = app(SaveProductFamily::class)->handle($this->user, [
        'code' => 'polo', 'name' => 'Polo', 'unit_id' => $this->unit->id, 'option1_id' => $this->size->id, 'option2_id' => $this->colour->id,
    ]);
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('derives value codes: full text for Size, two letters (then three) for other options', function (): void {
    $codes = fn (VariantOption $o): array => $o->values->pluck('code', 'value')->all();

    expect($codes($this->size))->toEqual(['XS' => 'XS', 'M' => 'M', 'L' => 'L', '3XL' => '3XL'])
        ->and($codes($this->colour))->toEqual(['Black' => 'BL', 'Blue' => 'BLU', 'Red' => 'RE']);
});

it('upserts option values without duplicating and checks codes', function (): void {
    $again = app(SaveVariantOption::class)->handle($this->user, ['name' => 'size', 'values' => [['value' => 'm', 'value_bn' => 'এম'], ['value' => 'XL']]], $this->size);

    expect($again->values)->toHaveCount(5)
        ->and($again->values->firstWhere('value', 'M')?->value_bn)->toBe('এম');
    expect(fn () => app(SaveVariantOption::class)->handle($this->user, ['name' => 'Size', 'values' => [['value' => 'Huge', 'code' => 'xl']]]))
        ->toThrow(InvalidCatalogData::class, 'code');
    expect(fn () => app(SaveVariantOption::class)->handle($this->user, ['name' => 'Colour'], $this->size))->toThrow(InvalidCatalogData::class);
});

it('generates the size x colour matrix with SKUs, auto barcodes and default prices', function (): void {
    $rows = GenerateFamilyVariants::matrix(($this->ids)($this->size, ['M', 'L']), ($this->ids)($this->colour, ['Black', 'Red']));
    $products = app(GenerateFamilyVariants::class)->handle($this->user, $this->family, $rows, ['sale_price' => '1,200', 'cost_price' => '700']);

    expect($products)->toHaveCount(4)
        ->and($products->pluck('sku')->sort()->values()->all())->toBe(['POLO-L-BL', 'POLO-L-RE', 'POLO-M-BL', 'POLO-M-RE'])
        ->and($products->every(fn (Product $p): bool => Ean13::isValid((string) $p->barcode)))->toBeTrue()
        ->and($products->pluck('barcode')->unique())->toHaveCount(4)
        ->and($products->first()?->sale_price->paisa)->toBe(120000)
        ->and($products->first()?->name)->toBe('Polo')
        ->and($products->first()?->unit_id)->toBe($this->unit->id)
        ->and($products->firstWhere('sku', 'POLO-M-RE')?->variant_label)->toBe('M / Red')
        ->and($products->first()?->variant_key)->toMatch('/^\d+-\d+$/');
});

it('is idempotent and applies per-row overrides to existing combinations only', function (): void {
    $m = ($this->ids)($this->size, ['M'])[0];
    $black = ($this->ids)($this->colour, ['Black'])[0];
    $red = ($this->ids)($this->colour, ['Red'])[0];
    $generate = app(GenerateFamilyVariants::class);

    $first = $generate->handle($this->user, $this->family, GenerateFamilyVariants::matrix([$m], [$black]), ['sale_price' => '1000']);
    $barcode = $first->first()?->barcode;
    $again = $generate->handle($this->user, $this->family, [
        ['option1_value_id' => $m, 'option2_value_id' => $black, 'sale_price' => Money::ofPaisa(110000)],
        ['option1_value_id' => $m, 'option2_value_id' => $red], // new: price inherited from the first variant
    ]);

    expect(Product::query()->where('family_id', $this->family->id)->count())->toBe(2)
        ->and($again->first()?->id)->toBe($first->first()?->id)
        ->and($again->first()?->sale_price->paisa)->toBe(110000)
        ->and($again->first()?->barcode)->toBe($barcode)
        ->and($again->last()?->sale_price->paisa)->toBe(100000);
});

it('restores a soft-deleted combination instead of duplicating it', function (): void {
    $rows = GenerateFamilyVariants::matrix(($this->ids)($this->size, ['M']), ($this->ids)($this->colour, ['Black']));
    $first = app(GenerateFamilyVariants::class)->handle($this->user, $this->family, $rows, ['sale_price' => '10'])->first();
    $first?->delete();

    $again = app(GenerateFamilyVariants::class)->handle($this->user, $this->family, $rows)->first();

    expect($again?->id)->toBe($first?->id)->and($again?->trashed())->toBeFalse();
});

it('refuses values from the wrong option, a missing second value and a missing price', function (): void {
    $m = ($this->ids)($this->size, ['M'])[0];
    $black = ($this->ids)($this->colour, ['Black'])[0];
    $generate = app(GenerateFamilyVariants::class);

    expect(fn () => $generate->handle($this->user, $this->family, [['option1_value_id' => $black, 'option2_value_id' => $m]], ['sale_price' => '1']))
        ->toThrow(InvalidCatalogData::class, 'belong');
    expect(fn () => $generate->handle($this->user, $this->family, [['option1_value_id' => $m]], ['sale_price' => '1']))
        ->toThrow(InvalidCatalogData::class);
    expect(fn () => $generate->handle($this->user, $this->family, GenerateFamilyVariants::matrix([$m], [$black])))
        ->toThrow(InvalidCatalogData::class, 'Sale price');
    expect(fn () => $generate->handle($this->user, $this->family, []))->toThrow(InvalidCatalogData::class);
    expect(Product::query()->count())->toBe(0);
});

it('fails on an SKU clash and rolls the whole batch back', function (): void {
    $m = ($this->ids)($this->size, ['M'])[0];
    $l = ($this->ids)($this->size, ['L'])[0];
    $black = ($this->ids)($this->colour, ['Black'])[0];
    app(TenantContext::class)->withoutTenancy(fn () => Product::factory()->create(['tenant_id' => $this->world->tenant->id, 'sku' => 'POLO-L-BL']));

    expect(fn () => app(GenerateFamilyVariants::class)->handle($this->user, $this->family, GenerateFamilyVariants::matrix([$m, $l], [$black]), ['sale_price' => '5']))
        ->toThrow(InvalidCatalogData::class, 'SKU');
    expect(Product::query()->where('family_id', $this->family->id)->count())->toBe(0);
});

it('supports one-option families', function (): void {
    $family = app(SaveProductFamily::class)->handle($this->user, ['code' => 'SOCK', 'name' => 'Sock', 'unit_id' => $this->unit->id, 'option1_id' => $this->size->id]);
    $products = app(GenerateFamilyVariants::class)->handle($this->user, $family, GenerateFamilyVariants::matrix(($this->ids)($this->size, ['M', 'L'])), ['sale_price' => '99']);

    expect($products->pluck('sku')->sort()->values()->all())->toBe(['SOCK-L', 'SOCK-M']);
    expect(fn () => app(GenerateFamilyVariants::class)->handle($this->user, $family, [['option1_value_id' => ($this->ids)($this->size, ['M'])[0], 'option2_value_id' => ($this->ids)($this->colour, ['Red'])[0]]]))
        ->toThrow(InvalidCatalogData::class);
});

it('validates family code, options and freezes options once variants exist', function (): void {
    $save = app(SaveProductFamily::class);

    expect(fn () => $save->handle($this->user, ['code' => 'POLO', 'name' => 'Dup', 'unit_id' => $this->unit->id]))->toThrow(InvalidCatalogData::class, 'family code');
    expect(fn () => $save->handle($this->user, ['code' => 'A B', 'name' => 'Bad', 'unit_id' => $this->unit->id]))->toThrow(InvalidCatalogData::class);
    expect(fn () => $save->handle($this->user, ['code' => 'X', 'name' => 'X', 'unit_id' => $this->unit->id, 'option1_id' => $this->size->id, 'option2_id' => $this->size->id]))->toThrow(InvalidCatalogData::class);
    expect(fn () => $save->handle($this->user, ['code' => 'Y', 'name' => 'Y', 'unit_id' => $this->unit->id, 'option2_id' => $this->size->id]))->toThrow(InvalidCatalogData::class);
    expect(fn () => $save->handle($this->user, ['code' => 'Z', 'name' => 'Z']))->toThrow(InvalidCatalogData::class, 'Unit');

    app(GenerateFamilyVariants::class)->handle($this->user, $this->family, GenerateFamilyVariants::matrix(($this->ids)($this->size, ['M']), ($this->ids)($this->colour, ['Red'])), ['sale_price' => '1']);

    expect(fn () => $save->handle($this->user, ['option2_id' => null], $this->family))->toThrow(InvalidCatalogData::class, 'cannot change');
    expect($save->handle($this->user, ['name' => 'Polo shirt'], $this->family)->name)->toBe('Polo shirt');
});

it('attaches a standalone product to a family and detaches it again', function (): void {
    $product = app(TenantContext::class)->withoutTenancy(fn (): Product => Product::factory()->create(['tenant_id' => $this->world->tenant->id]));
    $m = ($this->ids)($this->size, ['M'])[0];
    $red = ($this->ids)($this->colour, ['Red'])[0];
    $attach = app(AttachProductToFamily::class);

    $attached = $attach->handle($this->user, $this->family, $product, $m, $red);
    expect($attached->family_id)->toBe($this->family->id)->and($attached->variant_label)->toBe('M / Red');

    $other = app(TenantContext::class)->withoutTenancy(fn (): Product => Product::factory()->create(['tenant_id' => $this->world->tenant->id]));
    expect(fn () => $attach->handle($this->user, $this->family, $other, $m, $red))->toThrow(InvalidCatalogData::class, 'combination');
    expect(fn () => $attach->handle($this->user, $this->family, $attached, $m, $red))->toThrow(InvalidCatalogData::class, 'already');
    expect(fn () => $attach->handle($this->user, $this->family, $other, $m))->toThrow(InvalidCatalogData::class, 'one value per option');

    $detached = app(DetachProductFromFamily::class)->handle($this->user, $attached);
    expect($detached->family_id)->toBeNull()->and($detached->variant_label)->toBeNull();
    expect(fn () => app(DetachProductFromFamily::class)->handle($this->user, $detached))->toThrow(InvalidCatalogData::class);
    expect($attach->handle($this->user, $this->family, $other, $m, $red)->family_id)->toBe($this->family->id);
});

it('isolates families, options and values per tenant', function (): void {
    [$foreignFamily, $foreignValue, $foreignProduct] = app(TenantContext::class)->withoutTenancy(function (): array {
        $tenant = Tenant::factory()->create();
        $option = VariantOption::factory()->create(['tenant_id' => $tenant->id]);

        return [
            ProductFamily::factory()->create(['tenant_id' => $tenant->id, 'code' => 'POLO']),
            VariantOptionValue::factory()->create(['tenant_id' => $tenant->id, 'variant_option_id' => $option->id]),
            Product::factory()->create(['tenant_id' => $tenant->id]),
        ];
    });
    $m = ($this->ids)($this->size, ['M'])[0];
    $red = ($this->ids)($this->colour, ['Red'])[0];

    expect(fn () => app(GenerateFamilyVariants::class)->handle($this->user, $foreignFamily, [['option1_value_id' => $m]], ['sale_price' => '1']))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(GenerateFamilyVariants::class)->handle($this->user, $this->family, [['option1_value_id' => $foreignValue->id, 'option2_value_id' => $red]], ['sale_price' => '1']))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(AttachProductToFamily::class)->handle($this->user, $this->family, $foreignProduct, $m, $red))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(SaveProductFamily::class)->handle($this->user, ['name' => 'Hijack'], $foreignFamily))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(SaveProductFamily::class)->handle($this->user, ['code' => 'NEW', 'name' => 'N', 'unit_id' => $this->unit->id, 'option1_id' => VariantOption::withoutGlobalScopes()->where('tenant_id', '!=', $this->world->tenant->id)->value('id')]))->toThrow(ModelNotFoundException::class);
});
