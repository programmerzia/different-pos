<?php

declare(strict_types=1);

namespace App\Support\Sales;

use App\Models\SaleItem;
use App\Models\SaleItemBatch;
use App\Models\StockBatch;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Stock\StockLedger;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;

/**
 * Turns what a sale line took from stock (sale_item_batches + a backorder remainder without a
 * batch) back into StockLedger allocations, for a void (everything) or a restocking return
 * (a quantity, taken from the line's batches newest first, then from the backorder part).
 *
 * @phpstan-import-type StockAllocation from StockLedger
 *
 * @phpstan-type SoldPortion array{link: SaleItemBatch|null, allocation: StockAllocation}
 */
final class SoldStock
{
    /**
     * Everything the line took (a void).
     *
     * @return list<StockAllocation>
     */
    public static function all(SaleItem $item): array
    {
        return array_map(static fn (array $portion): array => $portion['allocation'], self::portions($item, $item->qty, false));
    }

    /**
     * $qty of the line that has not been put back yet (a restocking return). $item->batches must be
     * fresh (locked) rows.
     *
     * @return list<SoldPortion>
     */
    public static function forReturn(SaleItem $item, Quantity $qty): array
    {
        return self::portions($item, $qty, true);
    }

    /** @return list<SoldPortion> */
    private static function portions(SaleItem $item, Quantity $qty, bool $newestFirst): array
    {
        $links = $item->batches->sortBy('id')->values()->all();
        if ($newestFirst) {
            $links = array_reverse($links);
        }

        $ids = array_map(static fn (SaleItemBatch $link): int => $link->stock_batch_id, $links);
        $batches = $ids === [] ? collect() : StockBatch::query()->whereIn('id', $ids)->get()->keyBy('id');

        $want = $qty->milli;
        $fromBatches = 0;
        $batchCost = Money::zero();
        $portions = [];
        foreach ($links as $link) {
            $fromBatches += $link->qty->milli;
            $batchCost = $batchCost->plus($link->unit_cost->times($link->qty));
            $available = $link->qty->milli - ($newestFirst ? $link->qty_returned->milli : 0);
            $take = min($want, $available);
            if ($take <= 0) {
                continue;
            }
            $want -= $take;
            $portions[] = ['link' => $link, 'allocation' => [
                'batch' => $batches->get($link->stock_batch_id),
                'qty' => Quantity::ofMilli($take),
                'unit_cost' => $link->unit_cost,
                'is_backorder' => false,
            ]];
        }

        $backorder = $item->qty->milli - $fromBatches;
        if ($want > 0 && $backorder > 0) {
            $take = min($want, $backorder);
            $portions[] = ['link' => null, 'allocation' => [
                'batch' => null,
                'qty' => Quantity::ofMilli($take),
                'unit_cost' => self::perUnit($item->cogs->minus($batchCost), Quantity::ofMilli($backorder)),
                'is_backorder' => true,
            ]];
        }

        return $portions;
    }

    private static function perUnit(Money $amount, Quantity $qty): Money
    {
        if ($amount->paisa <= 0) {
            return Money::zero();
        }

        return Money::ofPaisa(
            BigInteger::of($amount->paisa)->multipliedBy(Quantity::SCALE_FACTOR)->dividedBy($qty->milli, RoundingMode::HalfUp)->toInt(),
        );
    }
}
