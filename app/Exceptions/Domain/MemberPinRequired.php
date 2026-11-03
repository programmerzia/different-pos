<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** Policy `required`: a member sale at the online register without a verified PIN or a manager override. */
final class MemberPinRequired extends DomainException
{
    public static function forSale(): self
    {
        return new self('This customer must enter their PIN (or a manager must override) before the sale completes.');
    }
}
