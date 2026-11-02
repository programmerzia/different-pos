<?php

declare(strict_types=1);

namespace App\Legacy\Steps;

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Legacy\ImportContext;
use App\Legacy\NumberBook;
use App\Legacy\Transform\LegacyDate;
use App\Legacy\Transform\Text;
use App\Models\Product;
use App\Models\StockBatch;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use stdClass;

/**
 * Step 8: stock invoices -> received purchases + purchase_items (the legacy received quantity).
 * The remainder of each line (available_stock > 0) is put on the shelf with StockLedger::receive()
 * as an `opening` batch (qty = available_stock, batch_no = legacy invoice_no, ref = the purchase_items
 * row), which also writes the movement and branch_stocks. Sold-out lines get a direct zero-remaining
 * batch without a movement, only so sale lines can link to it.
 *
 * Legacy never tracked what was owed to vendors, so imported purchases are recorded as paid.
 */
final readonly class StockInvoicesToPurchasesAndBatches implements Step
{
    public function __construct(private StockLedger $ledger, private TenantContext $tenancy) {}

    public const string TABLE = 'store_stock_invoice_list';

    public const string DETAILS = 'store_stock_invoice_details';

    public function name(): string
    {
        return 'StockInvoicesToPurchasesAndBatches';
    }

    public function run(ImportContext $context): void
    {
        $numbers = new NumberBook('purchases', $context->tenantId());
        $vendors = Product::query()->withTrashed()->pluck('default_vendor_id', 'id')->all();

        $context->select(self::TABLE, ['id', 'store_id', 'created_at', 'created_by'], ['invoice_no'])
            ->whereIn('store_id', $context->stores())
            ->orderBy('id')
            ->chunk($context->options->chunk, function (Collection $rows) use ($context, $numbers, $vendors): void {
                $all = $rows->count();
                $rows = $rows->reject(fn (stdClass $row): bool => $context->ids->has(self::TABLE, (int) $row->id));
                $context->skipped($all - $rows->count());
                if ($rows->isEmpty()) {
                    return;
                }

                $details = $context->select(self::DETAILS, ['id', 'invoice_id', 'product_id', 'quantity', 'available_stock', 'created_at'], ['description'], ['buy_price'])
                    ->whereIn('invoice_id', $rows->pluck('id')->all())->orderBy('id')->get()->groupBy('invoice_id');

                $this->chunk($context, $rows, $details, $numbers, $vendors);
            });
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @param  Collection<int|string, Collection<int, stdClass>>  $details
     * @param  array<int, int|null>  $vendors
     */
    private function chunk(ImportContext $context, Collection $rows, Collection $details, NumberBook $numbers, array $vendors): void
    {
        $tenantId = $context->tenantId();
        $now = $context->now();
        $fallbackUser = $context->lookup('fallback_user');
        $purchases = [];
        /** @var array<int, string> $numbersById */
        $numbersById = [];
        /** @var array<int, list<array<string, mixed>>> $lines legacy invoice id => line data */
        $lines = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $branch = $context->branch((int) $row->store_id);
            $date = LegacyDate::utc($row->created_at) ?? $now;
            $vendorId = null;
            $subtotal = Money::zero();

            foreach ($details->get($id, collect()) as $detail) {
                $productId = Products::resolve($context, (int) $detail->product_id, self::DETAILS, (int) $detail->id);
                $vendorId ??= $vendors[$productId] ?? null;
                $qty = Quantity::ofUnits(max(0, (int) $detail->quantity));
                $cost = Money::parseRounded((string) ($detail->buy_price ?? '0'))->abs();
                $remaining = (int) $detail->available_stock;
                if ($remaining < 0) {
                    $context->report->add('negative_batch', self::DETAILS, (int) $detail->id, "available_stock {$remaining}; batch remainder set to 0");
                    $remaining = 0;
                }
                if ($remaining > (int) $detail->quantity) {
                    $context->report->add('batch_over_received', self::DETAILS, (int) $detail->id, "available_stock {$remaining} exceeds quantity {$detail->quantity}; qty_received raised to match");
                    $qty = Quantity::ofUnits($remaining);
                }
                if ($qty->isZero()) {
                    $context->report->add('zero_quantity', self::DETAILS, (int) $detail->id, 'Stock line with quantity 0 skipped');

                    continue;
                }
                $lineTotal = $cost->times($qty);
                $subtotal = $subtotal->plus($lineTotal);
                $lines[$id][] = [
                    'legacy_id' => (int) $detail->id,
                    'product_id' => $productId,
                    'qty' => $qty,
                    'remaining' => Quantity::ofUnits($remaining),
                    'unit_cost' => $cost,
                    'line_total' => $lineTotal,
                    'received_at' => LegacyDate::utc($detail->created_at) ?? $date,
                ];
            }

            $vendorId ??= $context->lookup(Vendors::UNKNOWN);
            $legacyNumber = Text::orNull($context->text($row->invoice_no, self::TABLE, $id, 'invoice_no'));
            $number = $numbers->claim($context, $legacyNumber, $branch->id, $branch->code, self::TABLE, $id);
            $numbersById[$id] = $legacyNumber ?? $number;
            $purchases[] = [
                'uuid' => (string) Str::uuid7(),
                'tenant_id' => $tenantId,
                'branch_id' => $branch->id,
                'vendor_id' => $vendorId,
                'number' => $number,
                'status' => PurchaseStatus::Received->value,
                'purchased_at' => $date,
                'received_at' => $date,
                'subtotal' => $subtotal->paisa,
                'total' => $subtotal->paisa,
                'paid' => $subtotal->paisa,
                'due' => 0,
                'payment_status' => PaymentStatus::Paid->value,
                'note' => 'Imported from the legacy POS (vendor payments were not tracked there)',
                'legacy_id' => $id,
                'created_by' => $context->ids->get('store_admin', (int) $row->created_by) ?? $fallbackUser,
                'created_at' => $date,
                'updated_at' => $now,
            ];
        }

        DB::table('purchases')->insert($purchases);
        $purchaseIds = DB::table('purchases')->where('tenant_id', $tenantId)->whereIn('legacy_id', $rows->pluck('id')->all())->pluck('id', 'legacy_id');

        $items = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $purchaseId = (int) $purchaseIds[$id];
            $context->ids->put(self::TABLE, (int) $row->store_id, $id, $purchaseId);
            $context->created();

            foreach ($lines[$id] ?? [] as $line) {
                $items[] = [
                    'purchase_id' => $purchaseId,
                    'product_id' => $line['product_id'],
                    'qty' => $line['qty']->toDecimalString(),
                    'unit_cost' => $line['unit_cost']->paisa,
                    'line_total' => $line['line_total']->paisa,
                    'legacy_id' => $line['legacy_id'],
                ];
            }
        }
        foreach (array_chunk($items, 1000) as $chunk) {
            DB::table('purchase_items')->insert($chunk);
        }
        $itemIds = DB::table('purchase_items')->whereIn('purchase_id', $purchaseIds->values()->all())->pluck('id', 'legacy_id');

        // Remaining stock goes on the shelf through the ledger (opening batch + movement + branch_stocks).
        // A sold-out line still needs a batch row for sale-line linking: inserted directly, remainder 0,
        // no movement, so on hand = Σ movements = Σ remainders still holds.
        $products = Product::query()->withTrashed()->whereIn('id', array_unique(array_merge([], ...array_map(
            static fn (array $saleLines): array => array_column($saleLines, 'product_id'),
            array_values($lines),
        ))))->get()->keyBy('id');
        $zero = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $branch = $context->branch((int) $row->store_id);
            foreach ($lines[$id] ?? [] as $line) {
                $itemId = (int) $itemIds[$line['legacy_id']];
                $batchNo = mb_substr($numbersById[$id], 0, 50);
                if ($line['remaining']->isPositive()) {
                    $batch = $this->tenancy->run($context->tenant(), $branch, fn (): StockBatch => $this->ledger->receive(
                        $products->get($line['product_id']) ?? throw new LogicException("Product {$line['product_id']} vanished during the import."),
                        $branch,
                        $line['remaining'],
                        $line['unit_cost'],
                        StockMovementType::Opening,
                        $batchNo,
                        ['type' => 'purchase_items', 'id' => $itemId, 'user_id' => $fallbackUser, 'note' => 'Legacy import: batch remainder'],
                        CarbonImmutable::parse($line['received_at'], 'UTC'),
                    ));
                    DB::table('stock_batches')->where('id', $batch->id)->update(['legacy_id' => $line['legacy_id']]);
                    $context->ids->put(self::DETAILS, 0, $line['legacy_id'], $batch->id);

                    continue;
                }

                $zero[] = [
                    'tenant_id' => $tenantId,
                    'branch_id' => $branch->id,
                    'product_id' => $line['product_id'],
                    'source_type' => StockSourceType::Purchase->value,
                    'source_id' => $itemId,
                    'batch_no' => $batchNo,
                    'unit_cost' => $line['unit_cost']->paisa,
                    'qty_received' => $line['qty']->toDecimalString(),
                    'qty_remaining' => '0.000',
                    'received_at' => $line['received_at'],
                    'legacy_id' => $line['legacy_id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($zero, 1000) as $chunk) {
            DB::table('stock_batches')->insert($chunk);
        }
        if ($zero !== []) {
            DB::table('stock_batches')->where('tenant_id', $tenantId)->where('source_type', StockSourceType::Purchase->value)
                ->whereIn('legacy_id', array_column($zero, 'legacy_id'))->pluck('id', 'legacy_id')
                ->each(fn (mixed $batchId, mixed $legacyId) => $context->ids->put(self::DETAILS, 0, (int) $legacyId, (int) $batchId));
        }
    }
}
