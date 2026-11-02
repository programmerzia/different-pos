<?php

declare(strict_types=1);

namespace App\Sms;

final readonly class SmsResult
{
    private function __construct(
        public bool $ok,
        public ?string $providerRef,
        public ?string $error,
        /** A timeout or a 5xx: worth another attempt. A provider rejection is not. */
        public bool $retryable,
    ) {}

    public static function sent(?string $providerRef = null): self
    {
        return new self(true, $providerRef, null, false);
    }

    public static function failed(string $error, bool $retryable = false): self
    {
        return new self(false, null, mb_substr($error, 0, 500), $retryable);
    }
}
