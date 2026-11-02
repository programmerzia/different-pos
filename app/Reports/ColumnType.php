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

    public function isNumeric(): bool
    {
        return in_array($this, [self::Money, self::Qty, self::Int], true);
    }
}
