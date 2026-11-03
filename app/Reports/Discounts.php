<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Facades\DB;

/**
 * Discounts granted per cashier: invoices, discounted invoices, gross value, line + group discounts
 * (sale_items), order discounts (sales.order_discount) and the effective discount rate. Sales store
 * no discount reason yet, so the breakdown is by cashier and discount kind only.
 */
final class Discounts extends AbstractReport
{
    public function name(): string
    {
        return 'discounts';
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
        $query = $this->sales($parameters)
            ->join('users', 'users.id', '=', 'sales.sold_by')
            ->where('users.tenant_id', $this->tenantId());
        if (($cashier = $parameters->filter('cashier')) !== null && ctype_digit($cashier)) {
            $query->where('sales.sold_by', (int) $cashier);
        }

        $records = $query
            ->groupBy('users.id', 'users.name')
            ->select(
                'users.name',
                DB::raw('COUNT(*) AS invoices'),
                DB::raw('SUM(CASE WHEN sales.item_discount_total + sales.order_discount > 0 THEN 1 ELSE 0 END) AS discounted'),
                DB::raw('SUM(sales.subtotal) AS gross'),
                DB::raw('SUM(sales.item_discount_total) AS line_discounts'),
                DB::raw('SUM(sales.order_discount) AS order_discounts'),
            )
            ->orderByRaw('SUM(sales.item_discount_total + sales.order_discount) DESC')
            ->orderBy('users.id')
            ->get();

        $rows = [];
        foreach ($records as $r) {
            $line = $this->intOf($r->line_discounts);
            $order = $this->intOf($r->order_discounts);
            $gross = $this->intOf($r->gross);
            $rows[] = [
                'user' => $this->text($r->name),
                'invoices' => $this->intOf($r->invoices),
                'discounted' => $this->intOf($r->discounted),
                'gross' => $gross,
                'line_discounts' => $line,
                'order_discounts' => $order,
                'discounts' => $line + $order,
                'discount_bp' => $this->bp($line + $order, $gross),
            ];
        }

        $columns = [
            new ReportColumn('user', 'Cashier', ColumnType::Text),
            new ReportColumn('invoices', 'Invoices', ColumnType::Int),
            new ReportColumn('discounted', 'Discounted invoices', ColumnType::Int),
            new ReportColumn('gross', 'Gross sales', ColumnType::Money),
            new ReportColumn('line_discounts', 'Line & group discounts', ColumnType::Money),
            new ReportColumn('order_discounts', 'Order discounts', ColumnType::Money),
            new ReportColumn('discounts', 'Total discounts', ColumnType::Money),
            new ReportColumn('discount_bp', 'Discount %', ColumnType::Percent),
        ];

        $totals = $this->totals($columns, $rows);
        $totals['discount_bp'] = $this->bp($totals['discounts'], $totals['gross']);

        return new ReportResult($columns, $rows, $totals);
    }
}
