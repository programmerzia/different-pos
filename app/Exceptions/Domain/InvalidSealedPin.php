<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** A sealed customer-PIN box could not be opened or is malformed. The message never contains the PIN. */
final class InvalidSealedPin extends DomainException
{
    public static function cannotOpen(): self
    {
        return new self('The sealed PIN could not be opened with this tenant key.');
    }

    public static function malformed(string $reason): self
    {
        return new self('The sealed PIN payload is malformed: '.$reason.'.');
    }
}
