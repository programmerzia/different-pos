<?php

declare(strict_types=1);

namespace App\Support\Money;

use App\Support\Numbers\Digits;

/**
 * Display format: "৳1,23,456.50", "-৳50.00"; Bangla digits for locale "bn" ("৳১,২৩,৪৫৬.৫০").
 * Must match formatMoney() in resources/js/engine/money.ts.
 */
final class MoneyFormatter
{
    public const string SYMBOL = '৳';

    public static function format(Money $money, string $locale = 'en', bool $symbol = true): string
    {
        $abs = abs($money->paisa);
        $taka = intdiv($abs, 100);
        $paisa = $abs % 100;

        $text = Digits::groupIndian((string) $taka).'.'.str_pad((string) $paisa, 2, '0', STR_PAD_LEFT);
        $text = ($money->paisa < 0 ? '-' : '').($symbol ? self::SYMBOL : '').$text;

        return $locale === 'bn' ? Digits::toBangla($text) : $text;
    }
}
