<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Branch;
use App\Reports\Dashboard\DashboardAggregates;
use App\Reports\Dashboard\DashboardPeriod;
use App\Support\Sales\BusinessDay;
use App\Support\Stock\StockQueries;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Everything the dashboard shows for one period and branch scope (a branch id, or null for every
 * branch), cached for 60 seconds per tenant + scope + period. Money is integer paisa, quantities
 * integer milli-units. Cost and profit figures are always computed; the controller strips them for
 * users without reports.profit.
 */
final readonly class DashboardQuery
{
    public const int TTL_SECONDS = 60;

    /** Shortest heat-map window: shorter periods use the four weeks ending on their last day. */
    public const int HEATMAP_MIN_DAYS = 28;

    public function __construct(
        private TenantContext $context,
        private DashboardAggregates $aggregates,
        private TopCustomersByPurchase $topCustomers,
        private SalesByProduct $products,
        private SalesByDay $salesByDay,
        private DuesAging $aging,
        private StockValuation $valuation,
        private StockQueries $stock,
    ) {}

    public static function cacheKey(int $tenantId, ?int $branchId, DashboardPeriod $period, bool $withBranches = false): string
    {
        $scope = $branchId === null ? 'all' : (string) $branchId;

        return "dashboard:{$tenantId}:{$scope}:{$period->key}:{$period->from}:{$period->to}:".($withBranches ? 'b' : '-');
    }

    /** @return array<string, mixed> */
    public function get(DashboardPeriod $period, ?int $branchId, bool $withBranches = false): array
    {
        $key = self::cacheKey($this->context->tenantId(), $branchId, $period, $withBranches);

        return Cache::remember($key, self::TTL_SECONDS, fn (): array => $this->compute($period, $branchId, $withBranches));
    }

    /** @return array<string, mixed> */
    private function compute(DashboardPeriod $period, ?int $branchId, bool $withBranches): array
    {
        $timezone = BusinessDay::timezone($this->context->tenant());
        $branchIds = $branchId === null ? null : [$branchId];
        $params = static fn (string $from, string $to): ReportParameters => new ReportParameters($from, $to, $timezone, $branchIds, true);
        $current = $params($period->from, $period->to);
        $previous = $params($period->previousFrom, $period->previousTo);

        $sales = $this->aggregates->sales($current);
        $salesBefore = $this->aggregates->sales($previous);
        $returns = $this->aggregates->returns($current);
        $returnsBefore = $this->aggregates->returns($previous);
        $aging = $this->aging->run($current);
        $valuation = $this->valuation->run($current);
        $lowStock = $this->lowStock($branchId);

        $average = static fn (array $s): int => $s['invoices'] === 0 ? 0 : intdiv(2 * $s['sales'] + $s['invoices'], 2 * $s['invoices']);
        $skus = array_unique(array_map(static fn (array $r): string => (string) $r['sku'], $valuation->rows));

        return [
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'period' => $period->toArray(),
            'kpis' => [
                'sales' => ['value' => $sales['sales'], 'previous' => $salesBefore['sales']],
                'profit' => ['value' => $sales['profit'], 'previous' => $salesBefore['profit'], 'revenue' => $sales['revenue']],
                'invoices' => ['value' => $sales['invoices'], 'previous' => $salesBefore['invoices']],
                'average' => ['value' => $average($sales), 'previous' => $average($salesBefore)],
                'dues' => ['value' => (int) ($aging->totals['total'] ?? 0), 'debtors' => count($aging->rows)],
                'collections' => ['value' => $this->aggregates->collections($current), 'previous' => $this->aggregates->collections($previous)],
                'returns' => ['value' => $returns['amount'], 'previous' => $returnsBefore['amount'], 'count' => $returns['count']],
                'stock_value' => ['value' => (int) ($valuation->totals['cost_value'] ?? 0), 'skus' => count($skus)],
                'low_stock' => ['value' => count($lowStock)],
            ],
            'trend' => $this->trend($period, $current, $previous),
            'payment_mix' => $this->aggregates->paymentMix($current),
            'heatmap' => $this->heatmap($period, $params),
            'districts' => $this->aggregates->salesByDistrict($current),
            'branches' => $withBranches ? $this->branches($period, $timezone) : null,
            'aging' => [
                'd30' => (int) ($aging->totals['d30'] ?? 0),
                'd60' => (int) ($aging->totals['d60'] ?? 0),
                'd90' => (int) ($aging->totals['d90'] ?? 0),
                'd90_plus' => (int) ($aging->totals['d90_plus'] ?? 0),
                'total' => (int) ($aging->totals['total'] ?? 0),
            ],
            'top_products' => array_map(
                static fn (array $r): array => ['product' => $r['product'], 'sku' => $r['sku'], 'qty' => $r['net_qty'], 'sales' => $r['sales']],
                array_slice($this->products->run($current)->rows, 0, 5),
            ),
            'top_customers' => array_map(
                static fn (array $r): array => ['customer' => $r['customer'], 'customer_no' => $r['customer_no'], 'invoices' => $r['invoices'], 'net' => $r['net']],
                array_slice($this->topCustomers->run($current->withFilters(['limit' => '5']))->rows, 0, 5),
            ),
            'low_stock' => array_slice($lowStock, 0, 5),
            'recent_sales' => $this->aggregates->recentSales($current),
            'debtors' => $this->aggregates->debtors($current),
            'setup' => $this->aggregates->setup(),
        ];
    }

    /**
     * Hourly for a one-day period, daily otherwise; `previous` is the comparison window at the same
     * position (hour of day, or n-th day).
     *
     * @return array{granularity: string, points: list<array{key: string, net: int, previous: int}>}
     */
    private function trend(DashboardPeriod $period, ReportParameters $current, ReportParameters $previous): array
    {
        $points = [];
        if ($period->isSingleDay()) {
            $now = $this->aggregates->salesByHour($current);
            $before = $this->aggregates->salesByHour($previous);
            foreach ($now as $hour => $net) {
                $points[] = ['key' => sprintf('%02d', $hour), 'net' => $net, 'previous' => $before[$hour] ?? 0];
            }

            return ['granularity' => 'hour', 'points' => $points];
        }

        $now = $this->salesByDay->run($current)->rows;
        $before = $this->salesByDay->run($previous)->rows;
        foreach ($now as $i => $row) {
            $points[] = ['key' => (string) $row['date'], 'net' => (int) $row['net'], 'previous' => (int) ($before[$i]['net'] ?? 0)];
        }

        return ['granularity' => 'day', 'points' => $points];
    }

    /**
     * @param  callable(string, string): ReportParameters  $params
     * @return array{from: string, to: string, cells: list<array{dow: int, hour: int, count: int}>}
     */
    private function heatmap(DashboardPeriod $period, callable $params): array
    {
        $from = $period->from;
        if ($period->days() < self::HEATMAP_MIN_DAYS) {
            $from = CarbonImmutable::parse($period->to)->subDays(self::HEATMAP_MIN_DAYS - 1)->format('Y-m-d');
        }

        return ['from' => $from, 'to' => $period->to, 'cells' => $this->aggregates->heatmap($params($from, $period->to))];
    }

    /** @return list<array{id: int, code: string, name: string, sales: int, previous: int, profit: int}> */
    private function branches(DashboardPeriod $period, string $timezone): array
    {
        $now = $this->aggregates->salesByBranch($period->from, $period->to, $timezone);
        $before = $this->aggregates->salesByBranch($period->previousFrom, $period->previousTo, $timezone);

        $rows = [];
        foreach (Branch::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']) as $branch) {
            $rows[] = [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'sales' => $now[$branch->id]['sales'] ?? 0,
                'previous' => $before[$branch->id]['sales'] ?? 0,
                'profit' => $now[$branch->id]['profit'] ?? 0,
            ];
        }

        return $rows;
    }

    /** @return list<array{product: string, sku: string|null, branch: string, on_hand: int, reorder: int, cost: int}> */
    private function lowStock(?int $branchId): array
    {
        $branches = $branchId === null
            ? Branch::query()->where('is_active', true)->orderBy('code')->get()->all()
            : array_filter([Branch::query()->find($branchId)]);

        $rows = [];
        foreach ($branches as $branch) {
            foreach ($this->stock->lowStock($branch) as $r) {
                $rows[] = [
                    'product' => $r['product']->name,
                    'sku' => $r['product']->sku,
                    'branch' => $branch->code,
                    'on_hand' => $r['on_hand']->milli,
                    'reorder' => $r['product']->reorder_level->milli,
                    'cost' => $r['product']->cost_price->paisa,
                ];
            }
        }

        return $rows;
    }
}
