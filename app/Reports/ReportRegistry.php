<?php

declare(strict_types=1);

namespace App\Reports;

/** The catalogue of reports, in display order. */
final class ReportRegistry
{
    /** @var list<class-string<ReportContract>> */
    private const array REPORTS = [
        SalesByDay::class,
        SalesByProduct::class,
        SalesByUser::class,
        SalesByTender::class,
        Returns::class,
        TopCustomersByPurchase::class,
        CustomersByLastPurchase::class,
        DuesAging::class,
        StockValuation::class,
        DailyCashSummary::class,
    ];

    /** @return list<ReportContract> */
    public function all(): array
    {
        return array_map(static fn (string $class): ReportContract => app($class), self::REPORTS);
    }

    public function find(string $name): ?ReportContract
    {
        foreach ($this->all() as $report) {
            if ($report->name() === $name) {
                return $report;
            }
        }

        return null;
    }
}
