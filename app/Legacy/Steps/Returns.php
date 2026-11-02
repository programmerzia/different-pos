<?php

declare(strict_types=1);

namespace App\Legacy\Steps;

use App\Enums\RefundMode;
use App\Legacy\ImportContext;
use App\Legacy\NumberBook;
use App\Legacy\Transform\LegacyDate;
use App\Legacy\Transform\Text;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/**
 * Step 12: legacy returns -> sale_returns (refund_mode refund) + sale_return_items (restock). Linked to
 * the sale through the details' sales_invoice_id. Stock is not moved: the legacy batch remainders the
 * import starts from already include restocked units.
 */
final readonly class Returns implements Step
{
    public const string TABLE = 'store_return_invoice_list';

    public const string DETAILS = 'store_return_invoice_details';

    public function name(): string
    {
        return 'Returns';
    }

    public function run(ImportContext $context): void
    {
        $numbers = new NumberBook('sale_returns', $context->tenantId());

        $context->select(self::TABLE, ['id', 'store_id', 'created_at', 'created_by'], ['invoice_no'], ['total_amount', 'total_discount'])
            ->whereIn('store_id', $context->stores())
            ->orderBy('id')
            ->chunk($context->options->chunk, function (Collection $rows) use ($context, $numbers): void {
                $details = $context->select(self::DETAILS, ['id', 'invoice_id', 'sales_invoice_id', 'product_id', 'quantity'], [], ['price', 'discount'])
                    ->whereIn('invoice_id', $rows->pluck('id')->all())->orderBy('id')->get()->groupBy('invoice_id');

                foreach ($rows as $row) {
                    if ($context->ids->has(self::TABLE, (int) $row->id)) {
                        $context->skipped();

                        continue;
                    }
                    $this->import($context, $row, $details->get($row->id, collect()), $numbers);
                }
            });
    }

    /** @param Collection<int, stdClass> $details */
    private function import(ImportContext $context, stdClass $row, Collection $details, NumberBook $numbers): void
    {
        $id = (int) $row->id;
        $legacySale = (int) ($details->first()->sales_invoice_id ?? 0);
        $saleId = $context->ids->get(Sales::TABLE, $legacySale);
        $sale = $saleId === null ? null : DB::table('sales')->where('id', $saleId)->first(['id', 'branch_id', 'customer_id']);
        if ($sale === null) {
            $context->report->add('orphan_return', self::TABLE, $id, "Return refers to sale #{$legacySale}, which was not imported; skipped");
            $context->skipped();

            return;
        }

        $subtotal = Money::parseRounded((string) ($row->total_amount ?? '0'));
        $total = $subtotal->minus(Money::parseRounded((string) ($row->total_discount ?? '0')));
        $returnedAt = LegacyDate::utc($row->created_at) ?? $context->now();
        $branch = DB::table('branches')->where('id', $sale->branch_id)->first(['id', 'code']);

        $returnId = DB::table('sale_returns')->insertGetId([
            'uuid' => (string) Str::uuid7(),
            'tenant_id' => $context->tenantId(),
            'branch_id' => (int) $sale->branch_id,
            'number' => $numbers->claim($context, Text::orNull($context->text($row->invoice_no, self::TABLE, $id, 'invoice_no')), (int) $sale->branch_id, (string) $branch?->code, self::TABLE, $id),
            'sale_id' => (int) $sale->id,
            'customer_id' => (int) $sale->customer_id,
            'returned_at' => $returnedAt,
            'subtotal' => $subtotal->paisa,
            'total' => max(0, $total->paisa),
            'refund_mode' => RefundMode::Refund->value,
            'refunded' => max(0, $total->paisa),
            'reason' => 'other',
            'note' => 'Imported from the legacy POS',
            'legacy_id' => $id,
            'created_by' => $context->ids->get(Users::TABLE, (int) $row->created_by) ?? $context->lookup('fallback_user'),
            'created_at' => $returnedAt,
            'updated_at' => $context->now(),
        ]);

        foreach ($details as $detail) {
            $qty = Quantity::ofUnits(max(0, (int) $detail->quantity));
            $item = DB::table('sale_items')->where('sale_id', $sale->id)
                ->where('product_id', $context->ids->get(Products::TABLE, (int) $detail->product_id))
                ->orderBy('id')->first(['id', 'product_id', 'qty', 'qty_returned', 'cogs']);
            if ($item === null || $qty->isZero()) {
                $context->report->add('unmatched_return_line', self::DETAILS, (int) $detail->id, 'No matching sale line (or quantity 0); line skipped');

                continue;
            }

            $sold = Quantity::parse((string) $item->qty);
            $already = Quantity::parse((string) $item->qty_returned);
            if ($already->plus($qty)->compareTo($sold) > 0) {
                $context->report->add('return_exceeds_sale', self::DETAILS, (int) $detail->id, 'Returned quantity exceeds the sold quantity; capped');
                $qty = $sold->minus($already);
                if (! $qty->isPositive()) {
                    continue;
                }
            }

            $price = Money::parseRounded((string) ($detail->price ?? '0'));
            $cogs = Money::ofPaisa((int) $item->cogs)->allocate([$qty->milli, max(0, $sold->milli - $qty->milli)])[0];
            DB::table('sale_return_items')->insert([
                'sale_return_id' => $returnId,
                'sale_item_id' => (int) $item->id,
                'product_id' => (int) $item->product_id,
                'qty' => $qty->toDecimalString(),
                'unit_price' => $price->paisa,
                'line_total' => $price->times($qty)->minus(Money::parseRounded((string) ($detail->discount ?? '0')))->paisa,
                'restock' => true,
                'cogs_reversed' => $cogs->paisa,
                'legacy_id' => (int) $detail->id,
            ]);
            DB::table('sale_items')->where('id', $item->id)->update(['qty_returned' => $already->plus($qty)->toDecimalString()]);
        }

        DB::table('sales')->where('id', $sale->id)->increment('returned_total', max(0, $total->paisa));
        $context->ids->put(self::TABLE, (int) $row->store_id, $id, $returnId);
        $context->created();
    }
}
