<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Enums\StockSourceType;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Database\Factories\StockBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A FIFO cost layer of a product in a branch.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property int $product_id
 * @property StockSourceType $source_type
 * @property int|null $source_id
 * @property string|null $batch_no
 * @property Money $unit_cost
 * @property Quantity $qty_received
 * @property Quantity $qty_remaining
 * @property Carbon $received_at
 * @property Carbon|null $expires_at
 * @property int|null $legacy_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class StockBatch extends Model
{
    use BelongsToBranch, BelongsToTenant;

    /** @use HasFactory<StockBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id', 'source_type', 'source_id', 'batch_no', 'unit_cost', 'qty_received', 'qty_remaining',
        'received_at', 'expires_at', 'legacy_id',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    protected function casts(): array
    {
        return [
            'source_type' => StockSourceType::class,
            'unit_cost' => MoneyCast::class,
            'qty_received' => QuantityCast::class,
            'qty_remaining' => QuantityCast::class,
            'received_at' => 'datetime',
            'expires_at' => 'date',
        ];
    }
}
