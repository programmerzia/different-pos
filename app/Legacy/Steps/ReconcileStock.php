<?php

declare(strict_types=1);

namespace App\Legacy\Steps;

use App\Legacy\ImportContext;
use App\Support\Quantity\Quantity;
use Illuminate\Support\Facades\DB;

/**
 * Step 9: branch_stocks.qty_on_hand = Σ stock_batches.qty_remaining per (branch, product). Every
 * imported product also gets a row in its home branch. The difference from the legacy
 * product.quantity goes into the report as stock_mismatch; it is never silently fixed.
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
        $now = $context->now();

        /** @var array<string, array{branch_id: int, product_id: int, milli: int}> $stock */
        $stock = [];
        $sums = DB::table('stock_batches')->where('tenant_id', $tenantId)
            ->groupBy('branch_id', 'product_id')
            ->selectRaw('branch_id, product_id, SUM(qty_remaining) AS qty')
            ->get();
        foreach ($sums as $row) {
            $stock[$row->branch_id.':'.$row->product_id] = [
                'branch_id' => (int) $row->branch_id,
                'product_id' => (int) $row->product_id,
                'milli' => Quantity::parse((string) $row->qty)->milli,
            ];
        }

        $legacy = $context->legacy()->table(Products::TABLE)->whereIn('store_id', $context->stores())->orderBy('id')->get(['id', 'store_id', 'quantity']);
        foreach ($legacy as $product) {
            $productId = $context->ids->get(Products::TABLE, (int) $product->id);
            if ($productId === null) {
                continue;
            }
            $branchId = $context->branchId((int) $product->store_id);
            $key = $branchId.':'.$productId;
            $stock[$key] ??= ['branch_id' => $branchId, 'product_id' => $productId, 'milli' => 0];

            $legacyQty = Quantity::ofUnits((int) $product->quantity);
            $batchQty = Quantity::ofMilli($stock[$key]['milli']);
            if (! $legacyQty->equals($batchQty)) {
                $diff = $batchQty->minus($legacyQty);
                $context->report->add('stock_mismatch', Products::TABLE, (int) $product->id, sprintf(
                    'Legacy product quantity %s, batch remainders %s, difference %s%s (batches kept)',
                    $legacyQty->format(), $batchQty->format(), $diff->isPositive() ? '+' : '', $diff->format(),
                ));
            }
        }

        $rows = array_map(static fn (array $row): array => [
            'tenant_id' => $tenantId,
            'branch_id' => $row['branch_id'],
            'product_id' => $row['product_id'],
            'qty_on_hand' => Quantity::ofMilli($row['milli'])->toDecimalString(),
            'updated_at' => $now,
        ], array_values($stock));

        $existing = DB::table('branch_stocks')->where('tenant_id', $tenantId)->count();
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('branch_stocks')->upsert($chunk, ['branch_id', 'product_id'], ['qty_on_hand', 'updated_at']);
        }
        $after = DB::table('branch_stocks')->where('tenant_id', $tenantId)->count();
        $context->created($after - $existing);
        $context->skipped(count($rows) - ($after - $existing));
    }
}
