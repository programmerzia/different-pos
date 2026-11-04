<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Facades\DB;

/**
 * Per day: net sales, money collected (one column per payment method), refunds paid out and the
 * net cash. Customer-credit and exchange-credit tenders are not money in, so they are left out.
 */
final class DailyCashSummary extends AbstractReport
{
    private const array NOT_MONEY = ['customer_credit', 'exchange_credit'];

    public function name(): string
    {
        return 'daily-cash';
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
        $methods = DB::table('payment_methods')
            ->where('tenant_id', $this->tenantId())
            ->whereNotIn('type', self::NOT_MONEY)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name']);

        $paymentQuery = DB::table('payments')
            ->join('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->where('payments.tenant_id', $this->tenantId())
            ->where('payment_methods.tenant_id', $this->tenantId())
            ->where('payments.party_type', 'customer')
            ->whereNull('payments.voided_at')
            ->whereNotIn('payment_methods.type', self::NOT_MONEY)
            ->where('payments.paid_at', '>=', $parameters->from()->format('Y-m-d H:i:s'))
            ->where('payments.paid_at', '<', $parameters->toExclusive()->format('Y-m-d H:i:s'));
        $this->branches($paymentQuery, $parameters, 'payments.branch_id');
        $cashier = $this->cashierId($parameters);
        if ($cashier !== null) {
            $paymentQuery->where('payments.received_by', $cashier);
        }
        $payments = $paymentQuery
            ->groupBy('day', 'payments.payment_method_id', 'payments.direction')
            ->selectRaw("DATE(CONVERT_TZ(payments.paid_at, '+00:00', ?)) AS day", [$parameters->offset()])
            ->addSelect('payments.payment_method_id', 'payments.direction', DB::raw('SUM(payments.amount) AS amount'))
            ->get();

        $salesQuery = $this->sales($parameters);
        if ($cashier !== null) {
            $salesQuery->where('sales.sold_by', $cashier);
        }
        $sales = $salesQuery
            ->groupBy('day')
            ->selectRaw("DATE(CONVERT_TZ(sales.sold_at, '+00:00', ?)) AS day", [$parameters->offset()])
            ->addSelect(DB::raw('SUM(sales.total - sales.returned_total) AS net'))
            ->pluck('net', 'day');

        $in = [];
        $out = [];
        foreach ($payments as $p) {
            $amount = $this->intOf($p->amount);
            if ($p->direction === 'in') {
                $in[(string) $p->day][(int) $p->payment_method_id] = $amount;
            } else {
                $out[(string) $p->day] = ($out[(string) $p->day] ?? 0) + $amount;
            }
        }

        $rows = [];
        foreach ($this->days($parameters) as $date) {
            $row = ['date' => $date, 'sales' => $this->intOf($sales->get($date))];
            $collected = 0;
            foreach ($methods as $m) {
                $amount = $in[$date][(int) $m->id] ?? 0;
                $row['method_'.$m->id] = $amount;
                $collected += $amount;
            }
            $row['collections'] = $collected;
            $row['refunds'] = $out[$date] ?? 0;
            $row['net_cash'] = $collected - $row['refunds'];
            $rows[] = $row;
        }

        $columns = [
            new ReportColumn('date', 'Date', ColumnType::Date),
            new ReportColumn('sales', 'Net sales', ColumnType::Money),
        ];
        foreach ($methods as $m) {
            $columns[] = new ReportColumn('method_'.$m->id, (string) $m->name, ColumnType::Money);
        }
        $columns[] = new ReportColumn('collections', 'Collections', ColumnType::Money);
        $columns[] = new ReportColumn('refunds', 'Refunds', ColumnType::Money);
        $columns[] = new ReportColumn('net_cash', 'Net cash', ColumnType::Money);

        return new ReportResult($columns, $rows, $this->totals($columns, $rows));
    }
}
