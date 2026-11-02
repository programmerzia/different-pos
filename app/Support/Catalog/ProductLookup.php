<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Models\Product;

/** Scan-field / picker lookup: exact barcode or SKU first, else a name or SKU-prefix search. */
final class ProductLookup
{
    /** @return array{exact: bool, products: list<array<string, mixed>>} */
    public function find(string $code, bool $withCost): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['exact' => false, 'products' => []];
        }
        $escaped = addcslashes($code, '\\%_');

        $exact = Product::query()->where('is_active', true)
            ->where(static fn ($q) => $q->where('barcode', $code)->orWhere('sku', $code))->limit(1)->get();
        $products = $exact->isNotEmpty() ? $exact : Product::query()->where('is_active', true)
            ->where(static fn ($q) => $q->where('name', 'like', '%'.$escaped.'%')->orWhere('sku', 'like', $escaped.'%'))
            ->orderBy('name')->limit(8)->get();

        return [
            'exact' => $exact->isNotEmpty(),
            'products' => array_values($products->map(static fn (Product $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'variant_label' => $p->variant_label,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'track_stock' => $p->track_stock,
                'cost_price' => $withCost ? $p->cost_price->paisa : 0,
            ])->all()),
        ];
    }
}
