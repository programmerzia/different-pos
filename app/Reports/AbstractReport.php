<?php

declare(strict_types=1);

namespace App\Reports;

use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Shared plumbing. Reports use the query builder (aggregates over many rows), so every query
 * filters by tenant_id explicitly, and by branch when the parameters name branches.
 */
abstract class AbstractReport implements ReportContract
{
    public function usesPeriod(): bool
    {
        return true;
    }

    public function filters(): array
    {
        return [];
    }

    protected function tenantId(): int
    {
        return app(TenantContext::class)->tenantId();
    }

    /** Completed sales of the tenant, in the branches and the period of the parameters. */
    protected function sales(ReportParameters $p, bool $inPeriod = true): Builder
    {
        $query = DB::table('sales')
            ->where('sales.tenant_id', $this->tenantId())
            ->where('sales.status', 'completed');
        $this->branches($query, $p, 'sales.branch_id');
        if ($inPeriod) {
            $query->where('sales.sold_at', '>=', $p->from()->format('Y-m-d H:i:s'))
                ->where('sales.sold_at', '<', $p->toExclusive()->format('Y-m-d H:i:s'));
        }

        return $query;
    }

    protected function branches(Builder $query, ReportParameters $p, string $column): void
    {
        if ($p->branchIds !== null) {
            $query->whereIn($column, $p->branchIds);
        }
    }

    protected function intOf(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    protected function milli(mixed $value): int
    {
        return is_string($value) || is_int($value) ? Quantity::parse((string) $value)->milli : 0;
    }

    protected function text(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    /** Whole-number average, rounded half-up. */
    protected function average(int $sum, int $count): int
    {
        return $count === 0 ? 0 : intdiv(2 * $sum + $count, 2 * $count);
    }

    /**
     * @param  list<array<string, int|string|null>>  $rows
     * @param  list<ReportColumn>  $columns
     * @return array<string, int>
     */
    protected function totals(array $columns, array $rows): array
    {
        $totals = [];
        foreach ($columns as $column) {
            if ($column->type->isNumeric() && $column->key !== 'rank') {
                $totals[$column->key] = array_sum(array_map(static fn (array $r): int => (int) ($r[$column->key] ?? 0), $rows));
            }
        }

        return $totals;
    }

    /** @return list<string> every local date from..to inclusive */
    protected function days(ReportParameters $p): array
    {
        $days = [];
        $day = CarbonImmutable::parse($p->fromDate);
        $end = CarbonImmutable::parse($p->toDate);
        while ($day->lte($end) && count($days) < 400) {
            $days[] = $day->format('Y-m-d');
            $day = $day->addDay();
        }

        return $days;
    }
}
