<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Facades\DB;

/**
 * Profit & loss for the period, one line per row: gross sales, discounts, shipping and rounding,
 * returns (ex VAT, by return date), net revenue, VAT collected (memo), FIFO cost of goods sold net
 * of the cost reversed by restocked returns, gross profit and gross margin. There is no expenses
 * ledger in this phase (PRD Epic F follows with the money module), so the statement stops at gross
 * profit; the cost and profit lines need reports.profit.
 */
final class ProfitAndLoss extends AbstractReport
{
    public function name(): string
    {
        return 'pnl';
    }

    public function group(): string
    {
        return 'money';
    }

    public function filters(): array
    {
        return ['cashier'];
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $cashier = $this->cashierId($parameters);
        $salesQuery = $this->sales($parameters);
        if ($cashier !== null) {
            $salesQuery->where('sales.sold_by', $cashier);
        }
        $sales = $salesQuery
            ->selectRaw('COALESCE(SUM(sales.subtotal), 0) AS gross')
            ->selectRaw('COALESCE(SUM(sales.item_discount_total + sales.order_discount), 0) AS discounts')
            ->selectRaw('COALESCE(SUM(sales.shipping + sales.rounding), 0) AS other')
            ->selectRaw('COALESCE(SUM(sales.tax_total), 0) AS vat')
            ->selectRaw('COALESCE(SUM(sales.total - sales.tax_total), 0) AS revenue')
            ->selectRaw('COALESCE(SUM(sales.cogs_total), 0) AS cogs')
            ->first();

        $returns = DB::table('sale_returns')
            ->where('sale_returns.tenant_id', $this->tenantId())
            ->where('sale_returns.returned_at', '>=', $parameters->from()->format('Y-m-d H:i:s'))
            ->where('sale_returns.returned_at', '<', $parameters->toExclusive()->format('Y-m-d H:i:s'));
        $this->branches($returns, $parameters, 'sale_returns.branch_id');
        if ($cashier !== null) {
            $returns->where('sale_returns.created_by', $cashier);
        }
        $returns = $returns
            ->selectRaw('COALESCE(SUM(sale_returns.total - sale_returns.tax_total), 0) AS returned')
            ->selectRaw('COALESCE(SUM(sale_returns.tax_total), 0) AS returned_vat')
            ->first();

        $cogsReversed = DB::table('sale_return_items')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->where('sale_returns.tenant_id', $this->tenantId())
            ->where('sale_returns.returned_at', '>=', $parameters->from()->format('Y-m-d H:i:s'))
            ->where('sale_returns.returned_at', '<', $parameters->toExclusive()->format('Y-m-d H:i:s'));
        $this->branches($cogsReversed, $parameters, 'sale_returns.branch_id');
        if ($cashier !== null) {
            $cogsReversed->where('sale_returns.created_by', $cashier);
        }
        $cogsReversed = $this->intOf($cogsReversed->sum('sale_return_items.cogs_reversed'));

        $gross = $this->intOf($sales?->gross);
        $discounts = $this->intOf($sales?->discounts);
        $other = $this->intOf($sales?->other);
        $revenueBeforeReturns = $this->intOf($sales?->revenue);
        $returned = $this->intOf($returns?->returned);
        $netRevenue = $revenueBeforeReturns - $returned;
        $cogs = $this->intOf($sales?->cogs) - $cogsReversed;
        $profit = $netRevenue - $cogs;

        $line = fn (string $key, int $amount, bool $profitOnly = false): array => [
            'line' => $key,
            'amount' => $amount,
            'pct_bp' => $this->bp($amount, $netRevenue),
            '__profit' => $profitOnly ? 1 : 0,
        ];

        $rows = [
            $line('gross_sales', $gross),
            $line('discounts', -$discounts),
            $line('other', $other),
            $line('returns', -$returned),
            $line('net_revenue', $netRevenue),
            $line('vat_collected', $this->intOf($sales?->vat) - $this->intOf($returns?->returned_vat)),
            $line('cogs', -$cogs, true),
            $line('gross_profit', $profit, true),
        ];
        if (! $parameters->canProfit) {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => $r['__profit'] === 0));
        }
        $rows = array_map(static function (array $r): array {
            unset($r['__profit']);

            return $r;
        }, $rows);

        $columns = [
            new ReportColumn('line', 'Line', ColumnType::Text, valuePrefix: 'reports.pnl.'),
            new ReportColumn('amount', 'Amount', ColumnType::Money),
            new ReportColumn('pct_bp', '% of net revenue', ColumnType::Percent),
        ];

        return new ReportResult($columns, $rows, null);
    }
}
