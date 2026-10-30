<?php

declare(strict_types=1);

namespace App\Support\Numbers;

/** Converts between Latin (0-9) and Bangla (০-৯) digits. */
final class Digits
{
    private const array LATIN = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    private const array BANGLA = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

    public static function toLatin(string $value): string
    {
        return str_replace(self::BANGLA, self::LATIN, $value);
    }

    public static function toBangla(string $value): string
    {
        return str_replace(self::LATIN, self::BANGLA, $value);
    }

    /** Indian grouping of an unsigned integer string: 1234567 → 12,34,567. */
    public static function groupIndian(string $digits): string
    {
        if (strlen($digits) <= 3) {
            return $digits;
        }

        $last3 = substr($digits, -3);
        $rest = substr($digits, 0, -3);
        $groups = str_split(strrev($rest), 2);

        return strrev(implode(',', $groups)).','.$last3;
    }
}
