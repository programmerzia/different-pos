<?php

declare(strict_types=1);

namespace App\Actions\Sales\Data;

use App\Enums\RefundMode;

/**
 * Goods coming back from a completed sale. refund_mode refund pays out with `refundMethodId`
 * (default: the cash method); credit puts the value on the customer's account. Exchange is only
 * reachable through CreateExchange.
 */
final readonly class ReturnDraft
{
    /** @param  list<ReturnLineDraft>  $lines */
    public function __construct(
        public int $saleId,
        public array $lines,
        public RefundMode $refundMode = RefundMode::Refund,
        public string $reason = 'other',
        public ?int $refundMethodId = null,
        public ?string $note = null,
    ) {}
}
