<?php

declare(strict_types=1);

namespace App\Actions\Sales\Data;

use App\Enums\RefundMode;

/**
 * An exchange (ADR 0005): return lines of a completed sale plus a replacement cart for the same
 * customer. The return value pays the replacement as an Exchange credit tender; the replacement's
 * own tenders cover any difference. A surplus is refunded (`surplusMode` refund, with
 * `refundMethodId` or cash) or credited to the customer's account (credit, named customers only).
 */
final readonly class ExchangeDraft
{
    /** @param  list<ReturnLineDraft>  $returnLines */
    public function __construct(
        public int $saleId,
        public array $returnLines,
        public SaleDraft $replacement,
        public RefundMode $surplusMode = RefundMode::Refund,
        public ?int $refundMethodId = null,
        public string $reason = 'exchange',
        public ?string $note = null,
    ) {}
}
