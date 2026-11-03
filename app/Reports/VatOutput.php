<?php

declare(strict_types=1);

namespace App\Reports;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * VAT output summary in the Mushak 6.3 layout: per VAT rate, the invoices, the taxable value (line
 * value after every discount, the order discount shared over the lines pro rata) and the output VAT
 * of completed sales, the taxable value and VAT reversed by returns in the period, and the net VAT.
 */
final class VatOutput extends AbstractReport
{
    public function name(): string
    {
        return 'vat-output';
    }

    public function group(): string
    {
        return 'tax';
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $inclusive = (bool) app(TenantContext::class)->tenant()->setting('tax_inclusive');
        // Line value after line + group discounts, as the order discount was shared over it.
        $base = $inclusive ? 'sale_items.line_total' : '(sale_items.line_total - sale_items.tax_amount)';

        $perSale = DB::table('sale_items')
            ->groupBy('sale_id')
            ->select('sale_id', DB::raw("SUM({$base}) AS base_total"));

        $sales = $this->sales($parameters)
            ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->joinSub($perSale, 'ps', 'ps.sale_id', '=', 'sales.id')
            ->groupBy('sale_items.tax_rate_bp')
            ->select('sale_items.tax_rate_bp')
            ->selectRaw('COUNT(DISTINCT sales.id) AS invoices')
            ->selectRaw("SUM(sale_items.line_total - sale_items.tax_amount - ROUND(sales.order_discount * {$base} / NULLIF(ps.base_total, 0), 0)) AS taxable")
            ->selectRaw('SUM(sale_items.tax_amount) AS vat')
            ->get()->keyBy('tax_rate_bp');

        $returns = DB::table('sale_return_items')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
            ->where('sale_returns.tenant_id', $this->tenantId())
            ->where('sale_returns.returned_at', '>=', $parameters->from()->format('Y-m-d H:i:s'))
            ->where('sale_returns.returned_at', '<', $parameters->toExclusive()->format('Y-m-d H:i:s'));
        $this->branches($returns, $parameters, 'sale_returns.branch_id');
        $returns = $returns
            ->groupBy('sale_items.tax_rate_bp')
            ->select('sale_items.tax_rate_bp')
            ->selectRaw('COUNT(DISTINCT sale_returns.id) AS returns_count')
            ->selectRaw('SUM(sale_return_items.line_total - sale_return_items.tax_amount) AS taxable')
            ->selectRaw('SUM(sale_return_items.tax_amount) AS vat')
            ->get()->keyBy('tax_rate_bp');

        $rates = array_unique([...$sales->keys()->all(), ...$returns->keys()->all()]);
        sort($rates);

        $rows = [];
        foreach ($rates as $rate) {
            $s = $sales->get($rate);
            $r = $returns->get($rate);
            $taxable = $this->intOf($s?->taxable);
            $vat = $this->intOf($s?->vat);
            $returnedTaxable = $this->intOf($r?->taxable);
            $returnedVat = $this->intOf($r?->vat);
            $rows[] = [
                'vat_rate_bp' => (int) $rate,
                'invoices' => $this->intOf($s?->invoices),
                'taxable' => $taxable,
                'vat' => $vat,
                'returns_count' => $this->intOf($r?->returns_count),
                'returned_taxable' => $returnedTaxable,
                'returned_vat' => $returnedVat,
                'net_taxable' => $taxable - $returnedTaxable,
                'net_vat' => $vat - $returnedVat,
            ];
        }

        $columns = [
            new ReportColumn('vat_rate_bp', 'VAT rate', ColumnType::Percent),
            new ReportColumn('invoices', 'Invoices', ColumnType::Int),
            new ReportColumn('taxable', 'Taxable value', ColumnType::Money),
            new ReportColumn('vat', 'Output VAT', ColumnType::Money),
            new ReportColumn('returns_count', 'Returns', ColumnType::Int),
            new ReportColumn('returned_taxable', 'Returned taxable value', ColumnType::Money),
            new ReportColumn('returned_vat', 'VAT reversed', ColumnType::Money),
            new ReportColumn('net_taxable', 'Net taxable value', ColumnType::Money),
            new ReportColumn('net_vat', 'Net VAT payable', ColumnType::Money),
        ];

        return new ReportResult($columns, $rows, $this->totals($columns, $rows));
    }
}
