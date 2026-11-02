<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Exceptions\Domain\InvalidCatalogData;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductFamily;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\VariantOption;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/**
 * Creates or updates a product family (style). The code is unique per tenant. At most two options;
 * once the family has variants its options are frozen.
 */
final readonly class SaveProductFamily
{
    /**
     * @param  array{code?: string, name?: string, name_bn?: string|null, category_id?: int|null, brand_id?: int|null, unit_id?: int, tax_rate_id?: int|null, option1_id?: int|null, option2_id?: int|null, description?: string|null, image_path?: string|null, is_active?: bool}  $data
     */
    public function handle(User $actor, array $data, ?ProductFamily $family = null): ProductFamily
    {
        return Transaction::run(function () use ($actor, $data, $family): ProductFamily {
            $family = $family instanceof ProductFamily ? ProductFamily::query()->findOrFail($family->id) : null;
            $created = ! $family instanceof ProductFamily;
            $family ??= new ProductFamily;

            $code = strtoupper(trim((string) ($data['code'] ?? ($created ? '' : $family->code))));
            $name = trim((string) ($data['name'] ?? ($created ? '' : $family->name)));
            if ($code === '') {
                throw InvalidCatalogData::required('Code');
            }
            if ($name === '') {
                throw InvalidCatalogData::required('Name');
            }
            if (preg_match('/^[A-Z0-9][A-Z0-9_.-]{0,39}$/', $code) !== 1) {
                throw InvalidCatalogData::invalid('A family code uses letters, digits, "_", "." or "-".');
            }
            $taken = ProductFamily::withTrashed()->where('code', $code)->when(! $created, fn ($q) => $q->whereKeyNot($family->id))->exists();
            if ($taken) {
                throw InvalidCatalogData::duplicate('family code', $code);
            }

            $attributes = ['code' => $code, 'name' => $name];
            foreach (['name_bn', 'description', 'image_path', 'is_active'] as $key) {
                if (array_key_exists($key, $data)) {
                    $attributes[$key] = $data[$key];
                }
            }
            $models = ['category_id' => Category::class, 'brand_id' => Brand::class, 'unit_id' => Unit::class, 'tax_rate_id' => TaxRate::class, 'option1_id' => VariantOption::class, 'option2_id' => VariantOption::class];
            foreach ($models as $key => $model) {
                if (array_key_exists($key, $data)) {
                    if ($data[$key] !== null) {
                        $model::query()->findOrFail($data[$key]);
                    }
                    $attributes[$key] = $data[$key];
                }
            }
            if ($created && ! isset($attributes['unit_id'])) {
                throw InvalidCatalogData::required('Unit');
            }

            $option1 = array_key_exists('option1_id', $attributes) ? $attributes['option1_id'] : $family->option1_id;
            $option2 = array_key_exists('option2_id', $attributes) ? $attributes['option2_id'] : $family->option2_id;
            if ($option2 !== null && $option1 === null) {
                throw InvalidCatalogData::invalid('Set the first option before the second.');
            }
            if ($option1 !== null && $option1 === $option2) {
                throw InvalidCatalogData::invalid('A family cannot use the same option twice.');
            }
            if (! $created && ($option1 !== $family->option1_id || $option2 !== $family->option2_id) && $family->products()->withTrashed()->exists()) {
                throw InvalidCatalogData::invalid('The options of a family that has variants cannot change.');
            }

            $family->fill($attributes);
            if ($created) {
                $family->created_by = $actor->id;
            }
            $family->updated_by = $actor->id;
            $family->save();

            Audit::log($created ? 'product_family.created' : 'product_family.updated', $family, ['code' => $code], $actor);

            return $family;
        });
    }
}
