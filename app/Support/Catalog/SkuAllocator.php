<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Enums\ProductItemType;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Auto SKUs for items created without one (the GRN's inline "New item"): RM-000001 for raw
 * materials, SKU-000001 for finished products, the next free number of the tenant. Call inside
 * Transaction::run(); the unique (tenant_id, sku) index backs it up under a race.
 */
final readonly class SkuAllocator
{
    public function next(ProductItemType $type): string
    {
        $prefix = $type === ProductItemType::RawMaterial ? 'RM-' : 'SKU-';
        $last = Product::withTrashed()
            ->whereRaw('sku REGEXP ?', ['^'.$prefix.'[0-9]{6}$'])
            ->max(DB::raw('CAST(SUBSTRING(sku, '.(strlen($prefix) + 1).') AS UNSIGNED)'));
        $n = (int) $last + 1;
        do {
            $sku = $prefix.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
            $n++;
        } while (Product::withTrashed()->where('sku', $sku)->exists());

        return $sku;
    }
}
