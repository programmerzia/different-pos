<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Facades\DB;

/**
 * Product sales velocity and margin: units sold and returned, net units per day of the period,
 * sales, FIFO cost of goods (sale_items.cogs, assigned by the stock ledger) and the gross margin.
 */
final class SalesByProduct extends AbstractReport
{
    public function name(): string
    {
        return 'sales-by-product';
    }

    public function group(): string
    {
        return 'sales';
    }

    public function filters(): array
    {
        return ['category', 'brand'];
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $query = $this->sales($parameters)
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->where('products.tenant_id', $this->tenantId());

        if (($category = $parameters->filter('category')) !== null) {
            $query->where('products.category_id', (int) $category);
        }
        if (($brand = $parameters->filter('brand')) !== null) {
            $query->where('products.brand_id', (int) $brand);
        }

        $records = $query
            ->groupBy('products.id', 'products.sku', 'products.name', 'categories.name', 'brands.name')
            ->select(
                'products.sku',
                'products.name',
                DB::raw('categories.name AS category'),
                DB::raw('brands.name AS brand'),
                DB::raw('SUM(sale_items.qty) AS sold'),
                DB::raw('SUM(sale_items.qty_returned) AS returned'),
                DB::raw('SUM(sale_items.line_total) AS sales'),
                DB::raw('SUM(sale_items.cogs) AS cogs'),
            )
            ->orderByDesc('sales')
            ->orderBy('products.id')
            ->get();

        $days = max(1, count($this->days($parameters)));
        $rows = [];
        foreach ($records as $r) {
            $sold = $this->milli($r->sold);
            $returned = $this->milli($r->returned);
            $sales = $this->intOf($r->sales);
            $cogs = $this->intOf($r->cogs);
            $rows[] = [
                'sku' => $this->text($r->sku),
                'product' => $this->text($r->name),
                'category' => $this->text($r->category),
                'brand' => $this->text($r->brand),
                'sold' => $sold,
                'returned' => $returned,
                'net_qty' => $sold - $returned,
                'velocity' => intdiv(2 * ($sold - $returned) + $days, 2 * $days),
                'sales' => $sales,
                'cogs' => $cogs,
                'profit' => $sales - $cogs,
                'margin_bp' => $this->bp($sales - $cogs, $sales),
            ];
        }

        $columns = [
            new ReportColumn('sku', 'SKU', ColumnType::Text),
            new ReportColumn('product', 'Product', ColumnType::Text),
            new ReportColumn('category', 'Category', ColumnType::Text),
            new ReportColumn('brand', 'Brand', ColumnType::Text),
            new ReportColumn('sold', 'Qty sold', ColumnType::Qty),
            new ReportColumn('returned', 'Qty returned', ColumnType::Qty),
            new ReportColumn('net_qty', 'Net qty', ColumnType::Qty),
            new ReportColumn('velocity', 'Units / day', ColumnType::Qty),
            new ReportColumn('sales', 'Sales', ColumnType::Money),
            new ReportColumn('cogs', 'COGS', ColumnType::Money, profit: true),
            new ReportColumn('profit', 'Gross profit', ColumnType::Money, profit: true),
            new ReportColumn('margin_bp', 'Margin %', ColumnType::Percent, profit: true),
        ];

        $totals = $this->totals($columns, $rows);
        $totals['margin_bp'] = $this->bp($totals['profit'], $totals['sales']);

        return new ReportResult($columns, $rows, $totals);
    }
}
