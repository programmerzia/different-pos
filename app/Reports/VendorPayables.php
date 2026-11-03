<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Facades\DB;

/**
 * Open vendor bills as of today: every received purchase with a balance due, its age in days and
 * the aging bucket, with the vendor's current balance. Purchases carry no payment terms yet, so
 * the age counts from the bill date.
 */
final class VendorPayables extends AbstractReport
{
    public function name(): string
    {
        return 'vendor-payables';
    }

    public function group(): string
    {
        return 'purchases';
    }

    public function usesPeriod(): bool
    {
        return false;
    }

    public function filters(): array
    {
        return ['vendor'];
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $today = $parameters->today()->format('Y-m-d');

        $query = DB::table('purchases')
            ->join('vendors', 'vendors.id', '=', 'purchases.vendor_id')
            ->join('branches', 'branches.id', '=', 'purchases.branch_id')
            ->where('purchases.tenant_id', $this->tenantId())
            ->where('vendors.tenant_id', $this->tenantId())
            ->where('branches.tenant_id', $this->tenantId())
            ->whereNull('purchases.deleted_at')
            ->where('purchases.status', '!=', 'cancelled')
            ->where('purchases.due', '>', 0);
        $this->branches($query, $parameters, 'purchases.branch_id');
        if (($vendor = $parameters->filter('vendor')) !== null && ctype_digit($vendor)) {
            $query->where('purchases.vendor_id', (int) $vendor);
        }

        $records = $query
            ->select(
                DB::raw('vendors.name AS vendor'),
                DB::raw('vendors.company AS company'),
                DB::raw('vendors.balance AS vendor_balance'),
                DB::raw('branches.name AS branch'),
                'purchases.number',
                'purchases.vendor_ref',
                'purchases.total',
                'purchases.paid',
                'purchases.due',
            )
            ->selectRaw("DATE(CONVERT_TZ(purchases.purchased_at, '+00:00', ?)) AS day", [$parameters->offset()])
            ->selectRaw("DATEDIFF(?, DATE(CONVERT_TZ(purchases.purchased_at, '+00:00', ?))) AS age", [$today, $parameters->offset()])
            ->orderBy('vendors.name')
            ->orderBy('purchases.purchased_at')
            ->orderBy('purchases.id')
            ->get();

        $rows = [];
        foreach ($records as $r) {
            $age = $this->intOf($r->age);
            $rows[] = [
                'vendor' => $this->text($r->vendor),
                'company' => $this->text($r->company),
                'branch' => $this->text($r->branch),
                'number' => $this->text($r->number),
                'vendor_ref' => $this->text($r->vendor_ref),
                'date' => $this->text($r->day),
                'age' => $age,
                'aging' => $age <= 30 ? 'd30' : ($age <= 60 ? 'd60' : ($age <= 90 ? 'd90' : 'd90_plus')),
                'total' => $this->intOf($r->total),
                'paid' => $this->intOf($r->paid),
                'due' => $this->intOf($r->due),
                'vendor_balance' => $this->intOf($r->vendor_balance),
            ];
        }

        $columns = [
            new ReportColumn('vendor', 'Vendor', ColumnType::Text),
            new ReportColumn('company', 'Company', ColumnType::Text),
            new ReportColumn('branch', 'Branch', ColumnType::Text),
            new ReportColumn('number', 'Bill no.', ColumnType::Text),
            new ReportColumn('vendor_ref', 'Vendor ref.', ColumnType::Text),
            new ReportColumn('date', 'Bill date', ColumnType::Date),
            new ReportColumn('age', 'Age (days)', ColumnType::Int),
            new ReportColumn('aging', 'Aging', ColumnType::Text, valuePrefix: 'reports.col.'),
            new ReportColumn('total', 'Bill total', ColumnType::Money),
            new ReportColumn('paid', 'Paid', ColumnType::Money),
            new ReportColumn('due', 'Due', ColumnType::Money),
            new ReportColumn('vendor_balance', 'Vendor balance', ColumnType::Money),
        ];

        $totals = $this->totals($columns, $rows);
        unset($totals['age'], $totals['vendor_balance']);

        return new ReportResult($columns, $rows, $totals);
    }
}
