<?php

declare(strict_types=1);

namespace App\Reports;

use App\Support\Sales\BusinessDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Customers bucketed by calendar months since their last purchase (this month, 1, 2, 3, 4+ months
 * ago, never), longest-lapsed first: the owner's "who stopped coming" list.
 */
final class CustomersByLastPurchase extends AbstractReport
{
    public const array BUCKETS = ['this_month', 'm1', 'm2', 'm3', 'm4_plus', 'never'];

    public function name(): string
    {
        return 'customers-last-purchase';
    }

    public function group(): string
    {
        return 'customers';
    }

    public function usesPeriod(): bool
    {
        return false;
    }

    public function filters(): array
    {
        return ['bucket'];
    }

    public function run(ReportParameters $parameters): ReportResult
    {
        $wanted = $parameters->filter('bucket');
        $now = CarbonImmutable::now($parameters->timezone);

        $agg = $this->sales($parameters, inPeriod: false)
            ->select(
                'sales.customer_id',
                DB::raw('MAX(sales.sold_at) AS last_at'),
                DB::raw('SUM(sales.total - sales.returned_total) AS lifetime'),
                DB::raw('SUM(sales.due) AS due'),
            )
            ->groupBy('sales.customer_id');

        $customers = DB::table('customers')
            ->leftJoinSub($agg, 'a', 'a.customer_id', '=', 'customers.id')
            ->where('customers.tenant_id', $this->tenantId())
            ->where('customers.is_walk_in', false)
            ->where('customers.is_active', true)
            ->whereNull('customers.deleted_at')
            ->whereNull('customers.merged_into_id')
            ->select('customers.id', 'customers.customer_no', 'customers.name', 'customers.shop_name', 'customers.phone', 'a.last_at', 'a.lifetime', 'a.due')
            ->get();

        $rows = [];
        foreach ($customers as $c) {
            $last = is_string($c->last_at) ? CarbonImmutable::parse($c->last_at, 'UTC')->setTimezone($parameters->timezone) : null;
            $bucket = $this->bucket($last, $now);
            if ($wanted !== null && $wanted !== $bucket) {
                continue;
            }
            $rows[] = [
                'bucket' => $bucket,
                'customer_no' => $this->text($c->customer_no),
                'customer' => $this->text($c->name),
                'shop' => $this->text($c->shop_name),
                'phone' => $this->text($c->phone),
                'last_purchase' => $last?->format('Y-m-d'),
                'days_since' => $last instanceof CarbonImmutable ? max(0, BusinessDay::daysBetween($last, $now, $parameters->timezone)) : null,
                'lifetime' => $this->intOf($c->lifetime),
                'due' => $this->intOf($c->due),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            // never-bought last, otherwise the longest-lapsed first
            $da = $a['days_since'] ?? -1;
            $db = $b['days_since'] ?? -1;
            if (($da === -1) !== ($db === -1)) {
                return $da === -1 ? 1 : -1;
            }

            return [$db, $a['customer']] <=> [$da, $b['customer']];
        });

        $columns = [
            new ReportColumn('bucket', 'Last purchased', ColumnType::Text, valuePrefix: 'reports.bucket.'),
            new ReportColumn('customer_no', 'Customer no.', ColumnType::Text),
            new ReportColumn('customer', 'Customer', ColumnType::Text),
            new ReportColumn('shop', 'Shop', ColumnType::Text),
            new ReportColumn('phone', 'Phone', ColumnType::Text),
            new ReportColumn('last_purchase', 'Last purchase', ColumnType::Date),
            new ReportColumn('days_since', 'Days since', ColumnType::Int),
            new ReportColumn('lifetime', 'Lifetime sales', ColumnType::Money),
            new ReportColumn('due', 'Open due', ColumnType::Money),
        ];

        $totals = $this->totals($columns, $rows);
        unset($totals['days_since']);

        return new ReportResult($columns, $rows, $totals);
    }

    private function bucket(?CarbonImmutable $last, CarbonImmutable $now): string
    {
        if (! $last instanceof CarbonImmutable) {
            return 'never';
        }
        $months = ($now->year * 12 + $now->month) - ($last->year * 12 + $last->month);

        return match (true) {
            $months <= 0 => 'this_month',
            $months === 1 => 'm1',
            $months === 2 => 'm2',
            $months === 3 => 'm3',
            default => 'm4_plus',
        };
    }
}
