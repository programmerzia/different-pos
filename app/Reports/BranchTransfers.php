<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\StockMovementType;
use Illuminate\Support\Facades\DB;

/**
 * Inter-branch transfer discrepancy, read from the stock ledger: per branch and product in the
 * period, quantity dispatched (transfer_out movements), received (transfer_in), still in transit
 * (branch_stocks.qty_in_transit) and the variance. The transfer workflow itself (PRD D5, plan 2.19)
 * is not built in this phase, so the report stays empty until transfers post to the ledger.
 */
final class BranchTransfers extends AbstractReport
{
    public function name(): string
    {
        return 'branch-transfers';
    }

    public function group(): string
    {
        return 'inventory';
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $query = DB::table('stock_movements')
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->join('branches', 'branches.id', '=', 'stock_movements.branch_id')
            ->leftJoin('branch_stocks', fn ($join) => $join->on('branch_stocks.branch_id', '=', 'stock_movements.branch_id')->on('branch_stocks.product_id', '=', 'stock_movements.product_id'))
            ->where('stock_movements.tenant_id', $this->tenantId())
            ->where('products.tenant_id', $this->tenantId())
            ->where('branches.tenant_id', $this->tenantId())
            ->whereIn('stock_movements.type', [StockMovementType::TransferOut->value, StockMovementType::TransferIn->value])
            ->where('stock_movements.created_at', '>=', $parameters->from()->format('Y-m-d H:i:s'))
            ->where('stock_movements.created_at', '<', $parameters->toExclusive()->format('Y-m-d H:i:s'));
        $this->branches($query, $parameters, 'stock_movements.branch_id');

        $records = $query
            ->groupBy('branches.id', 'branches.name', 'products.id', 'products.sku', 'products.name', 'branch_stocks.qty_in_transit')
            ->select(
                DB::raw('branches.name AS branch'),
                'products.sku',
                'products.name',
                DB::raw("SUM(CASE WHEN stock_movements.type = 'transfer_out' THEN ABS(stock_movements.qty) ELSE 0 END) AS dispatched"),
                DB::raw("SUM(CASE WHEN stock_movements.type = 'transfer_in' THEN ABS(stock_movements.qty) ELSE 0 END) AS received"),
                DB::raw("COUNT(CASE WHEN stock_movements.type = 'transfer_out' THEN 1 END) AS dispatches"),
                DB::raw("COUNT(CASE WHEN stock_movements.type = 'transfer_in' THEN 1 END) AS receipts"),
                DB::raw('COALESCE(branch_stocks.qty_in_transit, 0) AS in_transit'),
            )
            ->orderBy('branches.name')
            ->orderBy('products.name')
            ->orderBy('products.id')
            ->get();

        $rows = [];
        foreach ($records as $r) {
            $dispatched = $this->milli($r->dispatched);
            $received = $this->milli($r->received);
            $rows[] = [
                'branch' => $this->text($r->branch),
                'sku' => $this->text($r->sku),
                'product' => $this->text($r->name),
                'dispatches' => $this->intOf($r->dispatches),
                'receipts' => $this->intOf($r->receipts),
                'dispatched' => $dispatched,
                'received' => $received,
                'in_transit' => $this->milli($r->in_transit),
                'variance' => $dispatched - $received,
            ];
        }

        $columns = [
            new ReportColumn('branch', 'Branch', ColumnType::Text),
            new ReportColumn('sku', 'SKU', ColumnType::Text),
            new ReportColumn('product', 'Product', ColumnType::Text),
            new ReportColumn('dispatches', 'Dispatches', ColumnType::Int),
            new ReportColumn('receipts', 'Receipts', ColumnType::Int),
            new ReportColumn('dispatched', 'Qty dispatched', ColumnType::Qty),
            new ReportColumn('received', 'Qty received', ColumnType::Qty),
            new ReportColumn('in_transit', 'In transit', ColumnType::Qty),
            new ReportColumn('variance', 'Variance', ColumnType::Qty),
        ];

        return new ReportResult($columns, $rows, $this->totals($columns, $rows));
    }
}
