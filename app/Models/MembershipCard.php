<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CardPrintLayout;
use App\Enums\CardStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\Syncable;
use Database\Factories\MembershipCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A membership card; at most one is active per customer.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $customer_id
 * @property int $issue_no
 * @property string $card_code
 * @property CardStatus $status
 * @property Carbon $issued_at
 * @property int $issued_by
 * @property int $print_count
 * @property Carbon|null $last_printed_at
 * @property CardPrintLayout|null $last_print_layout
 * @property Carbon|null $blocked_at
 * @property int|null $blocked_by
 * @property string|null $block_reason
 * @property string $active_guard
 * @property int|null $sync_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class MembershipCard extends Model
{
    use BelongsToTenant, Syncable;

    /** @use HasFactory<MembershipCardFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id', 'issue_no', 'card_code', 'status', 'issued_at', 'issued_by', 'print_count', 'last_printed_at',
        'last_print_layout', 'blocked_at', 'blocked_by', 'block_reason',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** @return BelongsTo<User, $this> */
    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    protected function casts(): array
    {
        return [
            'status' => CardStatus::class,
            'issued_at' => 'datetime',
            'last_printed_at' => 'datetime',
            'last_print_layout' => CardPrintLayout::class,
            'blocked_at' => 'datetime',
        ];
    }
}
