<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Facades\DB;

/**
 * The append-only stock ledger in the period, newest first: one row per movement with the quantity
 * in or out. Capped at MAX_ROWS (the newest) so a long period cannot produce an unbounded page.
 */
final class StockMovements extends AbstractReport
{
    public const int MAX_ROWS = 5000;

    public function name(): string
    {
        return 'stock-movements';
    }

    public function group(): string
    {
        return 'inventory';
    }

    public function filters(): array
    {
        return ['category', 'brand'];
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $query = DB::table('stock_movements')
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->join('branches', 'branches.id', '=', 'stock_movements.branch_id')
            ->leftJoin('users', fn ($join) => $join->on('users.id', '=', 'stock_movements.user_id')->where('users.tenant_id', '=', $this->tenantId()))
            ->where('stock_movements.tenant_id', $this->tenantId())
            ->where('products.tenant_id', $this->tenantId())
            ->where('branches.tenant_id', $this->tenantId())
            ->where('stock_movements.created_at', '>=', $parameters->from()->format('Y-m-d H:i:s'))
            ->where('stock_movements.created_at', '<', $parameters->toExclusive()->format('Y-m-d H:i:s'));
        $this->branches($query, $parameters, 'stock_movements.branch_id');
        if (($category = $parameters->filter('category')) !== null && ctype_digit($category)) {
            $query->where('products.category_id', (int) $category);
        }
        if (($brand = $parameters->filter('brand')) !== null && ctype_digit($brand)) {
            $query->where('products.brand_id', (int) $brand);
        }

        $records = $query
            ->selectRaw("DATE(CONVERT_TZ(stock_movements.created_at, '+00:00', ?)) AS day", [$parameters->offset()])
            ->addSelect(
                DB::raw('branches.name AS branch'),
                'products.sku',
                DB::raw('products.name AS product'),
                'stock_movements.type',
                'stock_movements.qty',
                'stock_movements.reference_type',
                'stock_movements.reference_id',
                DB::raw('users.name AS user_name'),
            )
            ->orderByDesc('stock_movements.created_at')
            ->orderByDesc('stock_movements.id')
            ->limit(self::MAX_ROWS)
            ->get();

        $rows = [];
        foreach ($records as $r) {
            $qty = $this->milli($r->qty);
            $rows[] = [
                'date' => $this->text($r->day),
                'branch' => $this->text($r->branch),
                'sku' => $this->text($r->sku),
                'product' => $this->text($r->product),
                'type' => $this->text($r->type),
                'qty_in' => max($qty, 0),
                'qty_out' => max(-$qty, 0),
                'reference' => $this->text($r->reference_type).' #'.$this->intOf($r->reference_id),
                'user' => $this->text($r->user_name),
            ];
        }

        $columns = [
            new ReportColumn('date', 'Date', ColumnType::Date),
            new ReportColumn('branch', 'Branch', ColumnType::Text),
            new ReportColumn('sku', 'SKU', ColumnType::Text),
            new ReportColumn('product', 'Product', ColumnType::Text),
            new ReportColumn('type', 'Type', ColumnType::Text, valuePrefix: 'reports.movement.'),
            new ReportColumn('qty_in', 'Qty in', ColumnType::Qty),
            new ReportColumn('qty_out', 'Qty out', ColumnType::Qty),
            new ReportColumn('reference', 'Reference', ColumnType::Text),
            new ReportColumn('user', 'User', ColumnType::Text),
        ];

        return new ReportResult($columns, $rows, $this->totals($columns, $rows));
    }
}
