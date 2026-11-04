<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Facades\DB;

final class SalesByDay extends AbstractReport
{
    public function name(): string
    {
        return 'sales-by-day';
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
        if (($cashier = $this->cashierId($parameters)) !== null) {
            $query->where('sales.sold_by', $cashier);
        }

        $records = $query
            ->selectRaw("DATE(CONVERT_TZ(sales.sold_at, '+00:00', ?)) AS day", [$parameters->offset()])
            ->addSelect(
                DB::raw('COUNT(*) AS invoices'),
                DB::raw('SUM(sales.subtotal) AS gross'),
                DB::raw('SUM(sales.item_discount_total + sales.order_discount) AS discounts'),
                DB::raw('SUM(sales.tax_total) AS tax'),
                DB::raw('SUM(sales.total) AS total'),
                DB::raw('SUM(sales.returned_total) AS returned'),
                DB::raw('SUM(sales.cogs_total) AS cogs'),
            )
            ->groupBy('day')
            ->get()
            ->keyBy(fn (object $r): string => (string) $r->day);

        $rows = [];
        foreach ($this->days($parameters) as $date) {
            $r = $records->get($date);
            $total = $r === null ? 0 : $this->intOf($r->total);
            $returned = $r === null ? 0 : $this->intOf($r->returned);
            $tax = $r === null ? 0 : $this->intOf($r->tax);
            $rows[] = [
                'date' => $date,
                'invoices' => $r === null ? 0 : $this->intOf($r->invoices),
                'gross' => $r === null ? 0 : $this->intOf($r->gross),
                'discounts' => $r === null ? 0 : $this->intOf($r->discounts),
                'tax' => $tax,
                'total' => $total,
                'returned' => $returned,
                'net' => $total - $returned,
                'profit' => $r === null ? 0 : $total - $tax - $this->intOf($r->cogs),
            ];
        }

        $columns = [
            new ReportColumn('date', 'Date', ColumnType::Date),
            new ReportColumn('invoices', 'Invoices', ColumnType::Int),
            new ReportColumn('gross', 'Gross sales', ColumnType::Money),
            new ReportColumn('discounts', 'Discounts', ColumnType::Money),
            new ReportColumn('tax', 'Tax', ColumnType::Money),
            new ReportColumn('total', 'Invoiced', ColumnType::Money),
            new ReportColumn('returned', 'Returned', ColumnType::Money),
            new ReportColumn('net', 'Net sales', ColumnType::Money),
            new ReportColumn('profit', 'Gross profit (before returns)', ColumnType::Money, profit: true),
        ];

        return new ReportResult($columns, $rows, $this->totals($columns, $rows));
    }
}
