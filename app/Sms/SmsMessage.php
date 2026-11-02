<?php

declare(strict_types=1);

namespace App\Sms;

/** One outgoing SMS. The phone is already normalised (+8801XXXXXXXXX). */
final readonly class SmsMessage
{
    public function __construct(
        public string $phone,
        public string $body,
        public string $reference,
    ) {}

    /** The provider format: 8801XXXXXXXXX, no plus sign. */
    public function msisdn(): string
    {
        return ltrim($this->phone, '+');
    }
}
