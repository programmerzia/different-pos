<?php

declare(strict_types=1);

namespace App\Actions\Stock;

use App\Enums\AdjustmentReason;
use App\Enums\StockMovementType;
use App\Exceptions\Domain\InvalidStockDocument;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockBatch;
use App\Models\User;
use App\Support\Database\Transaction;
use App\Support\Money\Money;
use App\Support\Numbering\DocumentNumberer;
use App\Support\Quantity\Quantity;
use App\Support\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * A manual stock correction in the current branch. A positive line is received as a new batch
 * (adjustment_in) at the given cost (default products.cost_price); a negative line is issued FIFO
 * (adjustment_out, or from `batch_id` when given) and stores the weighted FIFO unit cost.
 * Negative stock follows the branch setting allow_negative_stock.
 *
 * @phpstan-type AdjustmentLineInput array{product_id: int, qty: Quantity, unit_cost?: Money|null, batch_id?: int|null}
 * @phpstan-type AdjustmentInput array{reason: AdjustmentReason, items: list<AdjustmentLineInput>, note?: string|null, adjusted_at?: CarbonInterface|null}
 */
final readonly class AdjustStock
{
    public function __construct(
        private DocumentNumberer $numberer,
        private StockLedger $ledger,
        private TenantContext $context,
    ) {}

    /** @param  AdjustmentInput  $input */
    public function handle(User $actor, array $input): StockAdjustment
    {
        $lines = $input['items'];
        if ($lines === []) {
            throw InvalidStockDocument::noLines();
        }

        foreach ($lines as $index => $line) {
            if ($line['qty']->isZero()) {
                throw InvalidStockDocument::zeroQuantity($index + 1);
            }
            if (($line['unit_cost'] ?? null)?->isNegative() === true) {
                throw InvalidStockDocument::negativeAmount('line '.($index + 1).' cost');
            }
        }

        return Transaction::run(function () use ($actor, $input, $lines): StockAdjustment {
            $branch = $this->context->branch();

            $ids = array_values(array_unique(array_map(static fn (array $line): int => $line['product_id'], $lines)));
            $products = self::products($ids);

            $batches = [];
            foreach ($lines as $index => $line) {
                if (! $products[$line['product_id']]->track_stock) {
                    throw InvalidStockDocument::untracked($index + 1);
                }
                if (($line['batch_id'] ?? null) !== null) {
                    $batches[$index] = StockBatch::query()->where('product_id', $line['product_id'])->findOrFail($line['batch_id']);
                }
            }

            $adjustment = StockAdjustment::query()->create([
                'number' => $this->numberer->next('adjustment', $branch->id),
                'reason' => $input['reason'],
                'note' => $input['note'] ?? null,
                'adjusted_at' => $input['adjusted_at'] ?? now(),
                'created_by' => $actor->id,
            ]);

            $this->ledger->lock($branch, array_values($products));

            foreach ($lines as $index => $line) {
                $product = $products[$line['product_id']];
                $qty = $line['qty'];

                $item = StockAdjustmentItem::query()->create([
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'unit_cost' => $line['unit_cost'] ?? $product->cost_price,
                ]);
                $ref = ['type' => 'stock_adjustment_items', 'id' => $item->id, 'user_id' => $actor->id, 'note' => $input['reason']->value];

                if ($qty->isPositive()) {
                    $this->ledger->receive($product, $branch, $qty, $item->unit_cost, StockMovementType::AdjustmentIn, null, $ref);

                    continue;
                }

                $allocations = $this->ledger->issue($product, $branch, $qty->negated(), StockMovementType::AdjustmentOut, $ref, $batches[$index] ?? null);

                $cost = 0;
                foreach ($allocations as $allocation) {
                    $cost += $allocation['unit_cost']->times($allocation['qty'])->paisa;
                }
                $item->unit_cost = Money::ofPaisa(
                    BigInteger::of($cost)->multipliedBy(Quantity::SCALE_FACTOR)->dividedBy(-$qty->milli, RoundingMode::HalfUp)->toInt(),
                );
                $item->save();
            }

            return $adjustment->load('items');
        });
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, Product>
     */
    private static function products(array $ids): array
    {
        $products = [];
        foreach (Product::query()->whereIn('id', $ids)->get() as $product) {
            $products[$product->id] = $product;
        }

        $missing = array_values(array_diff($ids, array_keys($products)));
        if ($missing !== []) {
            throw (new ModelNotFoundException)->setModel(Product::class, $missing);
        }

        return $products;
    }
}
