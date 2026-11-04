<?php

declare(strict_types=1);

namespace App\Support\Pos;

use App\Enums\PaymentDirection;
use App\Enums\SaleStatus;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use App\Support\Sales\BusinessDay;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Today's takings at the register (current branch, tenant business day): the sales of the chosen
 * register, or the cashier's own sales when no register is picked, and money received by method.
 */
final readonly class TodaySummary
{
    public function __construct(private TenantContext $context) {}

    /** @return array<string, mixed> */
    public function for(User $cashier, ?int $registerId): array
    {
        $timezone = BusinessDay::timezone($this->context->tenant());
        $from = CarbonImmutable::now($timezone)->startOfDay()->utc();
        $to = $from->addDay();

        /** @param Builder<Sale>|Builder<Payment> $q */
        $own = static fn (Builder $q, string $userColumn) => $registerId === null
            ? $q->where($userColumn, $cashier->id)
            : $q->where('register_id', $registerId);

        $sales = Sale::query()->where('status', SaleStatus::Completed)->whereBetween('sold_at', [$from, $to]);
        $own($sales, 'sold_by');
        $totals = (clone $sales)->selectRaw('count(*) as n, coalesce(sum(total), 0) as total, coalesce(sum(paid), 0) as paid, coalesce(sum(due), 0) as due')->toBase()->first();

        $payments = Payment::query()->where('direction', PaymentDirection::In)->whereNull('voided_at')->whereBetween('paid_at', [$from, $to]);
        $own($payments, 'received_by');
        $byMethod = $payments->selectRaw('payment_method_id, sum(amount) as amount')->groupBy('payment_method_id')->toBase()->get();
        $names = PaymentMethod::query()->whereIn('id', $byMethod->pluck('payment_method_id')->all())->pluck('name', 'id');

        $recent = (clone $sales)->latest('sold_at')->limit(10)->get(['id', 'uuid', 'number', 'total', 'due', 'sold_at']);

        return [
            'count' => (int) ($totals->n ?? 0),
            'total' => (int) ($totals->total ?? 0),
            'paid' => (int) ($totals->paid ?? 0),
            'due' => (int) ($totals->due ?? 0),
            'methods' => $byMethod->map(static fn (object $row): array => [
                'name' => (string) ($names[$row->payment_method_id] ?? ''),
                'amount' => (int) $row->amount,
            ])->values()->all(),
            'recent' => $recent->map(static fn (Sale $sale): array => [
                'id' => $sale->id,
                'uuid' => $sale->uuid,
                'number' => $sale->number,
                'total' => $sale->total->paisa,
                'due' => $sale->due->paisa,
                'sold_at' => $sale->sold_at->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
