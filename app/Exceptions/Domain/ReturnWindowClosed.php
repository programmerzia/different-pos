<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** A return after the tenant's return window (setting return_days). */
final class ReturnWindowClosed extends DomainException
{
    public static function for(string $number, int $days): self
    {
        return new self(sprintf('Sale %s is older than the %d-day return window.', $number, $days));
    }
}
