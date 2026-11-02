<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Facades\DB;

/** Stock on hand per product and branch, valued at batch cost (cost columns need reports.profit). */
final class StockValuation extends AbstractReport
{
    public function name(): string
    {
        return 'stock-valuation';
    }

    public function group(): string
    {
        return 'inventory';
    }

    public function usesPeriod(): bool
    {
        return false;
    }

    public function filters(): array
    {
        return ['category', 'brand'];
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $query = DB::table('stock_batches')
            ->join('products', 'products.id', '=', 'stock_batches.product_id')
            ->join('branches', 'branches.id', '=', 'stock_batches.branch_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('stock_batches.tenant_id', $this->tenantId())
            ->where('products.tenant_id', $this->tenantId())
            ->where('branches.tenant_id', $this->tenantId())
            ->where('stock_batches.qty_remaining', '>', 0);
        $this->branches($query, $parameters, 'stock_batches.branch_id');

        if (($category = $parameters->filter('category')) !== null) {
            $query->where('products.category_id', (int) $category);
        }
        if (($brand = $parameters->filter('brand')) !== null) {
            $query->where('products.brand_id', (int) $brand);
        }

        $records = $query
            ->groupBy('branches.id', 'branches.name', 'products.id', 'products.sku', 'products.name', 'categories.name', 'products.sale_price')
            ->select(
                DB::raw('branches.name AS branch'),
                'products.sku',
                'products.name',
                DB::raw('categories.name AS category'),
                'products.sale_price',
                DB::raw('SUM(stock_batches.qty_remaining) AS qty'),
                DB::raw('SUM(ROUND(stock_batches.qty_remaining * stock_batches.unit_cost, 0)) AS cost_value'),
            )
            ->orderBy('branches.name')
            ->orderBy('products.name')
            ->orderBy('products.id')
            ->get();

        $rows = [];
        foreach ($records as $r) {
            $qty = $this->milli($r->qty);
            $rows[] = [
                'branch' => $this->text($r->branch),
                'sku' => $this->text($r->sku),
                'product' => $this->text($r->name),
                'category' => $this->text($r->category),
                'qty' => $qty,
                'cost_value' => $this->intOf($r->cost_value),
                'retail_value' => $this->retail($qty, $this->intOf($r->sale_price)),
            ];
        }

        $columns = [
            new ReportColumn('branch', 'Branch', ColumnType::Text),
            new ReportColumn('sku', 'SKU', ColumnType::Text),
            new ReportColumn('product', 'Product', ColumnType::Text),
            new ReportColumn('category', 'Category', ColumnType::Text),
            new ReportColumn('qty', 'Qty on hand', ColumnType::Qty),
            new ReportColumn('cost_value', 'Stock value (cost)', ColumnType::Money, profit: true),
            new ReportColumn('retail_value', 'Retail value', ColumnType::Money),
        ];

        return new ReportResult($columns, $rows, $this->totals($columns, $rows));
    }

    /** qty (milli) × price (paisa), rounded half-up to the paisa. */
    private function retail(int $milli, int $price): int
    {
        return intdiv($milli * $price + 500, 1000);
    }
}
