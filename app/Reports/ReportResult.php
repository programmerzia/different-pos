<?php

declare(strict_types=1);

namespace App\Reports;

/** Rows keyed by column key, plus an optional totals row (numeric columns only). */
final readonly class ReportResult
{
    /**
     * @param  list<ReportColumn>  $columns
     * @param  list<array<string, int|string|null>>  $rows
     * @param  array<string, int>|null  $totals
     */
    public function __construct(
        public array $columns,
        public array $rows,
        public ?array $totals = null,
    ) {}

    /** Drops the cost/profit columns for users without reports.profit. */
    public function withoutProfit(): self
    {
        $keep = array_values(array_filter($this->columns, static fn (ReportColumn $c): bool => ! $c->profit));
        $keys = array_flip(array_map(static fn (ReportColumn $c): string => $c->key, $keep));

        return new self(
            $keep,
            array_map(static fn (array $row): array => array_intersect_key($row, $keys), $this->rows),
            $this->totals === null ? null : array_intersect_key($this->totals, $keys),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'columns' => array_map(static fn (ReportColumn $c): array => $c->toArray(), $this->columns),
            'rows' => $this->rows,
            'totals' => $this->totals,
        ];
    }
}
