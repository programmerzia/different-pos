<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Due collections per receiving user: customer payments taken after the sale (CollectCustomerPayment),
 * not the tenders taken while selling. A tender is a payment allocated to a sale made at the very
 * same moment (sales.sold_at = payments.paid_at), the rule the dashboard's "due collections" uses, so
 * the two agree. Money in only, not voided, store credit methods excluded; refunds (direction out)
 * never count.
 */
final class CollectionsByUser extends AbstractReport
{
    private const array NOT_MONEY = ['customer_credit', 'exchange_credit'];

    public function name(): string
    {
        return 'collections-by-user';
    }

    public function group(): string
    {
        return 'money';
    }

    public function filters(): array
    {
        return ['cashier'];
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $query = DB::table('payments')
            ->join('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->join('users', 'users.id', '=', 'payments.received_by')
            ->where('payments.tenant_id', $this->tenantId())
            ->where('payment_methods.tenant_id', $this->tenantId())
            ->where('users.tenant_id', $this->tenantId())
            ->where('payments.party_type', 'customer')
            ->where('payments.direction', 'in')
            ->whereNull('payments.voided_at')
            ->whereNotIn('payment_methods.type', self::NOT_MONEY)
            ->where('payments.paid_at', '>=', $parameters->from()->format('Y-m-d H:i:s'))
            ->where('payments.paid_at', '<', $parameters->toExclusive()->format('Y-m-d H:i:s'))
            ->whereNotExists(static function (Builder $q): void {
                $q->selectRaw('1')
                    ->from('payment_allocations')
                    ->join('sales', 'sales.id', '=', 'payment_allocations.allocatable_id')
                    ->whereColumn('payment_allocations.payment_id', 'payments.id')
                    ->where('payment_allocations.allocatable_type', 'sale')
                    ->whereColumn('sales.sold_at', 'payments.paid_at');
            });
        $this->branches($query, $parameters, 'payments.branch_id');
        if (($cashier = $parameters->filter('cashier')) !== null && ctype_digit($cashier)) {
            $query->where('payments.received_by', (int) $cashier);
        }

        $records = $query
            ->groupBy('users.id', 'users.name')
            ->select(
                'users.name',
                DB::raw('COUNT(*) AS collections'),
                DB::raw('SUM(payments.amount) AS amount'),
                DB::raw('COUNT(DISTINCT payments.party_id) AS customers'),
            )
            ->orderByRaw('SUM(payments.amount) DESC')
            ->orderBy('users.id')
            ->get();

        $rows = [];
        foreach ($records as $r) {
            $count = $this->intOf($r->collections);
            $amount = $this->intOf($r->amount);
            $rows[] = [
                'user' => $this->text($r->name),
                'collections' => $count,
                'customers' => $this->intOf($r->customers),
                'amount' => $amount,
                'average' => $this->average($amount, $count),
            ];
        }

        $columns = [
            new ReportColumn('user', 'User', ColumnType::Text),
            new ReportColumn('collections', 'Collections', ColumnType::Int),
            new ReportColumn('customers', 'Customers', ColumnType::Int),
            new ReportColumn('amount', 'Amount', ColumnType::Money),
            new ReportColumn('average', 'Average collection', ColumnType::Money),
        ];

        $totals = $this->totals($columns, $rows);
        unset($totals['customers']); // a customer may pay several users
        $totals['average'] = $this->average($totals['amount'], $totals['collections']);

        return new ReportResult($columns, $rows, $totals);
    }
}
