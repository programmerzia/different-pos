<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Facades\DB;

/** Received purchase bills per vendor in the period (drafts, cancelled and deleted bills excluded). */
final class PurchasesByVendor extends AbstractReport
{
    public function name(): string
    {
        return 'purchases-by-vendor';
    }

    public function group(): string
    {
        return 'purchases';
    }

    public function filters(): array
    {
        return ['vendor'];
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $query = DB::table('purchases')
            ->join('vendors', 'vendors.id', '=', 'purchases.vendor_id')
            ->where('purchases.tenant_id', $this->tenantId())
            ->where('vendors.tenant_id', $this->tenantId())
            ->whereNull('purchases.deleted_at')
            ->where('purchases.status', 'received')
            ->where('purchases.purchased_at', '>=', $parameters->from()->format('Y-m-d H:i:s'))
            ->where('purchases.purchased_at', '<', $parameters->toExclusive()->format('Y-m-d H:i:s'));
        $this->branches($query, $parameters, 'purchases.branch_id');
        if (($vendor = $parameters->filter('vendor')) !== null && ctype_digit($vendor)) {
            $query->where('purchases.vendor_id', (int) $vendor);
        }

        $records = $query
            ->groupBy('vendors.id', 'vendors.name', 'vendors.company')
            ->select(
                DB::raw('vendors.name AS vendor'),
                DB::raw('vendors.company AS company'),
                DB::raw('COUNT(*) AS bills'),
                DB::raw('SUM(purchases.total) AS total'),
                DB::raw('SUM(purchases.paid) AS paid'),
                DB::raw('SUM(purchases.due) AS due'),
            )
            ->orderByRaw('SUM(purchases.total) DESC')
            ->orderBy('vendors.id')
            ->get();

        $rows = [];
        foreach ($records as $r) {
            $rows[] = [
                'vendor' => $this->text($r->vendor),
                'company' => $this->text($r->company),
                'bills' => $this->intOf($r->bills),
                'total' => $this->intOf($r->total),
                'paid' => $this->intOf($r->paid),
                'due' => $this->intOf($r->due),
            ];
        }

        $columns = [
            new ReportColumn('vendor', 'Vendor', ColumnType::Text),
            new ReportColumn('company', 'Company', ColumnType::Text),
            new ReportColumn('bills', 'Bills', ColumnType::Int),
            new ReportColumn('total', 'Bill total', ColumnType::Money),
            new ReportColumn('paid', 'Paid', ColumnType::Money),
            new ReportColumn('due', 'Due', ColumnType::Money),
        ];

        return new ReportResult($columns, $rows, $this->totals($columns, $rows));
    }
}
