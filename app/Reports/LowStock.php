<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Facades\DB;

/**
 * Stocked products (per branch) at or below their reorder level, or holding less than the last 30
 * days' net sales. Run rate = net units sold in the last 30 days ÷ 30; days of cover = on hand ÷ run
 * rate; suggested order = the larger of the reorder level and the 30-day sales, less what is on hand.
 */
final class LowStock extends AbstractReport
{
    public const int WINDOW_DAYS = 30;

    public function name(): string
    {
        return 'low-stock';
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
        $since = $parameters->today()->subDays(self::WINDOW_DAYS)->utc()->format('Y-m-d H:i:s');

        $sold = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.tenant_id', $this->tenantId())
            ->where('sales.status', 'completed')
            ->where('sales.sold_at', '>=', $since)
            ->groupBy('sales.branch_id', 'sale_items.product_id')
            ->select('sales.branch_id', 'sale_items.product_id', DB::raw('SUM(sale_items.qty - sale_items.qty_returned) AS net_qty'));
        $this->branches($sold, $parameters, 'sales.branch_id');

        $query = DB::table('branch_stocks')
            ->join('products', 'products.id', '=', 'branch_stocks.product_id')
            ->join('branches', 'branches.id', '=', 'branch_stocks.branch_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoinSub($sold, 's', fn ($join) => $join->on('s.branch_id', '=', 'branch_stocks.branch_id')->on('s.product_id', '=', 'branch_stocks.product_id'))
            ->where('branch_stocks.tenant_id', $this->tenantId())
            ->where('products.tenant_id', $this->tenantId())
            ->where('branches.tenant_id', $this->tenantId())
            ->where('products.track_stock', true)
            ->where('products.is_active', true)
            ->whereNull('products.deleted_at')
            ->where(function ($q): void {
                $q->whereColumn('branch_stocks.qty_on_hand', '<=', 'products.reorder_level')
                    ->orWhereColumn('branch_stocks.qty_on_hand', '<', DB::raw('COALESCE(s.net_qty, 0)'));
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
                'products.reorder_level',
                DB::raw('COALESCE(s.net_qty, 0) AS sold_30d'),
            )
            ->orderBy('branches.name')
            ->orderBy('products.name')
            ->orderBy('products.id')
            ->get();

        $rows = [];
        foreach ($records as $r) {
            $onHand = $this->milli($r->qty_on_hand);
            $reorder = $this->milli($r->reorder_level);
            $sold = max(0, $this->milli($r->sold_30d));
            $runRate = intdiv(2 * $sold + self::WINDOW_DAYS, 2 * self::WINDOW_DAYS);
            $rows[] = [
                'branch' => $this->text($r->branch),
                'sku' => $this->text($r->sku),
                'product' => $this->text($r->name),
                'category' => $this->text($r->category),
                'on_hand' => $onHand,
                'reorder_level' => $reorder,
                'sold_30d' => $sold,
                'run_rate' => $runRate,
                'days_cover' => $runRate === 0 ? null : intdiv(max(0, $onHand), $runRate),
                'suggested' => max(0, max($reorder, $sold) - $onHand),
            ];
        }

        $columns = [
            new ReportColumn('branch', 'Branch', ColumnType::Text),
            new ReportColumn('sku', 'SKU', ColumnType::Text),
            new ReportColumn('product', 'Product', ColumnType::Text),
            new ReportColumn('category', 'Category', ColumnType::Text),
            new ReportColumn('on_hand', 'On hand', ColumnType::Qty),
            new ReportColumn('reorder_level', 'Reorder level', ColumnType::Qty),
            new ReportColumn('sold_30d', 'Sold (30 days)', ColumnType::Qty),
            new ReportColumn('run_rate', 'Run rate / day', ColumnType::Qty),
            new ReportColumn('days_cover', 'Days of cover', ColumnType::Int),
            new ReportColumn('suggested', 'Suggested order', ColumnType::Qty),
        ];

        $totals = $this->totals($columns, $rows);
        unset($totals['run_rate'], $totals['days_cover'], $totals['reorder_level']);

        return new ReportResult($columns, $rows, $totals);
    }
}
