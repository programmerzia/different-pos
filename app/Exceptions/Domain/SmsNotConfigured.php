<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** A manual SMS was requested while the tenant has no SMS provider selected. */
final class SmsNotConfigured extends DomainException
{
    public static function make(): self
    {
        return new self((string) __('sms.not_configured'));
    }

    public static function switchedOff(): self
    {
        return new self((string) __('sms.sending_off'));
    }
}
