<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customers and sales by Bangladesh area: division, district or upazila (filter `level`, default
 * district) from customers.geo_*_id joined to the geo_* reference tables (name_en / name_bn).
 * Customers = active members located there; invoices, net sales and open dues from the completed
 * sales of the period and branches. Customers without a location are one "unknown" row.
 */
final class CustomersByArea extends AbstractReport
{
    public const array LEVELS = ['division', 'district', 'upazila'];

    public const string DEFAULT_LEVEL = 'district';

    public function name(): string
    {
        return 'customers-by-area';
    }

    public function group(): string
    {
        return 'customers';
    }

    public function filters(): array
    {
        return ['level'];
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $level = $parameters->filter('level') ?? self::DEFAULT_LEVEL;
        $level = in_array($level, self::LEVELS, true) ? $level : self::DEFAULT_LEVEL;
        $geoTable = "geo_{$level}s";
        $geoColumn = "customers.geo_{$level}_id";

        $columns = [
            new ReportColumn('area', 'Area', ColumnType::Text),
            new ReportColumn('area_bn', 'Area (BN)', ColumnType::Text),
            new ReportColumn('customers', 'Customers', ColumnType::Int),
            new ReportColumn('buyers', 'Buyers in period', ColumnType::Int),
            new ReportColumn('invoices', 'Invoices', ColumnType::Int),
            new ReportColumn('net', 'Net sales', ColumnType::Money),
            new ReportColumn('dues', 'Open dues', ColumnType::Money),
        ];

        if (! Schema::hasTable($geoTable)) {
            return new ReportResult($columns, [], $this->totals($columns, []));
        }

        $salesByCustomer = $this->sales($parameters)
            ->groupBy('sales.customer_id')
            ->select('sales.customer_id')
            ->selectRaw('COUNT(*) AS invoices')
            ->selectRaw('SUM(sales.total - sales.returned_total) AS net')
            ->selectRaw('SUM(sales.due) AS dues');

        $records = DB::table('customers')
            ->leftJoin($geoTable.' AS g', 'g.id', '=', $geoColumn)
            ->leftJoinSub($salesByCustomer, 's', 's.customer_id', '=', 'customers.id')
            ->where('customers.tenant_id', $this->tenantId())
            ->where('customers.is_walk_in', false)
            ->whereNull('customers.deleted_at')
            ->whereNull('customers.merged_into_id')
            ->groupBy(DB::raw($geoColumn), 'g.name_en', 'g.name_bn')
            ->select(DB::raw('g.name_en AS area'), DB::raw('g.name_bn AS area_bn'))
            ->selectRaw('COUNT(*) AS customers')
            ->selectRaw('COUNT(s.customer_id) AS buyers')
            ->selectRaw('COALESCE(SUM(s.invoices), 0) AS invoices')
            ->selectRaw('COALESCE(SUM(s.net), 0) AS net')
            ->selectRaw('COALESCE(SUM(s.dues), 0) AS dues')
            ->orderByDesc('net')
            ->orderBy('g.name_en')
            ->get();

        $rows = [];
        foreach ($records as $r) {
            $rows[] = [
                'area' => $this->text($r->area),
                'area_bn' => $this->text($r->area_bn),
                'customers' => $this->intOf($r->customers),
                'buyers' => $this->intOf($r->buyers),
                'invoices' => $this->intOf($r->invoices),
                'net' => $this->intOf($r->net),
                'dues' => $this->intOf($r->dues),
            ];
        }

        return new ReportResult($columns, $rows, $this->totals($columns, $rows));
    }
}
