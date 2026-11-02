<?php

declare(strict_types=1);

namespace App\Legacy\Steps;

use App\Legacy\ImportContext;
use App\Support\Quantity\Quantity;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Step 9: branch_stocks is maintained by StockLedger (step 8), so this step only checks and reports.
 * It asserts qty_on_hand = Σ stock_batches.qty_remaining = Σ stock_movements.qty per (branch, product),
 * and reports every difference from the legacy product.quantity as stock_mismatch (never silently fixed).
 */
final readonly class ReconcileStock implements Step
{
    public function name(): string
    {
        return 'ReconcileStock';
    }

    public function run(ImportContext $context): void
    {
        $tenantId = $context->tenantId();
        $batches = $this->sums('stock_batches', 'qty_remaining', $tenantId);
        $movements = $this->sums('stock_movements', 'qty', $tenantId);
        $onHand = $this->sums('branch_stocks', 'qty_on_hand', $tenantId);

        foreach (array_unique([...array_keys($batches), ...array_keys($movements), ...array_keys($onHand)]) as $key) {
            $expected = $onHand[$key] ?? 0;
            if (($batches[$key] ?? 0) !== $expected || ($movements[$key] ?? 0) !== $expected) {
                throw new LogicException("Stock ledger out of balance for branch:product {$key}: on hand {$expected}, batches ".($batches[$key] ?? 0).', movements '.($movements[$key] ?? 0).' (milli-units).');
            }
        }

        $legacy = $context->legacy()->table(Products::TABLE)->whereIn('store_id', $context->stores())->orderBy('id')->get(['id', 'store_id', 'quantity']);
        foreach ($legacy as $product) {
            $productId = $context->ids->get(Products::TABLE, (int) $product->id);
            if ($productId === null) {
                continue;
            }

            $legacyQty = Quantity::ofUnits((int) $product->quantity);
            $batchQty = Quantity::ofMilli($batches[$context->branchId((int) $product->store_id).':'.$productId] ?? 0);
            if ($legacyQty->equals($batchQty)) {
                continue;
            }

            $diff = $batchQty->minus($legacyQty);
            $context->report->add('stock_mismatch', Products::TABLE, (int) $product->id, sprintf(
                'Legacy product quantity %s, batch remainders %s, difference %s%s (batches kept)',
                $legacyQty->format(), $batchQty->format(), $diff->isPositive() ? '+' : '', $diff->format(),
            ));
        }
    }

    /**
     * @param  literal-string  $column
     * @return array<string, int> "branch:product" => milli-units
     */
    private function sums(string $table, string $column, int $tenantId): array
    {
        $sums = [];
        $rows = DB::table($table)->where('tenant_id', $tenantId)->groupBy('branch_id', 'product_id')
            ->get(['branch_id', 'product_id', DB::raw("SUM({$column}) AS qty")]);
        foreach ($rows as $row) {
            $sums[$row->branch_id.':'.$row->product_id] = Quantity::parse((string) $row->qty)->milli;
        }

        return $sums;
    }
}
