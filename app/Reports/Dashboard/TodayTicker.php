<?php

declare(strict_types=1);

namespace App\Reports\Dashboard;

use App\Reports\ReportParameters;
use App\Support\Sales\BusinessDay;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * The top bar's "Today ৳… · N sales" ticker for the current branch: net sales (after returns) and the
 * completed-invoice count of the business day, cached for 60 seconds per tenant + branch + day.
 */
final readonly class TodayTicker
{
    public const int TTL_SECONDS = 60;

    public function __construct(private TenantContext $context, private DashboardAggregates $aggregates) {}

    /** @return array{total_paisa: int, sales: int} */
    public function get(): array
    {
        $timezone = BusinessDay::timezone($this->context->tenant());
        $today = CarbonImmutable::now($timezone)->format('Y-m-d');
        $branchId = $this->context->branchId();
        $key = "today-ticker:{$this->context->tenantId()}:{$branchId}:{$today}";

        return Cache::remember($key, self::TTL_SECONDS, function () use ($today, $timezone, $branchId): array {
            $sales = $this->aggregates->sales(new ReportParameters($today, $today, $timezone, [$branchId]));

            return ['total_paisa' => $sales['sales'], 'sales' => $sales['invoices']];
        });
    }
}
