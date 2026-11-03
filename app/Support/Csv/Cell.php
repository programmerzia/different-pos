<?php

declare(strict_types=1);

namespace App\Support\Csv;

/** Spreadsheet-safe CSV cells (shared by every server-side CSV export). */
final class Cell
{
    /**
     * A cell that starts with = + - @ (or a tab / CR) would run as a formula in a spreadsheet, so it is
     * prefixed with `'`. Plain numbers (`-50.00`) and E.164 phone numbers (`+8801…`) are data and stay as they are.
     */
    public static function safe(string $value): string
    {
        return self::looksLikeFormula($value) ? "'".$value : $value;
    }

    /**
     * @param  list<string>  $cells
     * @return list<string>
     */
    public static function row(array $cells): array
    {
        return array_map(self::safe(...), $cells);
    }

    private static function looksLikeFormula(string $value): bool
    {
        if ($value === '' || ! str_contains("=+-@\t\r", $value[0])) {
            return false;
        }

        // Plain numbers (negative amounts, E.164 phones) are data, not formulas.
        return preg_match('/^[+-]?\d+(\.\d+)?$/', $value) !== 1;
    }
}
