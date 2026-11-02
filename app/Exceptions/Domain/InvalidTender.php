<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Support\Money\Money;

/** A tender (or refund/payment method) that cannot settle this document. */
final class InvalidTender extends DomainException
{
    public static function nonPositive(int $index): self
    {
        return new self(sprintf('Tender %d: the amount must be greater than zero.', $index));
    }

    public static function inactive(string $method): self
    {
        return new self(sprintf('The payment method %s is not active.', $method));
    }

    public static function referenceRequired(string $method): self
    {
        return new self(sprintf('The payment method %s needs a reference (e.g. the TrxID).', $method));
    }

    public static function exchangeCreditOutsideExchange(): self
    {
        return new self('Exchange credit can only be used in an exchange.');
    }

    public static function creditNeedsCustomer(): self
    {
        return new self('Customer credit needs a named customer.');
    }

    public static function creditExceedsAdvance(Money $requested, Money $available): self
    {
        return new self(sprintf('Customer credit of %s requested, only %s available.', $requested->toDecimalString(), $available->toDecimalString()));
    }

    public static function changeFromNonCash(Money $change): self
    {
        return new self(sprintf('Change of %s can only be given from a cash tender.', $change->toDecimalString()));
    }

    public static function notMoney(string $method): self
    {
        return new self(sprintf('The payment method %s cannot be used for this payment.', $method));
    }
}
