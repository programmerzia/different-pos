<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Database\Factories\SaleItemBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The FIFO batch allocation trace of a sale line.
 *
 * @property int $id
 * @property int $sale_item_id
 * @property int $stock_batch_id
 * @property Quantity $qty
 * @property Money $unit_cost
 * @property Quantity $qty_returned
 */
final class SaleItemBatch extends Model
{
    /** @use HasFactory<SaleItemBatchFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'sale_item_id', 'stock_batch_id', 'qty', 'unit_cost', 'qty_returned',
    ];

    /** @return BelongsTo<SaleItem, $this> */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
    }

    /** @return BelongsTo<StockBatch, $this> */
    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'stock_batch_id');
    }

    protected function casts(): array
    {
        return [
            'qty' => QuantityCast::class,
            'unit_cost' => MoneyCast::class,
            'qty_returned' => QuantityCast::class,
        ];
    }
}
