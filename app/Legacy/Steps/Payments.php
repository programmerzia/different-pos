<?php

declare(strict_types=1);

namespace App\Legacy\Steps;

use App\Enums\AllocatableType;
use App\Enums\PartyType;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethodType;
use App\Enums\SaleOrigin;
use App\Legacy\ImportContext;
use App\Legacy\Transform\LegacyDate;
use App\Models\PaymentMethod;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/**
 * Step 11: store_sales_invoice_payment_list -> cash payments + allocations. Then every imported sale
 * whose allocations fall short of its paid amount (total − due, the receivable truth) gets one
 * synthesised cash payment for the gap at the sale date: "Paid" invoices without payment rows, and
 * balancing payments where legacy edits deleted history (ADR 0002 M3). Never a negative payment.
 * Payment numbers are derived from legacy ids ("L-P{id}", "L-S{sale id}"), so they are stable.
 */
final readonly class Payments implements Step
{
    public const string TABLE = 'store_sales_invoice_payment_list';

    public const string SYNTHETIC = 'synthetic_payment';

    public function name(): string
    {
        return 'Payments';
    }

    public function run(ImportContext $context): void
    {
        $cash = PaymentMethod::query()->where('type', PaymentMethodType::Cash->value)->orderBy('id')->first();
        if (! $cash instanceof PaymentMethod) {
            $cash = PaymentMethod::query()->create(['name' => 'Cash', 'type' => PaymentMethodType::Cash, 'is_active' => true, 'sort_order' => 0]);
            $context->created();
        }

        $sales = $context->ids->all(Sales::TABLE);
        foreach (array_chunk(array_keys($sales), max(1, $context->options->chunk)) as $legacySaleIds) {
            $rows = $context->select(self::TABLE, ['id', 'invoice_id', 'created_at', 'created_by'], [], ['paid_amount'])
                ->whereIn('invoice_id', $legacySaleIds)->orderBy('id')->get();
            $all = $rows->count();
            $rows = $rows->reject(fn (stdClass $row): bool => $context->ids->has(self::TABLE, (int) $row->id))->values();
            $context->skipped($all - $rows->count());
            if ($rows->isNotEmpty()) {
                $this->legacyPayments($context, array_values($rows->all()), $sales, $cash->id);
            }
        }

        $this->synthesise($context, $cash->id);
    }

    /**
     * @param  list<stdClass>  $rows
     * @param  array<int, int>  $sales  legacy sale id => sale id
     */
    private function legacyPayments(ImportContext $context, array $rows, array $sales, int $cashId): void
    {
        $saleRows = DB::table('sales')->whereIn('id', array_map(fn (stdClass $row): int => $sales[(int) $row->invoice_id], $rows))
            ->get(['id', 'branch_id', 'customer_id', 'register_id', 'sold_by'])->keyBy('id');

        $payments = [];
        $allocations = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $amount = Money::parseRounded((string) ($row->paid_amount ?? '0'));
            $sale = $saleRows->get($sales[(int) $row->invoice_id]);
            if ($sale !== null && ! $amount->isNegative() && ! $amount->isZero()) {
                $number = 'L-P'.$id;
                $payments[$number] = $this->payment($context, $sale, $cashId, $number, $amount, LegacyDate::utc($row->created_at), $id, null)
                    + ['received_by' => $context->ids->get(Users::TABLE, (int) $row->created_by) ?? (int) $sale->sold_by];
                $allocations[$number] = ['sale_id' => (int) $sale->id, 'amount' => $amount->paisa, 'legacy_id' => $id];
            } else {
                $context->report->add('payment_not_positive', self::TABLE, $id, 'Payment amount '.$amount->toDecimalString().' is not positive; skipped');
                $context->skipped();
            }
        }

        $this->insert($context, $payments, $allocations, self::TABLE);
    }

    /** One cash payment per imported sale whose allocations fall short of sales.paid. */
    private function synthesise(ImportContext $context, int $cashId): void
    {
        $tenantId = $context->tenantId();
        $gaps = DB::table('sales')
            ->leftJoin('payment_allocations', function ($join): void {
                $join->on('payment_allocations.allocatable_id', '=', 'sales.id')->where('payment_allocations.allocatable_type', AllocatableType::Sale->value);
            })
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.origin', SaleOrigin::Import->value)
            ->groupBy('sales.id', 'sales.legacy_id', 'sales.branch_id', 'sales.customer_id', 'sales.register_id', 'sales.sold_by', 'sales.sold_at', 'sales.paid')
            ->havingRaw('sales.paid <> COALESCE(SUM(payment_allocations.amount), 0)')
            ->orderBy('sales.id')
            ->get(['sales.id', 'sales.legacy_id', 'sales.branch_id', 'sales.customer_id', 'sales.register_id', 'sales.sold_by', 'sales.sold_at', 'sales.paid', DB::raw('COALESCE(SUM(payment_allocations.amount), 0) AS allocated'), DB::raw('COUNT(payment_allocations.id) AS allocations')]);

        $payments = [];
        $allocations = [];
        $withoutRows = 0;
        foreach ($gaps as $sale) {
            $gap = Money::ofPaisa((int) $sale->paid)->minus(Money::ofPaisa((int) $sale->allocated));
            if ($gap->isNegative()) {
                $context->report->add('payments_exceed_paid', Sales::TABLE, (int) $sale->legacy_id, 'Legacy payments exceed total − due by '.$gap->abs()->toDecimalString().'; payments kept');

                continue;
            }

            $number = 'L-S'.$sale->legacy_id;
            if ((int) $sale->allocations === 0) {
                $withoutRows++;
                $note = 'Legacy payment (the invoice had no payment rows)';
            } else {
                $note = 'Legacy balancing payment';
                $context->report->add('balancing_payment', Sales::TABLE, (int) $sale->legacy_id, 'Payments fell short of total − due by '.$gap->toDecimalString().'; balancing cash payment added');
            }
            $payments[$number] = $this->payment($context, $sale, $cashId, $number, $gap, (string) $sale->sold_at, null, $note)
                + ['received_by' => (int) $sale->sold_by];
            $allocations[$number] = ['sale_id' => (int) $sale->id, 'amount' => $gap->paisa, 'legacy_id' => (int) $sale->legacy_id];
        }

        if ($withoutRows > 0) {
            $context->report->add('synthesised_payment', Sales::TABLE, null, "{$withoutRows} paid invoices had no payment rows; one cash payment of total − due was created for each at the invoice date");
        }

        $this->insert($context, $payments, $allocations, self::SYNTHETIC);
    }

    /** @return array<string, mixed> */
    private function payment(ImportContext $context, stdClass $sale, int $cashId, string $number, Money $amount, ?string $paidAt, ?int $legacyId, ?string $note): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'tenant_id' => $context->tenantId(),
            'branch_id' => (int) $sale->branch_id,
            'number' => $number,
            'direction' => PaymentDirection::In->value,
            'party_type' => PartyType::Customer->value,
            'party_id' => (int) $sale->customer_id,
            'payment_method_id' => $cashId,
            'amount' => $amount->paisa,
            'register_id' => $sale->register_id === null ? null : (int) $sale->register_id,
            'paid_at' => $paidAt ?? $context->now(),
            'note' => $note,
            'legacy_id' => $legacyId,
            'created_at' => $paidAt ?? $context->now(),
            'updated_at' => $context->now(),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $payments  number => row
     * @param  array<string, array{sale_id: int, amount: int, legacy_id: int}>  $allocations  number => allocation
     */
    private function insert(ImportContext $context, array $payments, array $allocations, string $entity): void
    {
        if ($payments === []) {
            return;
        }

        foreach (array_chunk(array_values($payments), 1000) as $chunk) {
            DB::table('payments')->insert($chunk);
        }

        $ids = [];
        foreach (array_chunk(array_keys($payments), 1000) as $numbers) {
            $ids += DB::table('payments')->where('tenant_id', $context->tenantId())->whereIn('number', $numbers)->pluck('id', 'number')->all();
        }

        $rows = [];
        foreach ($allocations as $number => $allocation) {
            $paymentId = (int) $ids[$number];
            $rows[] = ['payment_id' => $paymentId, 'allocatable_type' => AllocatableType::Sale->value, 'allocatable_id' => $allocation['sale_id'], 'amount' => $allocation['amount']];
            $context->ids->put($entity, 0, $allocation['legacy_id'], $paymentId);
            $context->created();
        }
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('payment_allocations')->insert($chunk);
        }
    }
}
