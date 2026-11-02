<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** A line priced below its cost, without pos.sell_below_cost. */
final class BelowCostSale extends DomainException
{
    public static function onLine(int $line, string $sku): self
    {
        return new self(sprintf('Line %d (%s) sells below cost.', $line, $sku));
    }
}
