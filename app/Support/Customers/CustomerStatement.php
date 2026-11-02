<?php

declare(strict_types=1);

namespace App\Support\Customers;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Sale;
use App\Support\Money\Money;
use App\Support\Tenancy\BranchScope;
use Carbon\CarbonInterface;

/** A customer's completed sales in a period with column totals (paisa). Voided and held sales are left out. */
final readonly class CustomerStatement
{
    /**
     * @return array{rows: list<array{id: int, uuid: string, number: string|null, sold_at: string, items: int, total: int, paid: int, due: int, payment_status: string}>, totals: array{count: int, total: int, paid: int, due: int}}
     */
    public function build(Customer $customer, CarbonInterface $from, CarbonInterface $to, bool $allBranches = false): array
    {
        $query = Sale::query()
            ->where('customer_id', $customer->id)
            ->where('status', SaleStatus::Completed)
            ->whereBetween('sold_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->withCount('items')
            ->orderBy('sold_at')
            ->orderBy('id');
        if ($allBranches) {
            $query->withoutGlobalScope(BranchScope::class);
        }

        $rows = [];
        $total = Money::zero();
        $paid = Money::zero();
        $due = Money::zero();
        foreach ($query->get() as $sale) {
            $rows[] = [
                'id' => $sale->id,
                'uuid' => $sale->uuid,
                'number' => $sale->number,
                'sold_at' => $sale->sold_at->toIso8601String(),
                'items' => (int) $sale->getAttribute('items_count'),
                'total' => $sale->total->paisa,
                'paid' => $sale->paid->paisa,
                'due' => $sale->due->paisa,
                'payment_status' => $sale->payment_status->value,
            ];
            $total = $total->plus($sale->total);
            $paid = $paid->plus($sale->paid);
            $due = $due->plus($sale->due);
        }

        return ['rows' => $rows, 'totals' => ['count' => count($rows), 'total' => $total->paisa, 'paid' => $paid->paisa, 'due' => $due->paisa]];
    }
}
