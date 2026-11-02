<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\AllocatableType;
use App\Support\Money\Money;
use Database\Factories\PaymentAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The share of a payment that settles one document.
 *
 * @property int $id
 * @property int $payment_id
 * @property AllocatableType $allocatable_type
 * @property int $allocatable_id
 * @property Money $amount
 */
final class PaymentAllocation extends Model
{
    /** @use HasFactory<PaymentAllocationFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'payment_id', 'allocatable_type', 'allocatable_id', 'amount',
    ];

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    protected function casts(): array
    {
        return [
            'allocatable_type' => AllocatableType::class,
            'amount' => MoneyCast::class,
        ];
    }
}
