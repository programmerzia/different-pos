<?php

declare(strict_types=1);

namespace App\Reports;

enum ColumnType: string
{
    case Money = 'money';
    case Qty = 'qty';
    case Int = 'int';
    case Text = 'text';
    case Date = 'date';
    /** A ratio in basis points (1234 = 12.34 %); never summed in the totals row. */
    case Percent = 'percent';

    public function isNumeric(): bool
    {
        return in_array($this, [self::Money, self::Qty, self::Int, self::Percent], true);
    }

    /** Numeric columns whose cells add up (a percent of a total is not a sum). */
    public function isSummable(): bool
    {
        return in_array($this, [self::Money, self::Qty, self::Int], true);
    }
}
