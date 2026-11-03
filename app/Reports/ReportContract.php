<?php

declare(strict_types=1);

namespace App\Reports;

/** A read-only report: parameters in, columns + rows + totals out. */
interface ReportContract
{
    /** URL slug and i18n key part (`reports.{key}.title`). */
    public function name(): string;

    /** Catalogue group: sales | customers | inventory | money | purchases | tax. */
    public function group(): string;

    /** False for point-in-time reports (aging, valuation, last purchase). */
    public function usesPeriod(): bool;

    /**
     * Extra filters the parameter bar offers: category, brand, bucket, limit, cashier, vendor, reason, level.
     *
     * @return list<string>
     */
    public function filters(): array;

    public function run(ReportParameters $parameters): ReportResult;
}
