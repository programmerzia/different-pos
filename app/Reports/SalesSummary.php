<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Facades\DB;

/**
 * The period in one row: invoices, gross sales, discounts, tax, invoiced total, paid, due, returns
 * and net sales after returns. Same definitions as sales-by-day (completed sales only).
 */
final class SalesSummary extends AbstractReport
{
    public function name(): string
    {
        return 'sales-summary';
    }

    public function group(): string
    {
        return 'sales';
    }

    public function filters(): array
    {
        return ['cashier'];
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $query = $this->sales($parameters);
        if (($cashier = $parameters->filter('cashier')) !== null && ctype_digit($cashier)) {
            $query->where('sales.sold_by', (int) $cashier);
        }

        $r = $query->select(
            DB::raw('COUNT(*) AS invoices'),
            DB::raw('COALESCE(SUM(sales.subtotal), 0) AS gross'),
            DB::raw('COALESCE(SUM(sales.item_discount_total + sales.order_discount), 0) AS discounts'),
            DB::raw('COALESCE(SUM(sales.tax_total), 0) AS tax'),
            DB::raw('COALESCE(SUM(sales.total), 0) AS total'),
            DB::raw('COALESCE(SUM(sales.paid), 0) AS paid'),
            DB::raw('COALESCE(SUM(sales.due), 0) AS due'),
            DB::raw('COALESCE(SUM(sales.returned_total), 0) AS returned'),
        )->first();

        $total = $r === null ? 0 : $this->intOf($r->total);
        $returned = $r === null ? 0 : $this->intOf($r->returned);
        $rows = [[
            'invoices' => $r === null ? 0 : $this->intOf($r->invoices),
            'gross' => $r === null ? 0 : $this->intOf($r->gross),
            'discounts' => $r === null ? 0 : $this->intOf($r->discounts),
            'tax' => $r === null ? 0 : $this->intOf($r->tax),
            'total' => $total,
            'paid' => $r === null ? 0 : $this->intOf($r->paid),
            'due' => $r === null ? 0 : $this->intOf($r->due),
            'returned' => $returned,
            'net' => $total - $returned,
        ]];

        $columns = [
            new ReportColumn('invoices', 'Invoices', ColumnType::Int),
            new ReportColumn('gross', 'Gross sales', ColumnType::Money),
            new ReportColumn('discounts', 'Discounts', ColumnType::Money),
            new ReportColumn('tax', 'Tax', ColumnType::Money),
            new ReportColumn('total', 'Invoiced', ColumnType::Money),
            new ReportColumn('paid', 'Paid', ColumnType::Money),
            new ReportColumn('due', 'Open due', ColumnType::Money),
            new ReportColumn('returned', 'Returned', ColumnType::Money),
            new ReportColumn('net', 'Net sales', ColumnType::Money),
        ];

        // One row is its own total: no totals line.
        return new ReportResult($columns, $rows, []);
    }
}
