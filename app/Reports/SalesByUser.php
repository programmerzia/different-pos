<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Facades\DB;

final class SalesByUser extends AbstractReport
{
    public function name(): string
    {
        return 'sales-by-user';
    }

    public function group(): string
    {
        return 'sales';
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $records = $this->sales($parameters)
            ->join('users', 'users.id', '=', 'sales.sold_by')
            ->where('users.tenant_id', $this->tenantId())
            ->groupBy('users.id', 'users.name')
            ->select(
                'users.name',
                DB::raw('COUNT(*) AS invoices'),
                DB::raw('SUM(sales.total) AS total'),
                DB::raw('SUM(sales.returned_total) AS returned'),
            )
            ->orderByRaw('SUM(sales.total - sales.returned_total) DESC')
            ->orderBy('users.id')
            ->get();

        $rows = [];
        foreach ($records as $r) {
            $invoices = $this->intOf($r->invoices);
            $net = $this->intOf($r->total) - $this->intOf($r->returned);
            $rows[] = [
                'user' => $this->text($r->name),
                'invoices' => $invoices,
                'net' => $net,
                'average' => $this->average($net, $invoices),
            ];
        }

        $columns = [
            new ReportColumn('user', 'User', ColumnType::Text),
            new ReportColumn('invoices', 'Invoices', ColumnType::Int),
            new ReportColumn('net', 'Net sales', ColumnType::Money),
            new ReportColumn('average', 'Average bill', ColumnType::Money),
        ];

        $totals = $this->totals($columns, $rows);
        $totals['average'] = $this->average($totals['net'], $totals['invoices']);

        return new ReportResult($columns, $rows, $totals);
    }
}
