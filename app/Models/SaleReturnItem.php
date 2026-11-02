<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Database\Factories\SaleReturnItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of a sale return.
 *
 * @property int $id
 * @property int $sale_return_id
 * @property int $sale_item_id
 * @property int $product_id
 * @property Quantity $qty
 * @property Money $unit_price
 * @property Money $tax_amount
 * @property Money $line_total
 * @property bool $restock
 * @property Money $cogs_reversed
 * @property int|null $legacy_id
 */
final class SaleReturnItem extends Model
{
    /** @use HasFactory<SaleReturnItemFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'sale_return_id', 'sale_item_id', 'product_id', 'qty', 'unit_price', 'tax_amount', 'line_total', 'restock',
        'cogs_reversed', 'legacy_id',
    ];

    /** @return BelongsTo<SaleReturn, $this> */
    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class, 'sale_return_id');
    }

    /** @return BelongsTo<SaleItem, $this> */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    protected function casts(): array
    {
        return [
            'qty' => QuantityCast::class,
            'unit_price' => MoneyCast::class,
            'tax_amount' => MoneyCast::class,
            'line_total' => MoneyCast::class,
            'restock' => 'boolean',
            'cogs_reversed' => MoneyCast::class,
        ];
    }
}
