<?php

declare(strict_types=1);

namespace App\Support\Stock;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Exceptions\Domain\InsufficientStock;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The only writer of branch_stocks, stock_batches and stock_movements.
 *
 * Every call must run inside Transaction::run() and takes its locks in the documented order:
 * branch_stocks (ORDER BY product_id) FOR UPDATE, then stock_batches (ORDER BY received_at, id) FOR UPDATE.
 * Callers that also lock party rows (vendors, customers) must do so after the ledger call.
 * Issues are planned in memory first, so a refused issue writes nothing.
 *
 * @phpstan-type StockRef array{type: string, id: int, user_id?: int|null, note?: string|null}
 * @phpstan-type StockAllocation array{batch: StockBatch|null, qty: Quantity, unit_cost: Money, is_backorder: bool}
 * @phpstan-type IssueLine array{product: Product, qty: Quantity, ref: StockRef, batch?: StockBatch|null}
 */
final readonly class StockLedger
{
    public function __construct(private TenantContext $context) {}

    /**
     * Locks (creating on first use) the branch_stocks rows of the products, in product_id order.
     *
     * @param  list<Product>  $products
     * @return array<int, BranchStock> keyed by product id
     */
    public function lock(Branch $branch, array $products): array
    {
        $this->guard($branch);

        $ids = [];
        foreach ($products as $product) {
            if ($product->tenant_id !== $branch->tenant_id) {
                throw new LogicException(sprintf('Product %d does not belong to the tenant of branch %d.', $product->id, $branch->id));
            }
            $ids[$product->id] = $product->id;
        }
        sort($ids);

        if ($ids === []) {
            return [];
        }

        /** @var list<int> $existing */
        $existing = BranchStock::query()->where('branch_id', $branch->id)->whereIn('product_id', $ids)->pluck('product_id')->all();
        $missing = array_values(array_diff($ids, $existing));

        if ($missing !== []) {
            $now = now();
            DB::table('branch_stocks')->insertOrIgnore(array_map(static fn (int $productId): array => [
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'product_id' => $productId,
                'qty_on_hand' => '0.000',
                'qty_in_transit' => '0.000',
                'updated_at' => $now,
            ], $missing));
        }

        $stocks = [];
        $rows = BranchStock::query()
            ->where('branch_id', $branch->id)
            ->whereIn('product_id', $ids)
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get();

        foreach ($rows as $row) {
            $stocks[$row->product_id] = $row;
        }

        return $stocks;
    }

    /**
     * Puts new stock on the shelf as one FIFO batch.
     *
     * @param  StockRef  $ref
     */
    public function receive(
        Product $product,
        Branch $branch,
        Quantity $qty,
        Money $unitCost,
        StockMovementType $type,
        ?string $batchNo,
        array $ref,
        ?CarbonInterface $receivedAt = null,
        ?CarbonInterface $expiresAt = null,
    ): StockBatch {
        if (! $qty->isPositive()) {
            throw new LogicException('A receipt needs a positive quantity.');
        }

        if ($unitCost->isNegative()) {
            throw new LogicException('A receipt cannot have a negative unit cost.');
        }

        $source = self::sourceFor($type);
        $stock = $this->lock($branch, [$product])[$product->id];

        $batch = new StockBatch;
        $batch->forceFill([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'source_type' => $source,
            'source_id' => $ref['id'],
            'batch_no' => $batchNo,
            'unit_cost' => $unitCost,
            'qty_received' => $qty,
            'qty_remaining' => $qty,
            'received_at' => $receivedAt ?? now(),
            'expires_at' => $expiresAt,
        ])->save();

        $this->bump($stock, $qty);
        $this->record($branch, $product->id, $batch->id, $type, $qty, $unitCost, $ref, false);

        return $batch;
    }

    /**
     * Single-line convenience over issueMany().
     *
     * @param  StockRef  $ref
     * @return list<StockAllocation>
     */
    public function issue(
        Product $product,
        Branch $branch,
        Quantity $qty,
        StockMovementType $type,
        array $ref,
        ?StockBatch $forcedBatch = null,
        ?bool $allowNegative = null,
    ): array {
        return $this->issueMany($branch, $type, [
            ['product' => $product, 'qty' => $qty, 'ref' => $ref, 'batch' => $forcedBatch],
        ], $allowNegative)[0];
    }

    /**
     * Takes stock off the shelf FIFO (oldest batch first, split across batches as needed).
     *
     * A line with a `batch` (batch_selection=manual) takes only from that batch. A shortfall throws
     * InsufficientStock unless negative stock is allowed ($allowNegative, else the branch setting
     * allow_negative_stock). An allowed shortfall becomes a backorder allocation without a batch,
     * costed at products.cost_price, and branch_stocks goes negative. Products that do not track
     * stock return no allocations and write nothing. Cancelling a receipt is an issue of the
     * purchase batch with type purchase (negative qty).
     *
     * @param  list<IssueLine>  $lines
     * @return list<list<StockAllocation>> one allocation list per line, same order
     */
    public function issueMany(Branch $branch, StockMovementType $type, array $lines, ?bool $allowNegative = null): array
    {
        $tracked = [];
        foreach ($lines as $index => $line) {
            if (! $line['qty']->isPositive()) {
                throw new LogicException(sprintf('Issue line %d needs a positive quantity.', $index));
            }
            if (self::tracks($line['product'])) {
                $tracked[] = $line['product'];
            }
        }

        $stocks = $this->lock($branch, $tracked);
        $allowNegative ??= (bool) $branch->setting('allow_negative_stock');

        // Lock every candidate batch of the involved products in FIFO order.
        $productIds = array_keys($stocks);
        $forcedIds = [];
        foreach ($lines as $line) {
            $forced = $line['batch'] ?? null;
            if ($forced instanceof StockBatch && self::tracks($line['product'])) {
                if ($forced->product_id !== $line['product']->id || $forced->branch_id !== $branch->id) {
                    throw new LogicException(sprintf('Batch %d does not hold product %d in branch %d.', $forced->id, $line['product']->id, $branch->id));
                }
                $forcedIds[] = $forced->id;
            }
        }

        $batches = $productIds === [] ? collect() : StockBatch::query()
            ->where('branch_id', $branch->id)
            ->whereIn('product_id', $productIds)
            ->where(static fn ($query) => $query->where('qty_remaining', '>', 0)->orWhereIn('id', $forcedIds))
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        /** @var array<int, list<StockBatch>> $queues */
        $queues = [];
        /** @var array<int, StockBatch> $byId */
        $byId = [];
        /** @var array<int, int> $remaining milli-units left per batch id during planning */
        $remaining = [];
        foreach ($batches as $batch) {
            $queues[$batch->product_id][] = $batch;
            $byId[$batch->id] = $batch;
            $remaining[$batch->id] = $batch->qty_remaining->milli;
        }

        // Plan.
        /** @var list<list<array{batch: StockBatch|null, milli: int, unit_cost: Money}>> $plans */
        $plans = [];
        foreach ($lines as $line) {
            $product = $line['product'];
            if (! self::tracks($product)) {
                $plans[] = [];

                continue;
            }

            $want = $line['qty']->milli;
            $forced = $line['batch'] ?? null;
            $candidates = $forced instanceof StockBatch
                ? (isset($byId[$forced->id]) ? [$byId[$forced->id]] : [])
                : ($queues[$product->id] ?? []);

            $plan = [];
            foreach ($candidates as $batch) {
                if ($want === 0) {
                    break;
                }
                $take = min($want, $remaining[$batch->id]);
                if ($take <= 0) {
                    continue;
                }
                $remaining[$batch->id] -= $take;
                $want -= $take;
                $plan[] = ['batch' => $batch, 'milli' => $take, 'unit_cost' => $batch->unit_cost];
            }

            if ($want > 0) {
                if (! $allowNegative) {
                    $available = Quantity::ofMilli($line['qty']->milli - $want);
                    throw $forced instanceof StockBatch
                        ? InsufficientStock::inBatch($forced->id, $line['qty'], $available)
                        : InsufficientStock::for($product->id, $line['qty'], $available);
                }
                $plan[] = ['batch' => null, 'milli' => $want, 'unit_cost' => $product->cost_price];
            }

            $plans[] = $plan;
        }

        // Write.
        foreach ($remaining as $batchId => $milli) {
            $batch = $byId[$batchId];
            if ($batch->qty_remaining->milli !== $milli) {
                $batch->qty_remaining = Quantity::ofMilli($milli);
                $batch->save();
            }
        }

        $result = [];
        foreach ($plans as $index => $plan) {
            $line = $lines[$index];
            $allocations = [];
            $total = 0;
            foreach ($plan as $step) {
                $qty = Quantity::ofMilli($step['milli']);
                $isBackorder = ! $step['batch'] instanceof StockBatch;
                $this->record($branch, $line['product']->id, $step['batch']?->id, $type, $qty->negated(), $step['unit_cost'], $line['ref'], $isBackorder);
                $allocations[] = ['batch' => $step['batch'], 'qty' => $qty, 'unit_cost' => $step['unit_cost'], 'is_backorder' => $isBackorder];
                $total += $step['milli'];
            }
            if ($total > 0) {
                $this->bump($stocks[$line['product']->id], Quantity::ofMilli(-$total));
            }
            $result[] = $allocations;
        }

        return $result;
    }

    /**
     * Puts issued quantity back onto the batches it came from (sale void, sale return).
     * Backorder allocations (no batch) only restore branch_stocks. A batch takes back at most its
     * headroom (received - remaining); any remainder goes into a new `return` batch at the same
     * unit cost (never throws: imported legacy batches may have sold more than they received).
     *
     * @param  list<StockAllocation>  $allocations
     * @param  StockRef  $ref
     */
    public function reverse(Product $product, Branch $branch, array $allocations, StockMovementType $type, array $ref): void
    {
        if (! self::isInbound($type)) {
            throw new LogicException(sprintf('[%s] is not an inbound movement type.', $type->value));
        }

        if (! self::tracks($product) || $allocations === []) {
            return;
        }

        $stock = $this->lock($branch, [$product])[$product->id];

        $batchIds = [];
        foreach ($allocations as $allocation) {
            if (! $allocation['qty']->isPositive()) {
                throw new LogicException('A reversal needs positive quantities.');
            }
            if ($allocation['batch'] instanceof StockBatch) {
                $batchIds[] = $allocation['batch']->id;
            }
        }

        $batches = $batchIds === [] ? collect() : StockBatch::query()
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->whereIn('id', $batchIds)
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $total = 0;
        foreach ($allocations as $allocation) {
            $batch = $allocation['batch'];
            $qty = $allocation['qty'];

            $total += $qty->milli;

            if (! $batch instanceof StockBatch) {
                $this->record($branch, $product->id, null, $type, $qty, $allocation['unit_cost'], $ref, true);

                continue;
            }

            $locked = $batches->get($batch->id) ?? throw new LogicException(sprintf('Batch %d does not hold product %d in branch %d.', $batch->id, $product->id, $branch->id));

            // The original batch takes back only up to its headroom (received - remaining). Imported
            // legacy batches can have sold more than they received; the rest becomes a new return
            // batch at the same unit cost, received now, so FIFO sells it after the older batches.
            $headroom = $locked->qty_received->minus($locked->qty_remaining);
            $back = match (true) {
                ! $headroom->isPositive() => Quantity::zero(),
                $headroom->compareTo($qty) < 0 => $headroom,
                default => $qty,
            };
            $extra = $qty->minus($back);

            if ($back->isPositive()) {
                $locked->qty_remaining = $locked->qty_remaining->plus($back);
                $locked->save();
                $this->record($branch, $product->id, $locked->id, $type, $back, $allocation['unit_cost'], $ref, false);
            }

            if ($extra->isPositive()) {
                $fresh = new StockBatch;
                $fresh->forceFill([
                    'tenant_id' => $branch->tenant_id,
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'source_type' => StockSourceType::Return,
                    'source_id' => $ref['id'],
                    'batch_no' => $locked->batch_no,
                    'unit_cost' => $allocation['unit_cost'],
                    'qty_received' => $extra,
                    'qty_remaining' => $extra,
                    'received_at' => now(),
                    'expires_at' => $locked->expires_at,
                ])->save();
                $this->record($branch, $product->id, $fresh->id, $type, $extra, $allocation['unit_cost'], $ref, false);
            }
        }

        $this->bump($stock, Quantity::ofMilli($total));
    }

    /**
     * Moves a branch's qty_in_transit by $delta: positive when a transfer is dispatched to it, negative when
     * the transfer is received. The destination is not the context branch while dispatching, so this is the
     * one branch_stocks write that bypasses the branch-scoped model (tenant still checked).
     */
    public function moveInTransit(Branch $branch, Product $product, Quantity $delta): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('StockLedger must be called inside Transaction::run().');
        }
        if ($this->context->tenantId() !== $branch->tenant_id || $product->tenant_id !== $branch->tenant_id) {
            throw new LogicException(sprintf('StockLedger was called for branch %d outside its tenant context.', $branch->id));
        }

        DB::table('branch_stocks')->insertOrIgnore([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'qty_on_hand' => '0.000',
            'qty_in_transit' => '0.000',
            'updated_at' => now(),
        ]);
        $current = DB::table('branch_stocks')
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->value('qty_in_transit');
        DB::table('branch_stocks')
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->update([
                'qty_in_transit' => Quantity::parse((string) $current)->plus($delta)->toDecimalString(),
                'updated_at' => now(),
            ]);
    }

    private function guard(Branch $branch): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('StockLedger must be called inside Transaction::run().');
        }

        if ($this->context->tenantId() !== $branch->tenant_id || $this->context->branchId() !== $branch->id) {
            throw new LogicException(sprintf('StockLedger was called for branch %d outside its tenant/branch context.', $branch->id));
        }
    }

    private function bump(BranchStock $stock, Quantity $delta): void
    {
        $stock->qty_on_hand = $stock->qty_on_hand->plus($delta);
        $stock->save();
    }

    /** @param  StockRef  $ref */
    private function record(
        Branch $branch,
        int $productId,
        ?int $batchId,
        StockMovementType $type,
        Quantity $qty,
        Money $unitCost,
        array $ref,
        bool $isBackorder,
    ): void {
        $movement = new StockMovement;
        $movement->forceFill([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'product_id' => $productId,
            'stock_batch_id' => $batchId,
            'type' => $type,
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'reference_type' => $ref['type'],
            'reference_id' => $ref['id'],
            'is_backorder' => $isBackorder,
            'note' => $ref['note'] ?? null,
            'user_id' => $ref['user_id'] ?? null,
        ])->save();
    }

    /** track_stock defaults to true in the schema, so a freshly created model without the attribute is tracked. */
    private static function tracks(Product $product): bool
    {
        return $product->getAttribute('track_stock') !== false;
    }

    private static function isInbound(StockMovementType $type): bool
    {
        return match ($type) {
            StockMovementType::Opening, StockMovementType::Purchase, StockMovementType::SaleVoid,
            StockMovementType::SaleReturn, StockMovementType::AdjustmentIn, StockMovementType::TransferIn,
            StockMovementType::Stocktake => true,
            StockMovementType::Sale, StockMovementType::AdjustmentOut, StockMovementType::TransferOut => false,
        };
    }

    private static function sourceFor(StockMovementType $type): StockSourceType
    {
        return match ($type) {
            StockMovementType::Opening => StockSourceType::Opening,
            StockMovementType::Purchase => StockSourceType::Purchase,
            StockMovementType::TransferIn => StockSourceType::TransferIn,
            StockMovementType::SaleReturn, StockMovementType::SaleVoid => StockSourceType::Return,
            StockMovementType::AdjustmentIn, StockMovementType::Stocktake => StockSourceType::Adjustment,
            default => throw new LogicException(sprintf('[%s] cannot create a batch.', $type->value)),
        };
    }
}
