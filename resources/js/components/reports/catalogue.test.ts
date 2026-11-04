import { describe, expect, it } from 'vite-plus/test';
import {
    directoryCards,
    POINT_IN_TIME_REPORTS,
    REPORT_CATALOGUE,
} from '@/components/reports/catalogue';
import bn from '@/i18n/bn.json';
import en from '@/i18n/en.json';

const MOCKUP_CARDS = [
    ['daily_cash_summary', 'Money', 'Daily Cash Summary Report'],
    ['discount_report', 'Sales', 'Discount Report (By Cashier & Reason)'],
    ['returns_by_reason', 'Sales', 'Returns & Exchanges Rate Report'],
    ['daily_sales', 'Sales', 'Daily Sales & Invoices Summary'],
    ['product_sales', 'Sales', 'Product Sales Velocity & Margin'],
    ['due_aging', 'Customers', 'Customer Due Aging Analysis'],
    ['top_customers', 'Customers', 'Top Customer Lifetime Value (LTV)'],
    ['stock_valuation', 'Inventory', 'FIFO Stock Valuation Ledger'],
    ['low_stock', 'Inventory', 'Low Stock & Purchase Reorder Alert'],
    ['dead_stock', 'Inventory', 'Dead Stock Report (>90 Days Zero Sale)'],
    ['branch_transfer', 'Inventory', 'Inter-Branch Transfer Discrepancy'],
    ['vendor_payables', 'Purchases', 'Vendor Payables & Bill Statement'],
    ['pnl_statement', 'Money', 'Profit & Loss (P&L) Statement'],
    ['vat_mushak', 'Tax & Compliance', 'VAT Output Summary (Mushak 6.3)'],
] as const;

type Directory = {
    categories: Record<string, string>;
    items: Record<string, { title: string; desc: string }>;
};
const directory = en.reports.directory as unknown as Directory;
const directoryBn = bn.reports.directory as unknown as Directory;

describe('report catalogue', () => {
    it('has the fourteen mockup cards first, in the mockup order, with its names and categories', () => {
        const main = REPORT_CATALOGUE.filter((c) => c.section === 'main');
        expect(main.map((c) => c.id)).toEqual(MOCKUP_CARDS.map((c) => c[0]));
        for (const [id, category, title] of MOCKUP_CARDS) {
            const card = main.find((c) => c.id === id)!;
            expect(directory.categories[card.category]).toBe(category);
            expect(directory.items[id]?.title).toBe(title);
            expect(directoryBn.items[id]?.title.length ?? 0).toBeGreaterThan(0);
        }
    });

    it('every card names a distinct report with EN + BN runner text', () => {
        const reports = REPORT_CATALOGUE.map((c) => c.report);
        expect(new Set(reports).size).toBe(reports.length);
        const items = en.reports.items as Record<string, unknown>;
        const itemsBn = bn.reports.items as Record<string, unknown>;
        for (const report of reports) {
            expect(items[report]).toBeDefined();
            expect(itemsBn[report]).toBeDefined();
        }
        for (const report of POINT_IN_TIME_REPORTS) {
            expect(reports).toContain(report);
        }
    });

    it('marks cards built only when the server lists the report and filters by category', () => {
        const cards = directoryCards(['daily-cash', 'pnl', 'brand-new']);
        expect(cards.find((c) => c.id === 'daily_cash_summary')?.built).toBe(
            true,
        );
        expect(cards.find((c) => c.id === 'low_stock')?.built).toBe(false);
        const extra = cards.find((c) => c.report === 'brand-new');
        expect(extra).toMatchObject({ built: true, section: 'more' });

        const money = directoryCards(['daily-cash', 'pnl'], 'money');
        expect(money.map((c) => c.id)).toEqual([
            'daily_cash_summary',
            'pnl_statement',
            'collections_by_user',
            'expenses_by_category',
        ]);
    });
});
