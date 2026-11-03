<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\ProductType;
use App\Exceptions\Domain\InvalidCatalogData;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Audit\Audit;
use App\Support\Catalog\BarcodeAllocator;
use App\Support\Catalog\Values;
use App\Support\Database\Transaction;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;

/**
 * Creates or updates a product. SKU is unique per tenant, a barcode is unique and may not start
 * with MC (membership cards); a blank barcode on create gets an auto EAN-13. Family membership is
 * not touched here (see GenerateFamilyVariants / AttachProductToFamily).
 *
 * @phpstan-type ProductData array{
 *     name?: string, name_bn?: string|null, sku?: string, barcode?: string|null, hs_code?: string|null,
 *     type?: ProductType|string, category_id?: int|null, brand_id?: int|null, unit_id?: int,
 *     tax_rate_id?: int|null, default_vendor_id?: int|null, sale_price?: Money|string,
 *     cost_price?: Money|string, min_sale_price?: Money|string|null,
 *     wholesale_price?: Money|string|null, min_sale_qty?: Quantity|string,
 *     reorder_level?: Quantity|string, track_stock?: bool, image_path?: string|null,
 *     description?: string|null, is_active?: bool
 * }
 */
final readonly class SaveProduct
{
    public function __construct(private BarcodeAllocator $barcodes) {}

    /** @param ProductData $data */
    public function handle(User $actor, array $data, ?Product $product = null): Product
    {
        return Transaction::run(function () use ($actor, $data, $product): Product {
            $product = $product instanceof Product ? Product::query()->findOrFail($product->id) : null;
            $created = ! $product instanceof Product;
            $product ??= new Product;

            $name = trim((string) ($data['name'] ?? ($created ? '' : $product->name)));
            if ($name === '') {
                throw InvalidCatalogData::required('Name');
            }
            $sku = trim((string) ($data['sku'] ?? ($created ? '' : $product->sku)));
            if ($sku === '') {
                throw InvalidCatalogData::required('SKU');
            }
            $this->assertSkuFree($sku, $created ? null : $product->id);

            $attributes = [
                'name' => $name,
                'sku' => $sku,
                'sale_price' => Values::money($data['sale_price'] ?? ($created ? null : $product->sale_price) ?? throw InvalidCatalogData::required('Sale price')),
            ];
            foreach (['name_bn', 'hs_code', 'image_path', 'description', 'is_active'] as $key) {
                if (array_key_exists($key, $data)) {
                    $attributes[$key] = $data[$key];
                }
            }
            if (array_key_exists('cost_price', $data)) {
                $attributes['cost_price'] = Values::money($data['cost_price']);
            }
            foreach (['min_sale_price', 'wholesale_price'] as $key) {
                if (array_key_exists($key, $data)) {
                    $attributes[$key] = Values::moneyOrNull($data[$key]);
                }
            }
            foreach (['min_sale_qty', 'reorder_level'] as $key) {
                if (array_key_exists($key, $data)) {
                    $attributes[$key] = Values::quantity($data[$key]);
                }
            }
            $rawType = $data['type'] ?? ($created ? ProductType::Standard : $product->type);
            $type = $rawType instanceof ProductType ? $rawType : (ProductType::tryFrom($rawType) ?? throw InvalidCatalogData::invalid('Unknown product type.'));
            $attributes['type'] = $type;
            $attributes['track_stock'] = $type === ProductType::Service ? false : (bool) ($data['track_stock'] ?? ($created ? true : $product->track_stock));

            $this->assertNonNegative($attributes);
            $this->assertReferences($data, $attributes, $created);

            $barcode = trim((string) ($data['barcode'] ?? ''));
            if ($barcode === '' && ! $created) {
                $barcode = (string) $product->barcode; // a blank barcode never clears an existing one
            }
            if ($barcode !== '') {
                if (strncasecmp($barcode, 'MC', 2) === 0) {
                    throw InvalidCatalogData::reservedBarcode();
                }
                $taken = Product::withTrashed()->where('barcode', $barcode)->when(! $created, fn ($q) => $q->whereKeyNot($product->id))->exists();
                if ($taken) {
                    throw InvalidCatalogData::duplicate('barcode', $barcode);
                }
            } else {
                $barcode = $this->barcodes->next();
            }
            $attributes['barcode'] = $barcode;

            $product->fill($attributes);
            if ($created) {
                $product->created_by = $actor->id;
            }
            $product->updated_by = $actor->id;
            $product->save();

            Audit::log($created ? 'product.created' : 'product.updated', $product, ['sku' => $sku], $actor);

            return $product;
        });
    }

    private function assertSkuFree(string $sku, ?int $exceptId): void
    {
        $taken = Product::withTrashed()->where('sku', $sku)->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))->exists();
        if ($taken) {
            throw InvalidCatalogData::duplicate('SKU', $sku);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function assertNonNegative(array $attributes): void
    {
        foreach (['sale_price', 'cost_price', 'min_sale_price', 'wholesale_price'] as $key) {
            if (($attributes[$key] ?? null) instanceof Money && $attributes[$key]->isNegative()) {
                throw InvalidCatalogData::invalid('Prices must not be negative.');
            }
        }
        foreach (['min_sale_qty', 'reorder_level'] as $key) {
            if (($attributes[$key] ?? null) instanceof Quantity && $attributes[$key]->isNegative()) {
                throw InvalidCatalogData::invalid('Quantities must not be negative.');
            }
        }
    }

    /**
     * Foreign keys must resolve inside the current tenant (the scoped lookup 404s otherwise).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $attributes
     */
    private function assertReferences(array $data, array &$attributes, bool $creating): void
    {
        $map = [
            'category_id' => Category::class, 'brand_id' => Brand::class, 'unit_id' => Unit::class,
            'tax_rate_id' => TaxRate::class, 'default_vendor_id' => Vendor::class,
        ];
        foreach ($map as $key => $model) {
            if (array_key_exists($key, $data)) {
                if ($data[$key] !== null) {
                    $model::query()->findOrFail($data[$key]);
                }
                $attributes[$key] = $data[$key];
            }
        }
        if ($creating && ! isset($data['unit_id'])) {
            throw InvalidCatalogData::required('Unit');
        }
    }
}
