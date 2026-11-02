<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Database\Factories\PurchaseItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of a purchase.
 *
 * @property int $id
 * @property int $purchase_id
 * @property int $product_id
 * @property Quantity $qty
 * @property Money $unit_cost
 * @property Money $discount
 * @property Money $tax_amount
 * @property Money $line_total
 * @property int|null $legacy_id
 */
final class PurchaseItem extends Model
{
    /** @use HasFactory<PurchaseItemFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'purchase_id', 'product_id', 'qty', 'unit_cost', 'discount', 'tax_amount', 'line_total', 'legacy_id',
    ];

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
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
            'discount' => MoneyCast::class,
            'tax_amount' => MoneyCast::class,
            'line_total' => MoneyCast::class,
        ];
    }
}
