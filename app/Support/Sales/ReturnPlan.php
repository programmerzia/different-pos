<?php

declare(strict_types=1);

namespace App\Support\Sales;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemBatch;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;

/**
 * A validated, server-valued return of sale lines (no rows touched yet). Each line is worth its
 * share of what was paid for it: line_total minus its prorated order discount, × qty / sold qty
 * (the last return of a line takes the exact remainder).
 *
 * @phpstan-import-type SoldPortion from SoldStock
 *
 * @phpstan-type PlannedReturnLine array{item: SaleItem, product: Product, qty: Quantity, restock: bool, value: Money, tax: Money, portions: list<SoldPortion>}
 */
final readonly class ReturnPlan
{
    /** @param  list<PlannedReturnLine>  $lines */
    public function __construct(
        public Sale $sale,
        public Customer $customer,
        public array $lines,
    ) {}

    public function total(): Money
    {
        return array_reduce($this->lines, static fn (Money $sum, array $line): Money => $sum->plus($line['value']), Money::zero());
    }

    public function tax(): Money
    {
        return array_reduce($this->lines, static fn (Money $sum, array $line): Money => $sum->plus($line['tax']), Money::zero());
    }

    /** @return list<Product> */
    public function restockedProducts(): array
    {
        $products = [];
        foreach ($this->lines as $line) {
            if ($line['restock'] && $line['product']->track_stock) {
                $products[$line['product']->id] = $line['product'];
            }
        }

        return array_values($products);
    }

    /** A comparable fingerprint of the values and stock portions, to detect a concurrent change. */
    public function signature(): string
    {
        $parts = [];
        foreach ($this->lines as $line) {
            $portions = array_map(
                static fn (array $portion): string => ($portion['link'] instanceof SaleItemBatch ? $portion['link']->id : 'b').':'.$portion['allocation']['qty']->milli,
                $line['portions'],
            );
            $parts[] = $line['item']->id.'|'.$line['qty']->milli.'|'.$line['value']->paisa.'|'.$line['tax']->paisa.'|'.implode(',', $portions);
        }

        return implode(';', $parts);
    }
}
