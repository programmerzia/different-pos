/**
 * The reports directory (mockup "Reports & Business Intelligence"): every card in display order,
 * with its category and the built report (App\Reports\*) it runs. `report: null` = not built yet
 * (shown disabled, "next phase"). Text lives under `reports.directory.<id>`.
 */
export type ReportCategory =
    | 'money'
    | 'sales'
    | 'customers'
    | 'inventory'
    | 'purchases'
    | 'tax';

export type CatalogueCard = {
    id: string;
    category: ReportCategory;
    /** Report slug (`/app/reports/{slug}`), or null when not built yet. */
    report: string | null;
};

export const REPORT_CATALOGUE: CatalogueCard[] = [
    { id: 'daily_cash', category: 'money', report: 'daily-cash' },
    { id: 'discounts', category: 'sales', report: null },
    { id: 'returns', category: 'sales', report: 'returns' },
    { id: 'daily_sales', category: 'sales', report: 'sales-by-day' },
    { id: 'product_sales', category: 'sales', report: 'sales-by-product' },
    { id: 'sales_by_user', category: 'sales', report: 'sales-by-user' },
    { id: 'sales_by_tender', category: 'sales', report: 'sales-by-tender' },
    { id: 'dues_aging', category: 'customers', report: 'dues-aging' },
    { id: 'top_customers', category: 'customers', report: 'top-customers' },
    {
        id: 'last_purchase',
        category: 'customers',
        report: 'customers-last-purchase',
    },
    { id: 'stock_valuation', category: 'inventory', report: 'stock-valuation' },
    { id: 'low_stock', category: 'inventory', report: null },
    { id: 'dead_stock', category: 'inventory', report: null },
    { id: 'transfers', category: 'inventory', report: null },
    { id: 'vendor_payables', category: 'purchases', report: null },
    { id: 'pnl', category: 'money', report: null },
    { id: 'vat', category: 'tax', report: null },
];

/**
 * Cards to show: the catalogue, a built card only when the server lists its report, plus any
 * server report the catalogue does not know yet (so a new report is never hidden).
 */
export function directoryCards(
    available: string[],
): (CatalogueCard & { built: boolean })[] {
    const known = new Set(REPORT_CATALOGUE.map((c) => c.report));
    const cards = REPORT_CATALOGUE.map((card) => ({
        ...card,
        built: card.report !== null && available.includes(card.report),
    }));
    for (const name of available) {
        if (!known.has(name)) {
            cards.push({
                id: name,
                category: 'sales',
                report: name,
                built: true,
            });
        }
    }

    return cards;
}
