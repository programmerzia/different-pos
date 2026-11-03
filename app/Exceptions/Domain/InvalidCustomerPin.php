<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** A customer PIN that cannot be set: wrong shape, the two entries differ, or it is too easy to guess. */
final class InvalidCustomerPin extends DomainException
{
    private function __construct(string $message, public readonly string $kind)
    {
        parent::__construct($message);
    }

    public static function format(): self
    {
        return new self('The PIN must be 4 to 6 digits.', 'format');
    }

    public static function mismatch(): self
    {
        return new self('The two PIN entries do not match.', 'mismatch');
    }

    public static function weak(): self
    {
        return new self('This PIN is too easy to guess. Avoid repeated or sequential digits and your phone number.', 'weak');
    }

    public static function reasonRequired(): self
    {
        return new self('A reason is required.', 'reason');
    }

    public static function alreadySet(): self
    {
        return new self('This customer already has a PIN. Reset it instead.', 'already_set');
    }
}
