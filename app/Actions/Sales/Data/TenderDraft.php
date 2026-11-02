<?php

declare(strict_types=1);

namespace App\Actions\Sales\Data;

use App\Support\Money\Money;

/** One tender as handed over (cash may exceed the total; the excess is change). */
final readonly class TenderDraft
{
    public function __construct(
        public int $paymentMethodId,
        public Money $amount,
        public ?string $reference = null,
    ) {}
}
