<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Database\Factories\StockAdjustmentItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of a stock adjustment; qty is signed.
 *
 * @property int $id
 * @property int $stock_adjustment_id
 * @property int $product_id
 * @property Quantity $qty
 * @property Money $unit_cost
 */
final class StockAdjustmentItem extends Model
{
    /** @use HasFactory<StockAdjustmentItemFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'stock_adjustment_id', 'product_id', 'qty', 'unit_cost',
    ];

    /** @return BelongsTo<StockAdjustment, $this> */
    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
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
            'unit_cost' => MoneyCast::class,
        ];
    }
}
