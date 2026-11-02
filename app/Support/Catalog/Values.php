<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Support\Money\Money;
use App\Support\Quantity\Quantity;

/** Normalises loose action input (value objects or decimal strings) into Money / Quantity. */
final class Values
{
    public static function money(Money|string $value): Money
    {
        return $value instanceof Money ? $value : Money::parse($value);
    }

    public static function moneyOrNull(Money|string|null $value): ?Money
    {
        return $value === null || $value === '' ? null : self::money($value);
    }

    public static function quantity(Quantity|string $value): Quantity
    {
        return $value instanceof Quantity ? $value : Quantity::parse($value);
    }
}
