<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Facades\DB;

/**
 * Returns and exchanges per reason in the period: count, quantity restocked vs written off
 * (sale_return_items.restock), value, refunds and credits, share of all returns, and the return rate
 * (return value ÷ completed sales of the same period and branches).
 */
final class ReturnsByReason extends AbstractReport
{
    public function name(): string
    {
        return 'returns-by-reason';
    }

    public function group(): string
    {
        return 'sales';
    }

    public function filters(): array
    {
        return ['reason'];
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $query = DB::table('sale_returns')
            ->join('sale_return_items', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
            ->where('sale_returns.tenant_id', $this->tenantId())
            ->where('sale_returns.returned_at', '>=', $parameters->from()->format('Y-m-d H:i:s'))
            ->where('sale_returns.returned_at', '<', $parameters->toExclusive()->format('Y-m-d H:i:s'));
        $this->branches($query, $parameters, 'sale_returns.branch_id');
        if (($reason = $parameters->filter('reason')) !== null) {
            $query->where('sale_returns.reason', $reason);
        }

        $records = $query
            ->groupBy('sale_returns.reason')
            ->select(
                'sale_returns.reason',
                DB::raw('COUNT(DISTINCT sale_returns.id) AS returns_count'),
                DB::raw('SUM(sale_return_items.qty) AS qty'),
                DB::raw('SUM(CASE WHEN sale_return_items.restock THEN sale_return_items.qty ELSE 0 END) AS restocked'),
                DB::raw('SUM(CASE WHEN sale_return_items.restock THEN 0 ELSE sale_return_items.qty END) AS written_off'),
                DB::raw('SUM(sale_return_items.line_total) AS total'),
            )
            ->orderByDesc('total')
            ->orderBy('sale_returns.reason')
            ->get();

        $money = DB::table('sale_returns')
            ->where('sale_returns.tenant_id', $this->tenantId())
            ->where('sale_returns.returned_at', '>=', $parameters->from()->format('Y-m-d H:i:s'))
            ->where('sale_returns.returned_at', '<', $parameters->toExclusive()->format('Y-m-d H:i:s'));
        $this->branches($money, $parameters, 'sale_returns.branch_id');
        $money = $money->groupBy('reason')
            ->select('reason', DB::raw('SUM(refunded) AS refunded'), DB::raw('SUM(credited) AS credited'))
            ->get()->keyBy('reason');

        $salesTotal = $this->intOf($this->sales($parameters)->sum('sales.total'));
        $grand = 0;
        foreach ($records as $r) {
            $grand += $this->intOf($r->total);
        }

        $rows = [];
        foreach ($records as $r) {
            $total = $this->intOf($r->total);
            $reason = (string) $r->reason;
            $rows[] = [
                'reason' => $reason,
                'returns_count' => $this->intOf($r->returns_count),
                'qty' => $this->milli($r->qty),
                'restocked' => $this->milli($r->restocked),
                'written_off' => $this->milli($r->written_off),
                'total' => $total,
                'refunded' => $this->intOf($money->get($reason)?->refunded),
                'credited' => $this->intOf($money->get($reason)?->credited),
                'share_bp' => $this->bp($total, $grand),
                'rate_bp' => $this->bp($total, $salesTotal),
            ];
        }

        $columns = [
            new ReportColumn('reason', 'Reason', ColumnType::Text, valuePrefix: 'returns.reason_'),
            new ReportColumn('returns_count', 'Returns', ColumnType::Int),
            new ReportColumn('qty', 'Qty returned', ColumnType::Qty),
            new ReportColumn('restocked', 'Restocked', ColumnType::Qty),
            new ReportColumn('written_off', 'Written off', ColumnType::Qty),
            new ReportColumn('total', 'Return value', ColumnType::Money),
            new ReportColumn('refunded', 'Refunded', ColumnType::Money),
            new ReportColumn('credited', 'Credited', ColumnType::Money),
            new ReportColumn('share_bp', 'Share of returns %', ColumnType::Percent),
            new ReportColumn('rate_bp', 'Return rate %', ColumnType::Percent),
        ];

        $totals = $this->totals($columns, $rows);
        $totals['share_bp'] = $rows === [] ? 0 : 10_000;
        $totals['rate_bp'] = $this->bp($grand, $salesTotal);

        return new ReportResult($columns, $rows, $totals);
    }
}
