<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** A void after the business day of the sale closed (tenant timezone): only a return is allowed. */
final class VoidWindowClosed extends DomainException
{
    public static function for(string $number): self
    {
        return new self(sprintf('Sale %s is not from today and can no longer be voided. Use a return.', $number));
    }
}
