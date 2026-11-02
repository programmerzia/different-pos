<?php

declare(strict_types=1);

namespace App\Actions\Sales\Data;

use App\Support\Money\Money;
use Carbon\CarbonInterface;

/**
 * A due collection. `allocations` (sale id => amount) settles those sales; null settles the oldest
 * dues first. What is not allocated stays on the account as an advance.
 */
final readonly class CustomerPaymentDraft
{
    /** @param  array<int, Money>|null  $allocations */
    public function __construct(
        public int $customerId,
        public Money $amount,
        public int $paymentMethodId,
        public ?array $allocations = null,
        public ?string $reference = null,
        public ?string $note = null,
        public ?CarbonInterface $paidAt = null,
    ) {}
}
