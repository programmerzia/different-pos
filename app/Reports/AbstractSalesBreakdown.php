<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Sales rolled up by one product attribute (category, brand, family). The measures are exactly
 * SalesByProduct's: units from sale_items.qty, sales = SUM(line_total), FIFO cost = SUM(cogs), so
 * the totals reconcile with that report. Products without the attribute fall into one "none" row.
 */
abstract class AbstractSalesBreakdown extends AbstractReport
{
    /** Row label for products that have no value for the attribute (English, like CSV headers). */
    abstract protected function noneLabel(): string;

    /** Column key and English header of the grouping column. */
    abstract protected function groupKey(): string;

    abstract protected function groupHeader(): string;

    /**
     * Adds the join of the grouping table and returns [id column, name column].
     *
     * @return array{string, string}
     */
    abstract protected function joinGroup(Builder $query): array;

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
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('products.tenant_id', $this->tenantId());

        if (($cashier = $parameters->filter('cashier')) !== null && ctype_digit($cashier)) {
            $query->where('sales.sold_by', (int) $cashier);
        }
        $this->narrow($query, $parameters);
        [$idColumn, $nameColumn] = $this->joinGroup($query);

        $records = $query
            ->groupBy($idColumn, $nameColumn)
            ->select(
                "{$nameColumn} as group_name",
                DB::raw('COUNT(DISTINCT products.id) AS products'),
                DB::raw('SUM(sale_items.qty) AS sold'),
                DB::raw('SUM(sale_items.qty_returned) AS returned'),
                DB::raw('SUM(sale_items.line_total) AS sales'),
                DB::raw('SUM(sale_items.cogs) AS cogs'),
            )
            ->orderByDesc('sales')
            ->orderBy($idColumn)
            ->get();

        $rows = [];
        foreach ($records as $r) {
            $sold = $this->milli($r->sold);
            $returned = $this->milli($r->returned);
            $sales = $this->intOf($r->sales);
            $cogs = $this->intOf($r->cogs);
            $rows[] = [
                $this->groupKey() => $this->text($r->group_name) ?? $this->noneLabel(),
                'products' => $this->intOf($r->products),
                'sold' => $sold,
                'returned' => $returned,
                'net_qty' => $sold - $returned,
                'sales' => $sales,
                'cogs' => $cogs,
                'profit' => $sales - $cogs,
                'margin_bp' => $this->bp($sales - $cogs, $sales),
            ];
        }

        $columns = [
            new ReportColumn($this->groupKey(), $this->groupHeader(), ColumnType::Text),
            new ReportColumn('products', 'Products sold', ColumnType::Int),
            new ReportColumn('sold', 'Qty sold', ColumnType::Qty),
            new ReportColumn('returned', 'Qty returned', ColumnType::Qty),
            new ReportColumn('net_qty', 'Net qty', ColumnType::Qty),
            new ReportColumn('sales', 'Sales', ColumnType::Money),
            new ReportColumn('cogs', 'COGS', ColumnType::Money, profit: true),
            new ReportColumn('profit', 'Gross profit', ColumnType::Money, profit: true),
            new ReportColumn('margin_bp', 'Margin %', ColumnType::Percent, profit: true),
        ];

        $totals = $this->totals($columns, $rows);
        // A product belongs to exactly one group, so the per-group product counts add up.
        $totals['margin_bp'] = $this->bp($totals['profit'], $totals['sales']);

        return new ReportResult($columns, $rows, $totals);
    }

    /** Applies the report's own category/brand filters. */
    protected function narrow(Builder $query, ReportParameters $parameters): void {}
}
