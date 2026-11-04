/**
 * The reports directory (mockup "Reports & Business Intelligence"): the mockup's fourteen cards in
 * its order, each with its category and the report (App\Reports\*) it runs, then the extra reports
 * the app has beyond the mockup ("More reports"). Text lives under `reports.directory.items.<id>`.
 */
export type ReportCategory =
    | 'money'
    | 'sales'
    | 'customers'
    | 'inventory'
    | 'purchases'
    | 'tax';

export type CatalogueCard = {
    /** Mockup card id (`daily_cash_summary` …), also the i18n key part. */
    id: string;
    category: ReportCategory;
    /** Report slug (`/app/reports/{slug}`). */
    report: string;
    /** `main` = one of the mockup's fourteen cards; `more` = an extra report. */
    section: 'main' | 'more';
};

export const REPORT_CATEGORIES: ReportCategory[] = [
    'money',
    'sales',
    'customers',
    'inventory',
    'purchases',
    'tax',
];

export const REPORT_CATALOGUE: CatalogueCard[] = [
    {
        id: 'daily_cash_summary',
        category: 'money',
        report: 'daily-cash',
        section: 'main',
    },
    {
        id: 'discount_report',
        category: 'sales',
        report: 'discounts',
        section: 'main',
    },
    {
        id: 'returns_by_reason',
        category: 'sales',
        report: 'returns-by-reason',
        section: 'main',
    },
    {
        id: 'daily_sales',
        category: 'sales',
        report: 'sales-by-day',
        section: 'main',
    },
    {
        id: 'product_sales',
        category: 'sales',
        report: 'sales-by-product',
        section: 'main',
    },
    {
        id: 'due_aging',
        category: 'customers',
        report: 'dues-aging',
        section: 'main',
    },
    {
        id: 'top_customers',
        category: 'customers',
        report: 'top-customers',
        section: 'main',
    },
    {
        id: 'stock_valuation',
        category: 'inventory',
        report: 'stock-valuation',
        section: 'main',
    },
    {
        id: 'low_stock',
        category: 'inventory',
        report: 'low-stock',
        section: 'main',
    },
    {
        id: 'dead_stock',
        category: 'inventory',
        report: 'dead-stock',
        section: 'main',
    },
    {
        id: 'branch_transfer',
        category: 'inventory',
        report: 'branch-transfers',
        section: 'main',
    },
    {
        id: 'vendor_payables',
        category: 'purchases',
        report: 'vendor-payables',
        section: 'main',
    },
    { id: 'pnl_statement', category: 'money', report: 'pnl', section: 'main' },
    {
        id: 'vat_mushak',
        category: 'tax',
        report: 'vat-output',
        section: 'main',
    },
    {
        id: 'sales_by_user',
        category: 'sales',
        report: 'sales-by-user',
        section: 'more',
    },
    {
        id: 'sales_by_tender',
        category: 'sales',
        report: 'sales-by-tender',
        section: 'more',
    },
    {
        id: 'returns_detail',
        category: 'sales',
        report: 'returns',
        section: 'more',
    },
    {
        id: 'last_purchase',
        category: 'customers',
        report: 'customers-last-purchase',
        section: 'more',
    },
    {
        id: 'customers_by_area',
        category: 'customers',
        report: 'customers-by-area',
        section: 'more',
    },
    {
        id: 'sales_summary',
        category: 'sales',
        report: 'sales-summary',
        section: 'more',
    },
    {
        id: 'sales_by_category',
        category: 'sales',
        report: 'sales-by-category',
        section: 'more',
    },
    {
        id: 'sales_by_brand',
        category: 'sales',
        report: 'sales-by-brand',
        section: 'more',
    },
    {
        id: 'sales_by_family',
        category: 'sales',
        report: 'sales-by-family',
        section: 'more',
    },
    {
        id: 'stock_movements',
        category: 'inventory',
        report: 'stock-movements',
        section: 'more',
    },
    {
        id: 'purchases_by_vendor',
        category: 'purchases',
        report: 'purchases-by-vendor',
        section: 'more',
    },
    {
        id: 'collections_by_user',
        category: 'money',
        report: 'collections-by-user',
        section: 'more',
    },
    {
        id: 'cards_issued',
        category: 'customers',
        report: 'cards-issued',
        section: 'more',
    },
    // Not on the server yet, so these stay disabled: they need the money module and offline sync.
    {
        id: 'expenses_by_category',
        category: 'money',
        report: 'expenses-by-category',
        section: 'more',
    },
    {
        id: 'sync_exceptions',
        category: 'sales',
        report: 'sync-exceptions',
        section: 'more',
    },
];

/** Point-in-time reports (no period): mirrors `usesPeriod()` on the server. */
export const POINT_IN_TIME_REPORTS = new Set([
    'dues-aging',
    'stock-valuation',
    'customers-last-purchase',
    'low-stock',
    'dead-stock',
    'vendor-payables',
]);

/**
 * Cards to show: the catalogue, a card marked built only when the server lists its report, plus any
 * server report the catalogue does not know yet (so a new report is never hidden). `category`
 * narrows to one category chip ('' = all).
 */
export function directoryCards(
    available: string[],
    category: ReportCategory | '' = '',
): (CatalogueCard & { built: boolean })[] {
    const known = new Set(REPORT_CATALOGUE.map((c) => c.report));
    const cards: (CatalogueCard & { built: boolean })[] = REPORT_CATALOGUE.map(
        (card) => ({ ...card, built: available.includes(card.report) }),
    );
    for (const name of available) {
        if (!known.has(name)) {
            cards.push({
                id: name,
                category: 'sales',
                report: name,
                section: 'more',
                built: true,
            });
        }
    }

    return category === ''
        ? cards
        : cards.filter((card) => card.category === category);
}
