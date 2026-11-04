<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Models\Product;
use App\Support\Quantity\Quantity;
use Illuminate\Support\Facades\Storage;

/** Product → plain arrays for Inertia: money as paisa, quantities as milli-units, cost only when allowed. */
final class ProductPresenter
{
    /** @return array<string, mixed> */
    public static function row(Product $product, bool $withCost): array
    {
        $onHand = $product->getAttribute('on_hand');
        $stock = Quantity::parse(is_string($onHand) || is_numeric($onHand) ? (string) $onHand : '0');
        $low = $product->track_stock && $product->reorder_level->isPositive() && $stock->compareTo($product->reorder_level) <= 0;

        return [
            'id' => $product->id,
            'uuid' => $product->uuid,
            'name' => $product->name,
            'name_bn' => $product->name_bn,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'item_type' => $product->item_type->value,
            'unit' => $product->unit?->short_name,
            'variant_label' => $product->variant_label,
            'family_id' => $product->family_id,
            'family_uuid' => $product->family?->uuid,
            'category' => $product->category?->name,
            'brand' => $product->brand?->name,
            'sale_price' => $product->sale_price->paisa,
            'cost_price' => $withCost ? $product->cost_price->paisa : null,
            'on_hand' => $product->track_stock ? $stock->milli : null,
            'reorder_level' => $product->reorder_level->milli,
            'low_stock' => $low,
            'is_active' => $product->is_active,
            'image_url' => self::imageUrl($product->image_path),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Product $product, bool $withCost): array
    {
        return [
            'id' => $product->id,
            'uuid' => $product->uuid,
            'name' => $product->name,
            'name_bn' => $product->name_bn,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'hs_code' => $product->hs_code,
            'type' => $product->type->value,
            'item_type' => $product->item_type->value,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'unit_id' => $product->unit_id,
            'tax_rate_id' => $product->tax_rate_id,
            'default_vendor_id' => $product->default_vendor_id,
            'sale_price' => $product->sale_price->paisa,
            'cost_price' => $withCost ? $product->cost_price->paisa : null,
            'min_sale_price' => $product->min_sale_price?->paisa,
            'wholesale_price' => $product->wholesale_price?->paisa,
            'min_sale_qty' => $product->min_sale_qty->milli,
            'reorder_level' => $product->reorder_level->milli,
            'track_stock' => $product->track_stock,
            'is_active' => $product->is_active,
            'description' => $product->description,
            'image_url' => self::imageUrl($product->image_path),
            'family_id' => $product->family_id,
            'family_uuid' => $product->family?->uuid,
            'family_code' => $product->family?->code,
            'variant_label' => $product->variant_label,
        ];
    }

    public static function imageUrl(?string $path): ?string
    {
        return $path === null || $path === '' ? null : Storage::disk('public')->url($path);
    }
}
