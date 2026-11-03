<?php

declare(strict_types=1);

namespace App\WhatsApp;

/** One outgoing WhatsApp text. The phone is already normalised (+8801XXXXXXXXX). */
final readonly class WhatsAppMessage
{
    public function __construct(
        public string $phone,
        public string $text,
        public string $reference,
    ) {}

    /** E.164 digits without the plus sign: what wa.me and the Cloud API want. */
    public function digits(): string
    {
        return ltrim($this->phone, '+');
    }
}
