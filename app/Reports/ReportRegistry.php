<?php

declare(strict_types=1);

namespace App\Reports;

/** The catalogue of reports, in display order. */
final class ReportRegistry
{
    /** @var list<class-string<ReportContract>> */
    private const array REPORTS = [
        DailyCashSummary::class,
        Discounts::class,
        ReturnsByReason::class,
        SalesByDay::class,
        SalesByProduct::class,
        DuesAging::class,
        TopCustomersByPurchase::class,
        StockValuation::class,
        LowStock::class,
        DeadStock::class,
        BranchTransfers::class,
        VendorPayables::class,
        ProfitAndLoss::class,
        VatOutput::class,
        // Beyond the mockup's 14 cards ("More reports").
        SalesByUser::class,
        SalesByTender::class,
        Returns::class,
        CustomersByLastPurchase::class,
        CustomersByArea::class,
        SalesSummary::class,
        SalesByCategory::class,
        SalesByBrand::class,
        SalesByFamily::class,
        StockMovements::class,
        PurchasesByVendor::class,
        CollectionsByUser::class,
        CardsIssued::class,
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
