<?php

declare(strict_types=1);

namespace App\Actions\Stock;

use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Exceptions\Domain\InvalidStockTransfer;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Support\Database\Transaction;
use App\Support\Quantity\Quantity;
use App\Support\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

/**
 * Books an in-transit transfer into the current branch, which must be its destination. Each line is received
 * as a new FIFO batch (transfer_in) at the cost it travelled with, for the quantity actually received; a
 * short receipt needs a reason and the shortfall is recorded on the line (and so in the branch-transfers
 * report). qty_in_transit at the destination is released for the full sent quantity.
 *
 * @phpstan-type ReceiveLineInput array{item_id: int, qty_received: Quantity, short_reason?: string|null}
 * @phpstan-type ReceiveInput array{items: list<ReceiveLineInput>}
 */
final readonly class ReceiveStockTransfer
{
    public function __construct(
        private StockLedger $ledger,
        private TenantContext $context,
    ) {}

    /** @param  ReceiveInput  $input */
    public function handle(User $actor, StockTransfer $transfer, array $input): StockTransfer
    {
        return Transaction::run(function () use ($actor, $transfer, $input): StockTransfer {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $branch = $this->context->branch();

            if ($transfer->status !== StockTransferStatus::InTransit) {
                throw InvalidStockTransfer::notInTransit($transfer->number);
            }
            if ($transfer->to_branch_id !== $branch->id) {
                throw InvalidStockTransfer::wrongBranch($transfer->number);
            }

            /** @var array<int, StockTransferItem> $items */
            $items = $transfer->items()->get()->keyBy('id')->all();
            /** @var array<int, Product> $products */
            $products = Product::query()->whereIn('id', array_map(static fn (StockTransferItem $i): int => $i->product_id, $items))->get()->keyBy('id')->all();

            $received = [];
            foreach ($input['items'] as $index => $line) {
                $item = $items[$line['item_id']] ?? throw InvalidStockTransfer::unknownLine($line['item_id']);
                $qty = $line['qty_received'];
                if ($qty->isNegative()) {
                    throw InvalidStockTransfer::nonPositiveQuantity($index + 1);
                }
                if ($qty->compareTo($item->qty_sent) > 0) {
                    throw InvalidStockTransfer::overReceived($index + 1);
                }
                $reason = trim((string) ($line['short_reason'] ?? ''));
                if ($qty->compareTo($item->qty_sent) < 0 && $reason === '') {
                    throw InvalidStockTransfer::shortReasonRequired($index + 1);
                }
                $received[$item->id] = ['qty' => $qty, 'reason' => $reason === '' ? null : $reason];
            }

            $this->ledger->lock($branch, array_values($products));

            foreach ($items as $item) {
                $product = $products[$item->product_id] ?? throw new ModelNotFoundException;
                // A line left out of the receipt is received in full.
                $qty = $received[$item->id]['qty'] ?? $item->qty_sent;
                $reason = $received[$item->id]['reason'] ?? null;

                $item->qty_received = $qty;
                $item->short_reason = $qty->compareTo($item->qty_sent) < 0 ? $reason : null;
                $item->save();

                if ($qty->isPositive()) {
                    $this->ledger->receive(
                        $product,
                        $branch,
                        $qty,
                        $item->unit_cost,
                        StockMovementType::TransferIn,
                        null,
                        ['type' => 'stock_transfer_items', 'id' => $item->id, 'user_id' => $actor->id, 'note' => $transfer->number],
                    );
                }
                $this->ledger->moveInTransit($branch, $product, $item->qty_sent->negated());
            }

            $transfer->status = StockTransferStatus::Received;
            $transfer->received_at = Carbon::now();
            $transfer->received_by = $actor->id;
            $transfer->save();

            return $transfer->load('items');
        });
    }
}
