<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** A unit price other than the product's sale price, without pos.price_override. */
final class PriceOverrideNotAllowed extends DomainException
{
    public static function onLine(int $line, string $sku): self
    {
        return new self(sprintf('Line %d (%s): changing the price needs a manager.', $line, $sku));
    }
}
