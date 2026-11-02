<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PartyType;
use App\Enums\PaymentDirection;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use App\Support\Money\Money;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One money movement: in from a customer, or out to a vendor or as a refund.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $branch_id
 * @property string $number
 * @property PaymentDirection $direction
 * @property PartyType $party_type
 * @property int $party_id
 * @property int $payment_method_id
 * @property int|null $account_id
 * @property Money $amount
 * @property string|null $reference
 * @property int|null $register_id
 * @property Carbon $paid_at
 * @property int $received_by
 * @property string|null $note
 * @property Carbon|null $voided_at
 * @property int|null $legacy_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Payment extends Model
{
    use BelongsToBranch, BelongsToTenant, HasUuid;

    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'number', 'direction', 'party_type', 'party_id', 'payment_method_id', 'account_id', 'amount', 'reference',
        'register_id', 'paid_at', 'received_by', 'note', 'voided_at', 'legacy_id',
    ];

    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /** @return BelongsTo<Register, $this> */
    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class, 'register_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }

    protected function casts(): array
    {
        return [
            'direction' => PaymentDirection::class,
            'party_type' => PartyType::class,
            'amount' => MoneyCast::class,
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
