<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdjustmentReason;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use Database\Factories\StockAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A manual stock correction document.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $branch_id
 * @property string $number
 * @property AdjustmentReason $reason
 * @property int|null $stocktake_id
 * @property string|null $note
 * @property Carbon $adjusted_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class StockAdjustment extends Model
{
    use BelongsToBranch, BelongsToTenant, HasUuid;

    /** @use HasFactory<StockAdjustmentFactory> */
    use HasFactory;

    protected $fillable = [
        'number', 'reason', 'stocktake_id', 'note', 'adjusted_at', 'created_by',
    ];

    /** @return HasMany<StockAdjustmentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class, 'stock_adjustment_id');
    }

    protected function casts(): array
    {
        return [
            'reason' => AdjustmentReason::class,
            'adjusted_at' => 'datetime',
        ];
    }
}
