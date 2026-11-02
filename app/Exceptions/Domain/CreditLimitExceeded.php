<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Support\Money\Money;

/** A due that the customer may not take: walk-in, or above the credit limit without a manager override. */
final class CreditLimitExceeded extends DomainException
{
    public static function walkIn(): self
    {
        return new self('A sale with a due needs a named customer.');
    }

    public static function over(Money $limit, Money $balanceAfter): self
    {
        return new self(sprintf(
            'The customer would owe %s, above the credit limit of %s.',
            $balanceAfter->toDecimalString(),
            $limit->toDecimalString(),
        ));
    }
}
