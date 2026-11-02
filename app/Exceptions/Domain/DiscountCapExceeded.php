<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** A manual discount above the acting user's role cap, without pos.discount_override. */
final class DiscountCapExceeded extends DomainException
{
    public static function onLine(int $line, int $bp, int $capBp): self
    {
        return new self(sprintf('Line %d: a discount of %s %% exceeds your limit of %s %%.', $line, self::pct($bp), self::pct($capBp)));
    }

    public static function onOrder(int $bp, int $capBp): self
    {
        return new self(sprintf('A discount of %s %% on the sale exceeds your limit of %s %%.', self::pct($bp), self::pct($capBp)));
    }

    private static function pct(int $bp): string
    {
        return sprintf('%d.%02d', intdiv($bp, 100), $bp % 100);
    }
}
