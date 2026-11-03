<?php

declare(strict_types=1);

namespace App\Support\Stock;

/**
 * The unit cost the stock list shows: products.cost_price, or, when that is 0 (imported products
 * often have none), the weighted average cost of the stock on hand (FIFO value / quantity), so the
 * column agrees with the Value column. Integer paisa, rounded half up.
 */
final class ListUnitCost
{
    /** @return array{paisa: int, average: bool} */
    public static function of(int $costPrice, int $valuePaisa, int $qtyMilli): array
    {
        if ($costPrice > 0 || $valuePaisa <= 0 || $qtyMilli <= 0) {
            return ['paisa' => $costPrice, 'average' => false];
        }

        return ['paisa' => intdiv(2 * $valuePaisa * 1000 + $qtyMilli, 2 * $qtyMilli), 'average' => true];
    }
}
