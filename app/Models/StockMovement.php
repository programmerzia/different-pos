<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * An append-only stock ledger entry (+ in, - out).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property int $product_id
 * @property int|null $stock_batch_id
 * @property StockMovementType $type
 * @property Quantity $qty
 * @property Money $unit_cost
 * @property string $reference_type
 * @property int $reference_id
 * @property bool $is_backorder
 * @property string|null $note
 * @property int|null $user_id
 * @property Carbon|null $created_at
 */
final class StockMovement extends Model
{
    use BelongsToBranch, BelongsToTenant;

    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id', 'stock_batch_id', 'type', 'qty', 'unit_cost', 'reference_type', 'reference_id', 'is_backorder',
        'note', 'user_id',
    ];

    protected static function booted(): void
    {
        $refuse = static function (): never {
            throw new LogicException('StockMovement rows are append-only.');
        };
        self::updating($refuse);
        self::deleting($refuse);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** @return BelongsTo<StockBatch, $this> */
    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'stock_batch_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'qty' => QuantityCast::class,
            'unit_cost' => MoneyCast::class,
            'is_backorder' => 'boolean',
        ];
    }
}
