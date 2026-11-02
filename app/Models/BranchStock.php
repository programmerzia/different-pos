<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\QuantityCast;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Quantity\Quantity;
use Database\Factories\BranchStockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cached on-hand quantity of a product in a branch; the ledger is the source of truth.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property int $product_id
 * @property Quantity $qty_on_hand
 * @property Quantity $qty_in_transit
 * @property Carbon|null $updated_at
 */
final class BranchStock extends Model
{
    use BelongsToBranch, BelongsToTenant;

    /** @use HasFactory<BranchStockFactory> */
    use HasFactory;

    public const CREATED_AT = null;

    protected $fillable = [
        'product_id', 'qty_on_hand', 'qty_in_transit',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    protected function casts(): array
    {
        return [
            'qty_on_hand' => QuantityCast::class,
            'qty_in_transit' => QuantityCast::class,
        ];
    }
}
