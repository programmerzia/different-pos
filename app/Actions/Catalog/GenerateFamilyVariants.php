<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Exceptions\Domain\InvalidCatalogData;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\User;
use App\Models\VariantOptionValue;
use App\Support\Audit\Audit;
use App\Support\Catalog\BarcodeAllocator;
use App\Support\Catalog\Values;
use App\Support\Database\Transaction;
use App\Support\Money\Money;
use Illuminate\Support\Collection;

/**
 * Creates the missing size x colour products of a family (idempotent: an existing combination is
 * updated, never duplicated). SKU = {family code}-{value1 code}-{value2 code}; blank barcodes are
 * auto EAN-13. A new variant takes its prices from the row, else $defaults, else the family's first
 * existing variant. An existing variant changes only the fields its row sets.
 *
 * @phpstan-type Row array{option1_value_id: int, option2_value_id?: int|null, sale_price?: Money|string, cost_price?: Money|string, sku?: string, barcode?: string}
 */
final readonly class GenerateFamilyVariants
{
    public function __construct(private BarcodeAllocator $barcodes) {}

    /**
     * The full grid of the selected values, as rows for handle().
     *
     * @param  list<int>  $option1ValueIds
     * @param  list<int>  $option2ValueIds
     * @return list<Row>
     */
    public static function matrix(array $option1ValueIds, array $option2ValueIds = []): array
    {
        $rows = [];
        foreach (array_values(array_unique($option1ValueIds)) as $v1) {
            if ($option2ValueIds === []) {
                $rows[] = ['option1_value_id' => $v1];

                continue;
            }
            foreach (array_values(array_unique($option2ValueIds)) as $v2) {
                $rows[] = ['option1_value_id' => $v1, 'option2_value_id' => $v2];
            }
        }

        return $rows;
    }

    /**
     * @param  list<Row>  $rows
     * @param  array{sale_price?: Money|string, cost_price?: Money|string}  $defaults
     * @return Collection<int, Product>
     */
    public function handle(User $actor, ProductFamily $family, array $rows, array $defaults = []): Collection
    {
        return Transaction::run(function () use ($actor, $family, $rows, $defaults): Collection {
            $family = ProductFamily::query()->whereKey($family->id)->lockForUpdate()->firstOrFail();
            if ($family->option1_id === null) {
                throw InvalidCatalogData::invalid('The family has no variant options.');
            }
            if ($rows === []) {
                throw InvalidCatalogData::invalid('Select at least one combination.');
            }

            $template = Product::query()->where('family_id', $family->id)->orderBy('id')->first();
            $products = new Collection;
            $seen = [];

            foreach ($rows as $row) {
                $v1 = $this->value($row['option1_value_id'], $family->option1_id);
                $v2 = $family->option2_id === null
                    ? (($row['option2_value_id'] ?? null) === null ? null : throw InvalidCatalogData::invalid('The family has only one option.'))
                    : $this->value($row['option2_value_id'] ?? throw InvalidCatalogData::required('Second option value'), $family->option2_id);

                $key = $v1->id.'-'.($v2->id ?? '');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $products->push($this->upsert($actor, $family, $template, $v1, $v2, $row, $defaults));
            }

            Audit::log('product_family.variants_generated', $family, ['count' => $products->count()], $actor);

            return $products;
        });
    }

    /**
     * @param  Row  $row
     * @param  array{sale_price?: Money|string, cost_price?: Money|string}  $defaults
     */
    private function upsert(User $actor, ProductFamily $family, ?Product $template, VariantOptionValue $v1, ?VariantOptionValue $v2, array $row, array $defaults): Product
    {
        $product = Product::withTrashed()
            ->where('family_id', $family->id)
            ->where('option1_value_id', $v1->id)
            ->when(! $v2 instanceof VariantOptionValue, fn ($q) => $q->whereNull('option2_value_id'), fn ($q) => $q->where('option2_value_id', $v2?->id))
            ->first();
        $created = ! $product instanceof Product;
        $product ??= new Product;

        $label = $v2 instanceof VariantOptionValue ? $v1->value.' / '.$v2->value : $v1->value;
        $attributes = ['variant_label' => $label];

        if (isset($row['sale_price'])) {
            $attributes['sale_price'] = Values::money($row['sale_price']);
        } elseif ($created) {
            $attributes['sale_price'] = isset($defaults['sale_price']) ? Values::money($defaults['sale_price'])
                : ($template instanceof Product ? $template->sale_price : throw InvalidCatalogData::required('Sale price'));
        }
        if (isset($row['cost_price'])) {
            $attributes['cost_price'] = Values::money($row['cost_price']);
        } elseif ($created) {
            $attributes['cost_price'] = isset($defaults['cost_price']) ? Values::money($defaults['cost_price'])
                : ($template instanceof Product ? $template->cost_price : Money::zero());
        }
        foreach (['sale_price', 'cost_price'] as $key) {
            if (isset($attributes[$key]) && $attributes[$key]->isNegative()) {
                throw InvalidCatalogData::invalid('Prices must not be negative.');
            }
        }

        $sku = isset($row['sku']) && trim($row['sku']) !== ''
            ? trim($row['sku'])
            : ($created ? $family->code.'-'.$v1->code.($v2 instanceof VariantOptionValue ? '-'.$v2->code : '') : $product->sku);
        $skuTaken = Product::withTrashed()->where('sku', $sku)->when(! $created, fn ($q) => $q->whereKeyNot($product->id))->exists();
        if ($skuTaken) {
            throw InvalidCatalogData::duplicate('SKU', $sku);
        }
        $attributes['sku'] = $sku;

        $barcode = isset($row['barcode']) ? trim($row['barcode']) : '';
        if ($barcode !== '') {
            if (strncasecmp($barcode, 'MC', 2) === 0) {
                throw InvalidCatalogData::reservedBarcode();
            }
            $taken = Product::withTrashed()->where('barcode', $barcode)->when(! $created, fn ($q) => $q->whereKeyNot($product->id))->exists();
            if ($taken) {
                throw InvalidCatalogData::duplicate('barcode', $barcode);
            }
            $attributes['barcode'] = $barcode;
        } elseif ($created || $product->barcode === null) {
            $attributes['barcode'] = $this->barcodes->next();
        }

        if ($created) {
            $attributes += [
                'family_id' => $family->id,
                'option1_value_id' => $v1->id,
                'option2_value_id' => $v2?->id,
                'name' => $family->name,
                'name_bn' => $family->name_bn,
                'category_id' => $family->category_id,
                'brand_id' => $family->brand_id,
                'unit_id' => $family->unit_id,
                'tax_rate_id' => $family->tax_rate_id,
                'image_path' => $family->image_path,
                'description' => $family->description,
                'created_by' => $actor->id,
            ];
        }
        $attributes['updated_by'] = $actor->id;

        $product->fill($attributes);
        if ($product->trashed()) {
            $product->restore();
        }
        $product->save();

        return $product->refresh();
    }

    private function value(int $id, int $optionId): VariantOptionValue
    {
        $value = VariantOptionValue::query()->findOrFail($id);
        if ($value->variant_option_id !== $optionId) {
            throw InvalidCatalogData::invalid('A selected value does not belong to the family\'s option.');
        }

        return $value;
    }
}
