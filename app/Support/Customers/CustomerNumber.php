<?php

declare(strict_types=1);

namespace App\Support\Customers;

use App\Support\Numbering\DocumentNumberer;
use App\Support\Numbers\Digits;
use InvalidArgumentException;

/** Customer numbers: a 7-digit tenant sequence value plus one Luhn check digit (8 digits). */
final readonly class CustomerNumber
{
    public function __construct(private DocumentNumberer $numberer) {}

    /** Takes the next sequence value (inside Transaction::run()). Never reused. */
    public function allocate(): string
    {
        return self::generate((int) $this->numberer->next(DocumentNumberer::CUSTOMER));
    }

    public static function generate(int $sequenceValue): string
    {
        if ($sequenceValue < 1_000_000 || $sequenceValue > 9_999_999) {
            throw new InvalidArgumentException('Customer sequence value must have 7 digits.');
        }

        $body = (string) $sequenceValue;

        return $body.self::checkDigit($body);
    }

    public static function isValid(string $number): bool
    {
        return preg_match('/^[0-9]{8}$/', $number) === 1
            && self::checkDigit(substr($number, 0, 7)) === (int) $number[7];
    }

    /** Strips spaces and converts Bangla digits to Latin. */
    public static function normalize(string $input): string
    {
        return (string) preg_replace('/\s+/u', '', Digits::toLatin($input));
    }

    /** 10000017 -> "1000 0017". */
    public static function format(string $number): string
    {
        $number = self::normalize($number);

        return strlen($number) > 4 ? substr($number, 0, 4).' '.substr($number, 4) : $number;
    }

    private static function checkDigit(string $body): int
    {
        $sum = 0;
        $double = true;

        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $digit = (int) $body[$i];

            if ($double) {
                $digit *= 2;
                $digit = $digit > 9 ? $digit - 9 : $digit;
            }

            $sum += $digit;
            $double = ! $double;
        }

        return (10 - $sum % 10) % 10;
    }
}
