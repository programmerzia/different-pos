<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockTransferStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use App\Models\Concerns\LogsTenantActivity;
use Database\Factories\StockTransferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An inter-branch stock transfer (gate pass). Tenant-scoped only: both branches see it.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $number
 * @property int $from_branch_id
 * @property int $to_branch_id
 * @property StockTransferStatus $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $received_at
 * @property string|null $note
 * @property int|null $sent_by
 * @property int|null $received_by
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class StockTransfer extends Model
{
    use BelongsToTenant, HasUuid, LogsTenantActivity;

    /** @use HasFactory<StockTransferFactory> */
    use HasFactory;

    protected $fillable = [
        'number', 'from_branch_id', 'to_branch_id', 'status', 'sent_at', 'received_at', 'note', 'sent_by', 'received_by', 'created_by',
    ];

    /** @return HasMany<StockTransferItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'stock_transfer_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** @return BelongsTo<User, $this> */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    protected function casts(): array
    {
        return [
            'status' => StockTransferStatus::class,
            'from_branch_id' => 'integer',
            'to_branch_id' => 'integer',
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }
}
