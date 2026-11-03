<?php

declare(strict_types=1);

namespace App\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Stock on hand (per branch) with no completed sale in the last 90 days: quantity, FIFO cost value
 * (reports.profit), retail value, the last sale date and the idle days. Capital to mark down.
 */
final class DeadStock extends AbstractReport
{
    public const int IDLE_DAYS = 90;

    public function name(): string
    {
        return 'dead-stock';
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
        $today = $parameters->today();
        $cutoff = $today->subDays(self::IDLE_DAYS)->utc()->format('Y-m-d H:i:s');

        $lastSale = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.tenant_id', $this->tenantId())
            ->where('sales.status', 'completed')
            ->groupBy('sales.branch_id', 'sale_items.product_id')
            ->select('sales.branch_id', 'sale_items.product_id', DB::raw('MAX(sales.sold_at) AS last_sold_at'));
        $this->branches($lastSale, $parameters, 'sales.branch_id');

        $batches = DB::table('stock_batches')
            ->where('tenant_id', $this->tenantId())
            ->where('qty_remaining', '>', 0)
            ->groupBy('branch_id', 'product_id')
            ->select('branch_id', 'product_id', DB::raw('SUM(ROUND(qty_remaining * unit_cost, 0)) AS cost_value'));
        $this->branches($batches, $parameters, 'branch_id');

        $query = DB::table('branch_stocks')
            ->join('products', 'products.id', '=', 'branch_stocks.product_id')
            ->join('branches', 'branches.id', '=', 'branch_stocks.branch_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoinSub($lastSale, 'ls', fn ($join) => $join->on('ls.branch_id', '=', 'branch_stocks.branch_id')->on('ls.product_id', '=', 'branch_stocks.product_id'))
            ->leftJoinSub($batches, 'b', fn ($join) => $join->on('b.branch_id', '=', 'branch_stocks.branch_id')->on('b.product_id', '=', 'branch_stocks.product_id'))
            ->where('branch_stocks.tenant_id', $this->tenantId())
            ->where('products.tenant_id', $this->tenantId())
            ->where('branches.tenant_id', $this->tenantId())
            ->whereNull('products.deleted_at')
            ->where('branch_stocks.qty_on_hand', '>', 0)
            ->where(function ($q) use ($cutoff): void {
                $q->whereNull('ls.last_sold_at')->orWhere('ls.last_sold_at', '<', $cutoff);
            });
        $this->branches($query, $parameters, 'branch_stocks.branch_id');

        if (($category = $parameters->filter('category')) !== null) {
            $query->where('products.category_id', (int) $category);
        }
        if (($brand = $parameters->filter('brand')) !== null) {
            $query->where('products.brand_id', (int) $brand);
        }

        $records = $query
            ->select(
                DB::raw('branches.name AS branch'),
                'products.sku',
                'products.name',
                DB::raw('categories.name AS category'),
                'branch_stocks.qty_on_hand',
                'products.sale_price',
                DB::raw('COALESCE(b.cost_value, 0) AS cost_value'),
                'ls.last_sold_at',
            )
            ->selectRaw("DATE(CONVERT_TZ(ls.last_sold_at, '+00:00', ?)) AS last_sold", [$parameters->offset()])
            ->orderByDesc('cost_value')
            ->orderBy('products.id')
            ->get();

        $rows = [];
        foreach ($records as $r) {
            $qty = $this->milli($r->qty_on_hand);
            $lastSold = $this->text($r->last_sold);
            $rows[] = [
                'branch' => $this->text($r->branch),
                'sku' => $this->text($r->sku),
                'product' => $this->text($r->name),
                'category' => $this->text($r->category),
                'on_hand' => $qty,
                'cost_value' => $this->intOf($r->cost_value),
                'retail_value' => intdiv($qty * $this->intOf($r->sale_price) + 500, 1000),
                'last_sold' => $lastSold,
                'idle_days' => $lastSold === null ? null : (int) CarbonImmutable::parse($lastSold, $parameters->timezone)->diffInDays($today, true),
            ];
        }

        $columns = [
            new ReportColumn('branch', 'Branch', ColumnType::Text),
            new ReportColumn('sku', 'SKU', ColumnType::Text),
            new ReportColumn('product', 'Product', ColumnType::Text),
            new ReportColumn('category', 'Category', ColumnType::Text),
            new ReportColumn('on_hand', 'On hand', ColumnType::Qty),
            new ReportColumn('cost_value', 'Stock value (cost)', ColumnType::Money, profit: true),
            new ReportColumn('retail_value', 'Retail value', ColumnType::Money),
            new ReportColumn('last_sold', 'Last sold', ColumnType::Date),
            new ReportColumn('idle_days', 'Idle days', ColumnType::Int),
        ];

        $totals = $this->totals($columns, $rows);
        unset($totals['idle_days']);

        return new ReportResult($columns, $rows, $totals);
    }
}
