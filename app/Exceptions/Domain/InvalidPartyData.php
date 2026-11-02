<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** A customer, vendor or membership card write that breaks a business rule. */
final class InvalidPartyData extends DomainException
{
    public static function required(string $field): self
    {
        return new self(sprintf('%s is required.', $field));
    }

    public static function invalid(string $message): self
    {
        return new self($message);
    }
}
