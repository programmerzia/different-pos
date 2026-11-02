<?php

declare(strict_types=1);

namespace App\Support\Sales;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Money\Money;

/**
 * Each line's share of a sale's order discount, prorated like the pricing engine did it
 * (weights = qty × unit_price − line discount). Used to value returns and the VAT summary.
 */
final class OrderDiscountShares
{
    /**
     * @param  list<SaleItem>  $items
     * @return array<int, Money> by sale item id
     */
    public static function of(Sale $sale, array $items): array
    {
        $shares = [];
        foreach ($items as $item) {
            $shares[$item->id] = Money::zero();
        }

        $weights = array_map(static fn (SaleItem $item): int => max(0, $item->unit_price->times($item->qty)->minus($item->discount)->paisa), $items);
        if ($sale->order_discount->paisa <= 0 || array_sum($weights) === 0) {
            return $shares;
        }

        foreach ($sale->order_discount->allocate($weights) as $index => $share) {
            $shares[$items[$index]->id] = $share;
        }

        return $shares;
    }
}
