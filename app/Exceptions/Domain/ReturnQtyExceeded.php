<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Support\Quantity\Quantity;

/** A return line above the quantity sold minus the quantity already returned. */
final class ReturnQtyExceeded extends DomainException
{
    public static function forItem(int $saleItemId, Quantity $requested, Quantity $returnable): self
    {
        return new self(sprintf(
            'Sale line %d: %s requested, only %s can be returned.',
            $saleItemId,
            $requested->toDecimalString(),
            $returnable->toDecimalString(),
        ));
    }
}
