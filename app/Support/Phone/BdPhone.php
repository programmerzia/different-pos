<?php

declare(strict_types=1);

namespace App\Support\Phone;

use App\Support\Numbers\Digits;

/** Bangladeshi mobile numbers to E.164 (+8801XXXXXXXXX). Anything else is invalid (null). */
final class BdPhone
{
    public static function toE164(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = (string) preg_replace('/\D/', '', Digits::toLatin($input));

        $national = match (true) {
            strlen($digits) === 11 && str_starts_with($digits, '01') => substr($digits, 1),
            strlen($digits) === 13 && str_starts_with($digits, '8801') => substr($digits, 3),
            strlen($digits) === 15 && str_starts_with($digits, '008801') => substr($digits, 5),
            strlen($digits) === 10 && str_starts_with($digits, '1') => $digits,
            default => null,
        };

        return $national !== null && preg_match('/^1[3-9]\d{8}$/', $national) === 1 ? '+880'.$national : null;
    }
}
