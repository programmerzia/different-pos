<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Facades\DB;

final class Returns extends AbstractReport
{
    public function name(): string
    {
        return 'returns';
    }

    public function group(): string
    {
        return 'sales';
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $query = DB::table('sale_returns')
            ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->join('customers', 'customers.id', '=', 'sale_returns.customer_id')
            ->where('sale_returns.tenant_id', $this->tenantId())
            ->where('sales.tenant_id', $this->tenantId())
            ->where('customers.tenant_id', $this->tenantId())
            ->where('sale_returns.returned_at', '>=', $parameters->from()->format('Y-m-d H:i:s'))
            ->where('sale_returns.returned_at', '<', $parameters->toExclusive()->format('Y-m-d H:i:s'));
        $this->branches($query, $parameters, 'sale_returns.branch_id');

        $records = $query
            ->selectRaw("DATE(CONVERT_TZ(sale_returns.returned_at, '+00:00', ?)) AS day", [$parameters->offset()])
            ->addSelect(
                'sale_returns.number',
                DB::raw('sales.number AS sale_number'),
                DB::raw('customers.name AS customer'),
                'sale_returns.refund_mode',
                'sale_returns.reason',
                'sale_returns.total',
                'sale_returns.refunded',
                'sale_returns.credited',
            )
            ->orderByDesc('sale_returns.returned_at')
            ->orderByDesc('sale_returns.id')
            ->get();

        $rows = [];
        foreach ($records as $r) {
            $rows[] = [
                'date' => $this->text($r->day),
                'number' => $this->text($r->number),
                'sale' => $this->text($r->sale_number),
                'customer' => $this->text($r->customer),
                'mode' => $this->text($r->refund_mode),
                'reason' => $this->text($r->reason),
                'total' => $this->intOf($r->total),
                'refunded' => $this->intOf($r->refunded),
                'credited' => $this->intOf($r->credited),
            ];
        }

        $columns = [
            new ReportColumn('date', 'Date', ColumnType::Date),
            new ReportColumn('number', 'Return no.', ColumnType::Text),
            new ReportColumn('sale', 'Invoice', ColumnType::Text),
            new ReportColumn('customer', 'Customer', ColumnType::Text),
            new ReportColumn('mode', 'Mode', ColumnType::Text, valuePrefix: 'reports.mode.'),
            new ReportColumn('reason', 'Reason', ColumnType::Text),
            new ReportColumn('total', 'Total', ColumnType::Money),
            new ReportColumn('refunded', 'Refunded', ColumnType::Money),
            new ReportColumn('credited', 'Credited', ColumnType::Money),
        ];

        return new ReportResult($columns, $rows, $this->totals($columns, $rows));
    }
}
