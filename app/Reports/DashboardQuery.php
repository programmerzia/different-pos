<?php

declare(strict_types=1);

namespace App\Reports;

use App\Support\Sales\BusinessDay;
use App\Support\Stock\StockQueries;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Everything the dashboard shows, for the current branch, cached for 60 seconds per tenant + branch.
 * Money is integer paisa, quantities integer milli-units.
 */
final readonly class DashboardQuery
{
    public const int TTL_SECONDS = 60;

    public function __construct(
        private TenantContext $context,
        private SalesByDay $salesByDay,
        private DailyCashSummary $cash,
        private TopCustomersByPurchase $topCustomers,
        private SalesByProduct $products,
        private DuesAging $aging,
        private StockQueries $stock,
    ) {}

    public static function cacheKey(int $tenantId, int $branchId): string
    {
        return "dashboard:{$tenantId}:{$branchId}";
    }

    /** @return array<string, mixed> */
    public function get(): array
    {
        $key = self::cacheKey($this->context->tenantId(), $this->context->branchId());

        return Cache::remember($key, self::TTL_SECONDS, fn (): array => $this->compute());
    }

    /** @return array<string, mixed> */
    private function compute(): array
    {
        $timezone = BusinessDay::timezone($this->context->tenant());
        $branch = [$this->context->branchId()];
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $range = fn (CarbonImmutable $from, CarbonImmutable $to): ReportParameters => new ReportParameters($from->format('Y-m-d'), $to->format('Y-m-d'), $timezone, $branch);

        $monthStart = $today->startOfMonth();
        $lastMonthStart = $monthStart->subMonthNoOverflow();
        $periods = [
            'today' => $range($today, $today),
            'yesterday' => $range($today->subDay(), $today->subDay()),
            'month' => $range($monthStart, $today),
            'last_month' => $range($lastMonthStart, $monthStart->subDay()),
        ];

        $kpis = ['sales' => [], 'invoices' => [], 'average' => [], 'collections' => []];
        foreach ($periods as $name => $p) {
            $sales = $this->salesByDay->run($p)->totals ?? [];
            $cash = $this->cash->run($p)->totals ?? [];
            $kpis['sales'][$name] = $sales['net'] ?? 0;
            $kpis['invoices'][$name] = $sales['invoices'] ?? 0;
            $kpis['average'][$name] = ($sales['invoices'] ?? 0) === 0 ? 0 : intdiv(2 * ($sales['net'] ?? 0) + $sales['invoices'], 2 * $sales['invoices']);
            $kpis['collections'][$name] = $cash['collections'] ?? 0;
        }

        $aging = $this->aging->run($range($today, $today))->totals ?? [];
        $kpis['open_dues'] = $aging['total'] ?? 0;

        $lowStock = $this->stock->lowStock($this->context->branch());
        $kpis['low_stock'] = count($lowStock);

        $trend = $this->salesByDay->run($range($today->subDays(29), $today))->rows;

        return [
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'kpis' => $kpis,
            'trend' => array_map(static fn (array $r): array => ['date' => $r['date'], 'net' => $r['net']], $trend),
            'top_customers' => array_map(
                static fn (array $r): array => ['customer' => $r['customer'], 'invoices' => $r['invoices'], 'net' => $r['net']],
                array_slice($this->topCustomers->run($periods['month']->withFilters(['limit' => '5']))->rows, 0, 5),
            ),
            'top_products' => array_map(
                static fn (array $r): array => ['product' => $r['product'], 'qty' => $r['net_qty'], 'sales' => $r['sales']],
                array_slice($this->products->run($periods['month'])->rows, 0, 5),
            ),
            'aging' => [
                'd30' => $aging['d30'] ?? 0,
                'd60' => $aging['d60'] ?? 0,
                'd90' => $aging['d90'] ?? 0,
                'd90_plus' => $aging['d90_plus'] ?? 0,
                'total' => $aging['total'] ?? 0,
            ],
            'low_stock' => array_map(
                static fn (array $r): array => [
                    'product' => $r['product']->name,
                    'sku' => $r['product']->sku,
                    'on_hand' => $r['on_hand']->milli,
                    'reorder' => $r['product']->reorder_level->milli,
                ],
                array_slice($lowStock, 0, 8),
            ),
        ];
    }
}
