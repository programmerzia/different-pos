<?php

declare(strict_types=1);

namespace App\Legacy\Steps;

use App\Enums\MemberAuth;
use App\Enums\PaymentStatus;
use App\Enums\SaleOrigin;
use App\Enums\SaleStatus;
use App\Legacy\ImportContext;
use App\Legacy\NumberBook;
use App\Legacy\Transform\LegacyDate;
use App\Legacy\Transform\Text;
use App\Models\Product;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/**
 * Step 10: legacy sales -> completed sales (origin import, member_auth none) + sale_items +
 * sale_item_batches. The header is what the customer was charged and wins over the lines (ADR 0002
 * M3). sales.paid is set to total − due here; the Payments step writes the matching allocations.
 * cogs = buy_price × qty; a line links to the batch of its stock invoice when that resolves (the link
 * uses the same unit cost, so cogs = Σ batch qty × unit_cost holds).
 */
final readonly class Sales implements Step
{
    public const string TABLE = 'store_sales_invoice_list';

    public const string DETAILS = 'store_sales_invoice_details';

    public function name(): string
    {
        return 'Sales';
    }

    public function run(ImportContext $context): void
    {
        $numbers = new NumberBook('sales', $context->tenantId());
        $catalog = Product::query()->withTrashed()->get(['id', 'name', 'sku'])->keyBy('id');
        $batches = $this->batchIndex($context);

        $context->select(self::TABLE, ['id', 'store_id', 'customer_id', 'payment_status', 'created_at', 'created_by'], ['invoice_no', 'courier_service'], ['total_amount', 'total_discount', 'total_due'])
            ->whereIn('store_id', $context->stores())
            ->orderBy('id')
            ->chunk($context->options->chunk, function (Collection $rows) use ($context, $numbers, $catalog, $batches): void {
                $all = $rows->count();
                $rows = $rows->reject(fn (stdClass $row): bool => $context->ids->has(self::TABLE, (int) $row->id))->values();
                $context->skipped($all - $rows->count());
                if ($rows->isEmpty()) {
                    return;
                }

                $details = $context->select(self::DETAILS, ['id', 'invoice_id', 'product_id', 'quantity', 'stock_invoice_no'], [], ['price', 'buy_price', 'discount'])
                    ->whereIn('invoice_id', $rows->pluck('id')->all())->orderBy('id')->get()->groupBy('invoice_id');

                $this->chunk($context, $rows, $details, $numbers, $catalog, $batches);
            });
    }

    /**
     * "legacy stock invoice id:legacy product id" => batch id. Stock invoice refs are ids
     * (ADR 0002); an unresolvable ref leaves the line unlinked (reported).
     *
     * @return array<string, int>
     */
    private function batchIndex(ImportContext $context): array
    {
        $index = [];
        $invoiceIds = array_keys($context->ids->all(StockInvoicesToPurchasesAndBatches::TABLE));
        foreach (array_chunk($invoiceIds, 1000) as $chunk) {
            $lines = $context->legacy()->table(StockInvoicesToPurchasesAndBatches::DETAILS)
                ->whereIn('invoice_id', $chunk)->orderBy('id')->get(['id', 'invoice_id', 'product_id']);
            foreach ($lines as $line) {
                $batchId = $context->ids->get(StockInvoicesToPurchasesAndBatches::DETAILS, (int) $line->id);
                if ($batchId !== null) {
                    $index[$line->invoice_id.':'.$line->product_id] ??= $batchId;
                }
            }
        }

        return $index;
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @param  Collection<int|string, Collection<int, stdClass>>  $details
     * @param  Collection<int, Product>  $catalog
     * @param  array<string, int>  $batches
     */
    private function chunk(ImportContext $context, Collection $rows, Collection $details, NumberBook $numbers, Collection $catalog, array $batches): void
    {
        $tenantId = $context->tenantId();
        $now = $context->now();
        $today = $context->startedAt->setTimezone(LegacyDate::ZONE)->format('Y-m-d');
        $fallbackUser = $context->lookup('fallback_user');
        $walkIn = $context->lookup(CustomerGroupsAndCustomers::WALK_IN);
        $sales = [];
        /** @var array<int, list<array<string, mixed>>> $lines */
        $lines = [];

        foreach ($rows as $row) {
            $id = (int) $row->id;
            $branch = $context->branch((int) $row->store_id);
            $soldAt = LegacyDate::local($row->created_at);
            if (! $soldAt instanceof CarbonImmutable) {
                $context->report->add('invalid_date', self::TABLE, $id, 'Sale has no valid date; the import time is used');
            } elseif ($soldAt->format('Y-m-d') > $today) {
                $context->report->add('future_dated', self::TABLE, $id, 'Sale is dated '.$soldAt->format('Y-m-d').', after the import date; imported as-is for owner review');
            }
            $soldAtUtc = $soldAt?->utc()->format('Y-m-d H:i:s') ?? $now;

            $subtotal = Money::parseRounded((string) ($row->total_amount ?? '0'));
            $discount = Money::parseRounded((string) ($row->total_discount ?? '0'));
            $due = Money::parseRounded((string) ($row->total_due ?? '0'));
            $total = $subtotal->minus($discount);
            if ($total->isNegative()) {
                $context->report->add('negative_total', self::TABLE, $id, 'Discount exceeds the amount; total set to 0');
                $total = Money::zero();
            }
            if ($due->isNegative() || $due->compareTo($total) > 0) {
                $context->report->add('due_out_of_range', self::TABLE, $id, 'Due '.$due->toDecimalString().' outside 0..'.$total->toDecimalString().'; clamped');
                $due = $due->isNegative() ? Money::zero() : $total;
            }
            $paid = $total->minus($due);

            $gross = Money::zero();
            $lineDiscounts = Money::zero();
            $cogs = Money::zero();
            foreach ($details->get($id, collect()) as $detail) {
                $qty = (int) $detail->quantity;
                if ($qty <= 0) {
                    $context->report->add('zero_quantity', self::DETAILS, (int) $detail->id, 'Sale line with quantity <= 0 skipped');

                    continue;
                }
                $quantity = Quantity::ofUnits($qty);
                $price = Money::parseRounded((string) ($detail->price ?? '0'));
                $lineDiscount = Money::parseRounded((string) ($detail->discount ?? '0'));
                $unitCost = Money::parseRounded((string) ($detail->buy_price ?? '0'))->abs();
                $lineGross = $price->times($quantity);
                $lineCogs = $unitCost->times($quantity);
                $productId = Products::resolve($context, (int) $detail->product_id, self::DETAILS, (int) $detail->id);
                $batchId = (int) $detail->stock_invoice_no > 0 ? ($batches[$detail->stock_invoice_no.':'.$detail->product_id] ?? null) : null;
                if ($batchId === null) {
                    $context->report->add('unlinked_batch', self::DETAILS, (int) $detail->id, (int) $detail->stock_invoice_no > 0
                        ? "Stock invoice #{$detail->stock_invoice_no} has no line for this product; no batch link (cogs from the line buy price)"
                        : 'Sale line has no stock invoice; no batch link (cogs from the line buy price)');
                }

                $gross = $gross->plus($lineGross);
                $lineDiscounts = $lineDiscounts->plus($lineDiscount);
                $cogs = $cogs->plus($lineCogs);
                $product = $catalog->get($productId) ?? Product::query()->withTrashed()->find($productId);
                $lines[$id][] = [
                    'product_id' => $productId,
                    'product_name' => mb_substr($product->name ?? 'Product #'.$detail->product_id, 0, 200),
                    'sku' => mb_substr($product->sku ?? 'L-'.$detail->product_id, 0, 64),
                    'qty' => $quantity->toDecimalString(),
                    'unit_price' => $price->paisa,
                    'discount' => $lineDiscount->paisa,
                    'line_total' => $lineGross->minus($lineDiscount)->paisa,
                    'cogs' => $lineCogs->paisa,
                    'legacy_id' => (int) $detail->id,
                    'batch_id' => $batchId,
                    'unit_cost' => $unitCost->paisa,
                ];
            }

            if (! isset($lines[$id])) {
                $context->report->add('no_lines', self::TABLE, $id, 'Sale has no lines; header imported alone');
            } elseif (! $gross->equals($subtotal)) {
                $context->report->add('header_line_mismatch', self::TABLE, $id, 'Header amount '.$subtotal->toDecimalString().' vs lines '.$gross->toDecimalString().'; header kept');
            }
            $orderDiscount = $discount->minus($lineDiscounts);
            if ($orderDiscount->isNegative()) {
                $context->report->add('header_line_mismatch', self::TABLE, $id, 'Header discount '.$discount->toDecimalString().' below line discounts '.$lineDiscounts->toDecimalString().'; header kept');
            }

            $customerId = (int) $row->customer_id > 0 ? $context->ids->get(CustomerGroupsAndCustomers::TABLE, (int) $row->customer_id) : null;
            if ($customerId === null && (int) $row->customer_id > 0) {
                $context->report->add('orphan_customer', self::TABLE, $id, "Customer #{$row->customer_id} does not exist; sale put on the walk-in customer");
            }
            $soldBy = $context->ids->get(Users::TABLE, (int) $row->created_by);
            if ($soldBy === null) {
                $context->report->add('orphan_user', self::TABLE, $id, "User #{$row->created_by} does not exist; sale attributed to the fallback user");
            }

            $courier = Text::orNull($context->text($row->courier_service, self::TABLE, $id, 'courier_service'));
            $sales[] = [
                'uuid' => (string) Str::uuid7(),
                'tenant_id' => $tenantId,
                'branch_id' => $branch->id,
                'client_uuid' => (string) Str::uuid7(),
                'number' => $numbers->claim($context, Text::orNull($context->text($row->invoice_no, self::TABLE, $id, 'invoice_no')), $branch->id, $branch->code, self::TABLE, $id),
                'register_id' => $context->registerId($branch->id),
                'customer_id' => $customerId ?? $walkIn,
                'status' => SaleStatus::Completed->value,
                'sold_at' => $soldAtUtc,
                'subtotal' => $subtotal->paisa,
                'item_discount_total' => $orderDiscount->isNegative() ? $discount->paisa : $lineDiscounts->paisa,
                'order_discount' => $orderDiscount->isNegative() ? 0 : $orderDiscount->paisa,
                'total' => $total->paisa,
                'paid' => $paid->paisa,
                'due' => $due->paisa,
                'cogs_total' => $cogs->paisa,
                'payment_status' => ($due->isZero() ? PaymentStatus::Paid : ($paid->isZero() ? PaymentStatus::Unpaid : PaymentStatus::Partial))->value,
                'courier_name' => Text::limit($courier, 100),
                'origin' => SaleOrigin::Import->value,
                'member_auth' => MemberAuth::None->value,
                'sold_by' => $soldBy ?? $fallbackUser,
                'legacy_id' => $id,
                'created_at' => $soldAtUtc,
                'updated_at' => $now,
            ];
        }

        DB::table('sales')->insert($sales);
        $saleIds = DB::table('sales')->where('tenant_id', $tenantId)->where('origin', SaleOrigin::Import->value)
            ->whereIn('legacy_id', $rows->pluck('id')->all())->pluck('id', 'legacy_id');

        $items = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $saleId = (int) $saleIds[$id];
            $context->ids->put(self::TABLE, (int) $row->store_id, $id, $saleId);
            $context->created();
            foreach ($lines[$id] ?? [] as $line) {
                unset($line['batch_id'], $line['unit_cost']);
                $items[] = ['sale_id' => $saleId] + $line;
            }
        }
        foreach (array_chunk($items, 1000) as $chunk) {
            DB::table('sale_items')->insert($chunk);
        }

        $itemIds = DB::table('sale_items')->whereIn('sale_id', $saleIds->values()->all())->pluck('id', 'legacy_id');
        $links = [];
        foreach ($lines as $saleLines) {
            foreach ($saleLines as $line) {
                if ($line['batch_id'] === null) {
                    continue;
                }
                $links[] = [
                    'sale_item_id' => (int) $itemIds[$line['legacy_id']],
                    'stock_batch_id' => $line['batch_id'],
                    'qty' => $line['qty'],
                    'unit_cost' => $line['unit_cost'],
                ];
            }
        }
        foreach (array_chunk($links, 1000) as $chunk) {
            DB::table('sale_item_batches')->insert($chunk);
        }
    }
}
