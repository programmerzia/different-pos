<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of a stock transfer: sent quantity, received quantity (null until received) and the FIFO cost it travels at.
 *
 * @property int $id
 * @property int $stock_transfer_id
 * @property int $product_id
 * @property Quantity $qty_sent
 * @property Quantity|null $qty_received
 * @property Money $unit_cost
 * @property string|null $short_reason
 */
final class StockTransferItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'stock_transfer_id', 'product_id', 'qty_sent', 'qty_received', 'unit_cost', 'short_reason',
    ];

    /** @return BelongsTo<StockTransfer, $this> */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    protected function casts(): array
    {
        return [
            'qty_sent' => QuantityCast::class,
            'qty_received' => QuantityCast::class,
            'unit_cost' => MoneyCast::class,
        ];
    }
}
