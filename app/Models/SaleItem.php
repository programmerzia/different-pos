<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A line of a sale, with name and SKU snapshots.
 *
 * @property int $id
 * @property int $sale_id
 * @property int $product_id
 * @property string $product_name
 * @property string $sku
 * @property Quantity $qty
 * @property Money $unit_price
 * @property Money $discount
 * @property int $tax_rate_bp
 * @property Money $tax_amount
 * @property Money $line_total
 * @property Money $cogs
 * @property Quantity $qty_returned
 * @property int|null $legacy_id
 */
final class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'sale_id', 'product_id', 'product_name', 'sku', 'qty', 'unit_price', 'discount', 'tax_rate_bp', 'tax_amount',
        'line_total', 'cogs', 'qty_returned', 'legacy_id',
    ];

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** @return HasMany<SaleItemBatch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(SaleItemBatch::class, 'sale_item_id');
    }

    protected function casts(): array
    {
        return [
            'qty' => QuantityCast::class,
            'unit_price' => MoneyCast::class,
            'discount' => MoneyCast::class,
            'tax_amount' => MoneyCast::class,
            'line_total' => MoneyCast::class,
            'cogs' => MoneyCast::class,
            'qty_returned' => QuantityCast::class,
        ];
    }
}
