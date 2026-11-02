<?php

declare(strict_types=1);

namespace App\Reports;

/** Open dues per customer, aged by invoice date: 0-30, 31-60, 61-90, over 90 days (today, tenant timezone). */
final class DuesAging extends AbstractReport
{
    public function name(): string
    {
        return 'dues-aging';
    }

    public function group(): string
    {
        return 'customers';
    }

    public function usesPeriod(): bool
    {
        return false;
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $today = $parameters->today()->format('Y-m-d');
        $offset = $parameters->offset();
        $binds = [$today, $offset];

        $records = $this->sales($parameters, inPeriod: false)
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('customers.tenant_id', $this->tenantId())
            ->where('sales.due', '>', 0)
            ->groupBy('customers.id', 'customers.customer_no', 'customers.name', 'customers.shop_name', 'customers.phone')
            ->select('customers.customer_no', 'customers.name', 'customers.shop_name', 'customers.phone')
            ->selectRaw("COALESCE(SUM(CASE WHEN DATEDIFF(?, DATE(CONVERT_TZ(sales.sold_at, '+00:00', ?))) <= 30 THEN sales.due END), 0) AS d30", $binds)
            ->selectRaw("COALESCE(SUM(CASE WHEN DATEDIFF(?, DATE(CONVERT_TZ(sales.sold_at, '+00:00', ?))) BETWEEN 31 AND 60 THEN sales.due END), 0) AS d60", $binds)
            ->selectRaw("COALESCE(SUM(CASE WHEN DATEDIFF(?, DATE(CONVERT_TZ(sales.sold_at, '+00:00', ?))) BETWEEN 61 AND 90 THEN sales.due END), 0) AS d90", $binds)
            ->selectRaw("COALESCE(SUM(CASE WHEN DATEDIFF(?, DATE(CONVERT_TZ(sales.sold_at, '+00:00', ?))) > 90 THEN sales.due END), 0) AS d90_plus", $binds)
            ->selectRaw('SUM(sales.due) AS total')
            ->orderByDesc('total')
            ->orderBy('customers.id')
            ->get();

        $rows = [];
        foreach ($records as $r) {
            $rows[] = [
                'customer_no' => $this->text($r->customer_no),
                'customer' => $this->text($r->name),
                'shop' => $this->text($r->shop_name),
                'phone' => $this->text($r->phone),
                'd30' => $this->intOf($r->d30),
                'd60' => $this->intOf($r->d60),
                'd90' => $this->intOf($r->d90),
                'd90_plus' => $this->intOf($r->d90_plus),
                'total' => $this->intOf($r->total),
            ];
        }

        $columns = [
            new ReportColumn('customer_no', 'Customer no.', ColumnType::Text),
            new ReportColumn('customer', 'Customer', ColumnType::Text),
            new ReportColumn('shop', 'Shop', ColumnType::Text),
            new ReportColumn('phone', 'Phone', ColumnType::Text),
            new ReportColumn('d30', '0-30 days', ColumnType::Money),
            new ReportColumn('d60', '31-60 days', ColumnType::Money),
            new ReportColumn('d90', '61-90 days', ColumnType::Money),
            new ReportColumn('d90_plus', 'Over 90 days', ColumnType::Money),
            new ReportColumn('total', 'Total due', ColumnType::Money),
        ];

        return new ReportResult($columns, $rows, $this->totals($columns, $rows));
    }
}
