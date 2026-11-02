<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** A manual SMS was requested while the tenant has no SMS provider selected. */
final class SmsNotConfigured extends DomainException
{
    public static function make(): self
    {
        return new self('No SMS provider is configured. Choose one in Settings, SMS.');
    }
}
