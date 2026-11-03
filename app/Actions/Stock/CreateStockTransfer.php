<?php

declare(strict_types=1);

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Exceptions\Domain\InvalidStockTransfer;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Support\Database\Transaction;
use App\Support\Money\Money;
use App\Support\Numbering\DocumentNumberer;
use App\Support\Quantity\Quantity;
use App\Support\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Dispatches a gate pass from the current branch to another branch of the tenant: every line is issued FIFO
 * out of the current branch (transfer_out, never into negative stock), travels at its weighted FIFO cost and
 * is counted as qty_in_transit at the destination until ReceiveStockTransfer books it in.
 *
 * @phpstan-type TransferLineInput array{product_id: int, qty: Quantity}
 * @phpstan-type TransferInput array{to_branch_id: int, items: list<TransferLineInput>, note?: string|null}
 */
final readonly class CreateStockTransfer
{
    public function __construct(
        private DocumentNumberer $numberer,
        private StockLedger $ledger,
        private TenantContext $context,
    ) {}

    /** @param  TransferInput  $input */
    public function handle(User $actor, array $input): StockTransfer
    {
        $lines = $input['items'];
        if ($lines === []) {
            throw InvalidStockTransfer::noLines();
        }

        $seen = [];
        foreach ($lines as $index => $line) {
            if (! $line['qty']->isPositive()) {
                throw InvalidStockTransfer::nonPositiveQuantity($index + 1);
            }
            if (isset($seen[$line['product_id']])) {
                throw InvalidStockTransfer::duplicateProduct($index + 1);
            }
            $seen[$line['product_id']] = true;
        }

        return Transaction::run(function () use ($actor, $input, $lines): StockTransfer {
            $from = $this->context->branch();
            if ($input['to_branch_id'] === $from->id) {
                throw InvalidStockTransfer::sameBranch();
            }
            $to = Branch::query()->findOrFail($input['to_branch_id']);
            if (! $to->is_active) {
                throw InvalidStockTransfer::inactiveBranch();
            }

            $products = self::products(array_map(static fn (array $line): int => $line['product_id'], $lines));
            foreach ($lines as $index => $line) {
                if (! $products[$line['product_id']]->track_stock) {
                    throw InvalidStockTransfer::untracked($index + 1);
                }
            }

            $transfer = StockTransfer::query()->create([
                'number' => $this->numberer->next('transfer', $from->id),
                'from_branch_id' => $from->id,
                'to_branch_id' => $to->id,
                'status' => StockTransferStatus::InTransit,
                'sent_at' => now(),
                'note' => $input['note'] ?? null,
                'sent_by' => $actor->id,
                'created_by' => $actor->id,
            ]);

            $this->ledger->lock($from, array_values($products));

            foreach ($lines as $line) {
                $product = $products[$line['product_id']];
                $qty = $line['qty'];

                $item = StockTransferItem::query()->create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $product->id,
                    'qty_sent' => $qty,
                    'unit_cost' => $product->cost_price,
                ]);
                $ref = ['type' => 'stock_transfer_items', 'id' => $item->id, 'user_id' => $actor->id, 'note' => $transfer->number];

                $allocations = $this->ledger->issue($product, $from, $qty, StockMovementType::TransferOut, $ref, null, false);

                $cost = 0;
                foreach ($allocations as $allocation) {
                    $cost += $allocation['unit_cost']->times($allocation['qty'])->paisa;
                }
                $item->unit_cost = Money::ofPaisa(
                    BigInteger::of($cost)->multipliedBy(Quantity::SCALE_FACTOR)->dividedBy($qty->milli, RoundingMode::HalfUp)->toInt(),
                );
                $item->save();

                $this->ledger->moveInTransit($to, $product, $qty);
            }

            return $transfer->load('items');
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
