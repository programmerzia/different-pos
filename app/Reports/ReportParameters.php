<?php

declare(strict_types=1);

namespace App\Reports;

use Carbon\CarbonImmutable;

/** The validated inputs of one report run. Dates are tenant-local calendar days, both inclusive. */
final readonly class ReportParameters
{
    /**
     * @param  list<int>|null  $branchIds  null = every branch of the tenant
     * @param  array<string, string>  $filters
     */
    public function __construct(
        public string $fromDate,
        public string $toDate,
        public string $timezone,
        public ?array $branchIds,
        public bool $canProfit = false,
        public array $filters = [],
    ) {}

    /** Start of the first day, in UTC. */
    public function from(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->fromDate, $this->timezone)->startOfDay()->utc();
    }

    /** Start of the day after the last day, in UTC (exclusive bound). */
    public function toExclusive(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->toDate, $this->timezone)->addDay()->startOfDay()->utc();
    }

    /** UTC offset such as `+06:00`, for CONVERT_TZ. */
    public function offset(): string
    {
        return $this->toExclusive()->setTimezone($this->timezone)->format('P');
    }

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone)->startOfDay();
    }

    public function filter(string $key): ?string
    {
        $value = $this->filters[$key] ?? null;

        return $value === null || $value === '' ? null : $value;
    }

    /** Same inputs, different days. */
    public function withDates(string $fromDate, string $toDate): self
    {
        return new self($fromDate, $toDate, $this->timezone, $this->branchIds, $this->canProfit, $this->filters);
    }

    /** @param  array<string, string>  $filters */
    public function withFilters(array $filters): self
    {
        return new self($this->fromDate, $this->toDate, $this->timezone, $this->branchIds, $this->canProfit, $filters);
    }
}
