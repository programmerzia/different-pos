<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use InvalidArgumentException;

/** EAN-13 helpers. */
final class Ean13
{
    /** Appends the check digit to a 12-digit body. */
    public static function complete(string $body): string
    {
        if (preg_match('/^[0-9]{12}$/', $body) !== 1) {
            throw new InvalidArgumentException('An EAN-13 body must have 12 digits.');
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $body[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return $body.((10 - $sum % 10) % 10);
    }

    public static function isValid(string $code): bool
    {
        return preg_match('/^[0-9]{13}$/', $code) === 1 && self::complete(substr($code, 0, 12)) === $code;
    }
}
