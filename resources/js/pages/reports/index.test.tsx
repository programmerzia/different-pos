import {
    cleanup,
    fireEvent,
    render,
    screen,
    within,
} from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { REPORT_CATALOGUE } from '@/components/reports/catalogue';
import { i18n } from '@/i18n';
import { TooltipProvider } from '@/components/ui/tooltip';
import ReportsIndex from '@/pages/reports/index';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({
        children,
        href,
        ...rest
    }: {
        children: React.ReactNode;
        href: string;
    }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
    router: { get: vi.fn(), visit: vi.fn() },
    usePage: () => ({ props: {} }),
}));

// The toolbar's mobile hook reads window.matchMedia at import time; jsdom has none.
vi.hoisted(() => {
    Object.defineProperty(window, 'matchMedia', {
        writable: true,
        value: (query: string) => ({
            matches: false,
            media: query,
            onchange: null,
            addListener: () => undefined,
            removeListener: () => undefined,
            addEventListener: () => undefined,
            removeEventListener: () => undefined,
            dispatchEvent: () => false,
        }),
    });
});

const t = (key: string): string => i18n.t(key);

const groups = { all: REPORT_CATALOGUE.map((c) => ({ name: c.report })) };

const mount = (props: {
    groups: Record<string, { name: string }[]>;
    canExport: boolean;
}) =>
    render(
        <TooltipProvider>
            <ReportsIndex {...props} />
        </TooltipProvider>,
    );

afterEach(cleanup);

describe('reports directory', () => {
    it('shows the fourteen mockup cards with name, category and description, then the extra reports', () => {
        mount({ groups, canExport: true });

        const main = screen.getByRole('list', {
            name: t('reports.directory.title'),
        });
        const cards = within(main).getAllByRole('listitem');
        expect(cards).toHaveLength(14);
        expect(
            within(cards[0]!).getByText('Daily Cash Summary Report'),
        ).toBeTruthy();
        expect(within(cards[0]!).getByText('Money')).toBeTruthy();
        expect(
            within(cards[13]!).getByText('VAT Output Summary (Mushak 6.3)'),
        ).toBeTruthy();
        expect(within(cards[13]!).getByText('Tax & Compliance')).toBeTruthy();
        expect(
            within(cards[12]!).getByText(
                'Net revenue minus COGS; operational expenses follow with the money module',
            ),
        ).toBeTruthy();
        expect(
            within(main).getAllByText(`${t('reports.directory.run')} →`),
        ).toHaveLength(14);

        const more = screen.getByRole('list', {
            name: t('reports.directory.more'),
        });
        expect(within(more).getAllByRole('listitem')).toHaveLength(
            REPORT_CATALOGUE.length - 14,
        );
        expect(
            within(more).getByText('Customers & Sales by Area'),
        ).toBeTruthy();
    });

    it('shows CSV, XLSX and PDF as available when exporting is allowed, and struck through otherwise', () => {
        mount({ groups, canExport: true });
        for (const format of ['CSV', 'XLSX', 'PDF']) {
            const labels = screen.getAllByText(format);
            expect(labels).toHaveLength(REPORT_CATALOGUE.length);
            for (const label of labels) {
                expect(label.className).not.toContain('line-through');
            }
        }
        expect(screen.queryByText('Export Excel')).toBeNull();
        cleanup();

        mount({ groups, canExport: false });
        for (const format of ['CSV', 'XLSX', 'PDF']) {
            for (const label of screen.getAllByText(format)) {
                expect(label.className).toContain('line-through');
            }
        }
    });

    it('links period reports with the selected range and point-in-time reports without one', () => {
        mount({ groups, canExport: true });

        const pnl = screen
            .getByText('Profit & Loss (P&L) Statement')
            .closest('a')!;
        expect(pnl.getAttribute('href')).toMatch(
            /^\/app\/reports\/pnl\?from=\d{4}-\d{2}-\d{2}&to=\d{4}-\d{2}-\d{2}$/,
        );
        const lowStock = screen
            .getByText('Low Stock & Purchase Reorder Alert')
            .closest('a')!;
        expect(lowStock.getAttribute('href')).toBe('/app/reports/low-stock');
    });

    it('filters the grid by category chip and disables cards the server does not list', () => {
        mount({
            groups: { all: [{ name: 'daily-cash' }, { name: 'pnl' }] },
            canExport: true,
        });

        fireEvent.click(screen.getByRole('button', { name: 'Inventory' }));
        const main = screen.getByRole('list', {
            name: t('reports.directory.title'),
        });
        const cards = within(main).getAllByRole('listitem');
        expect(cards).toHaveLength(4);
        expect(
            within(main).getAllByText(t('reports.directory.next_phase')),
        ).toHaveLength(4);
        // Stock movements is the only extra Inventory report; the server does not list it here.
        expect(
            within(
                screen.getByRole('list', {
                    name: t('reports.directory.more'),
                }),
            ).getAllByRole('listitem'),
        ).toHaveLength(1);

        fireEvent.click(screen.getByRole('button', { name: 'Money' }));
        expect(
            within(
                screen.getByRole('list', {
                    name: t('reports.directory.title'),
                }),
            ).getAllByRole('link'),
        ).toHaveLength(2);
    });

    it('keeps the reports the server does not have yet visible but disabled', () => {
        mount({
            groups: {
                all: REPORT_CATALOGUE.filter(
                    (c) =>
                        !['expenses-by-category', 'sync-exceptions'].includes(
                            c.report,
                        ),
                ).map((c) => ({ name: c.report })),
            },
            canExport: true,
        });

        for (const id of ['expenses_by_category', 'sync_exceptions']) {
            const title = screen.getByText(
                t(`reports.directory.items.${id}.title`),
            );
            expect(title.closest('a')).toBeNull();
            expect(
                title.closest('[aria-disabled="true"]'),
            ).not.toBeNull();
        }
    });
});
