<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Facades\DB;

final class TopCustomersByPurchase extends AbstractReport
{
    public const int DEFAULT_LIMIT = 100;

    public function name(): string
    {
        return 'top-customers';
    }

    public function group(): string
    {
        return 'customers';
    }

    public function filters(): array
    {
        return ['limit'];
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $limit = (int) ($parameters->filter('limit') ?? self::DEFAULT_LIMIT);
        $limit = max(1, min(1000, $limit));

        $items = DB::table('sale_items')
            ->select('sale_id', DB::raw('SUM(qty - qty_returned) AS net_qty'))
            ->groupBy('sale_id');

        $records = $this->sales($parameters)
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->leftJoinSub($items, 'it', 'it.sale_id', '=', 'sales.id')
            ->where('customers.tenant_id', $this->tenantId())
            ->where('customers.is_walk_in', false)
            ->groupBy('customers.id', 'customers.customer_no', 'customers.name', 'customers.shop_name', 'customers.phone')
            ->select(
                'customers.customer_no',
                'customers.name',
                'customers.shop_name',
                'customers.phone',
                DB::raw('COUNT(sales.id) AS invoices'),
                DB::raw('COALESCE(SUM(it.net_qty), 0) AS qty'),
                DB::raw('SUM(sales.total - sales.returned_total) AS net'),
                DB::raw('SUM(sales.due) AS dues'),
            )
            ->orderByDesc('net')
            ->orderBy('customers.id')
            ->limit($limit)
            ->get();

        $rows = [];
        foreach ($records as $i => $r) {
            $rows[] = [
                'rank' => $i + 1,
                'customer_no' => $this->text($r->customer_no),
                'customer' => $this->text($r->name),
                'shop' => $this->text($r->shop_name),
                'phone' => $this->text($r->phone),
                'invoices' => $this->intOf($r->invoices),
                'qty' => $this->milli($r->qty),
                'net' => $this->intOf($r->net),
                'dues' => $this->intOf($r->dues),
            ];
        }

        $columns = [
            new ReportColumn('rank', 'Rank', ColumnType::Int),
            new ReportColumn('customer_no', 'Customer no.', ColumnType::Text),
            new ReportColumn('customer', 'Customer', ColumnType::Text),
            new ReportColumn('shop', 'Shop', ColumnType::Text),
            new ReportColumn('phone', 'Phone', ColumnType::Text),
            new ReportColumn('invoices', 'Invoices', ColumnType::Int),
            new ReportColumn('qty', 'Qty', ColumnType::Qty),
            new ReportColumn('net', 'Net sales', ColumnType::Money),
            new ReportColumn('dues', 'Dues', ColumnType::Money),
        ];

        return new ReportResult($columns, $rows, $this->totals($columns, $rows));
    }
}
