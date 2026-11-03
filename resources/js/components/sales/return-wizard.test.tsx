import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vite-plus/test';
import {
    ReturnWizard,
    type WizardData,
} from '@/components/sales/return-wizard';
import { HotkeyHost } from '@/hotkeys/host';
import { i18n } from '@/i18n';

const posts: { url: string; data: Record<string, unknown> }[] = [];
const navigations: string[] = [];

vi.mock('@inertiajs/react', () => ({
    router: {
        post: (url: string, data: Record<string, unknown>) =>
            posts.push({ url, data }),
    },
}));
vi.mock('@/command/navigate', () => ({
    navigateTo: (url: string) => navigations.push(url),
}));

const t = (key: string, options: Record<string, unknown> = {}): string =>
    i18n.t(key, options);

const customer = {
    id: 7,
    uuid: 'c-7',
    name: 'Karim',
    shop_name: 'Karim Fashion',
    customer_no: '1000 0017',
    phone: null,
    walk_in: false,
};

const data = (overrides: Partial<WizardData> = {}): WizardData => ({
    q: '',
    candidates: [],
    sale: {
        id: 41,
        number: 'DF1-R1-26000412',
        status: 'completed',
        sold_at: '2026-11-03 11:42',
        customer,
        total: 400_000,
        paid: 400_000,
        due: 0,
        returned_total: 0,
        window: { days: 7, age: 0, expired: false },
        lines: [
            {
                id: 1,
                product_id: 10,
                name: 'Panjabi',
                sku: 'PAN-1',
                qty: 3000,
                qty_returned: 0,
                returnable: 3000,
                unit_price: 100_000,
                payable: 300_000,
            },
            {
                id: 2,
                product_id: 11,
                name: 'Trousers',
                sku: 'TRO-1',
                qty: 1000,
                qty_returned: 1000,
                returnable: 0,
                unit_price: 100_000,
                payable: 100_000,
            },
        ],
    },
    methods: [
        { id: 3, name: 'Cash', type: 'cash', requires_reference: false },
        { id: 4, name: 'bKash', type: 'mfs', requires_reference: true },
    ],
    reasons: ['defect', 'size', 'other'],
    can: { exchange: true },
    ...overrides,
});

const next = () =>
    fireEvent.click(
        screen.getByRole('button', {
            name: new RegExp(
                `^(${[
                    'returns.wizard_next_items',
                    'returns.wizard_next_refund',
                    'returns.wizard_next_confirm',
                    'returns.wizard_next_exchange',
                    'returns.wizard_complete',
                ]
                    .map((k) => t(k))
                    .join('|')})`,
                'i',
            ),
        }),
    );
const step = () =>
    document.querySelector('[aria-current="step"]')?.textContent ?? '';

beforeAll(() => {
    globalThis.ResizeObserver ??= class {
        observe() {}
        unobserve() {}
        disconnect() {}
    } as unknown as typeof ResizeObserver;
});

afterEach(() => {
    cleanup();
    posts.length = 0;
    navigations.length = 0;
});

describe('ReturnWizard', () => {
    it('finds candidates and hands the picked sale to the page', () => {
        const pick = vi.fn();
        const search = vi.fn();
        render(
            <ReturnWizard
                open
                loading={false}
                data={data({
                    sale: null,
                    q: 'Kar',
                    candidates: [
                        {
                            id: 41,
                            number: 'DF1-R1-26000412',
                            sold_at: '2026-11-03 11:42',
                            customer,
                            items: 2,
                            total: 400_000,
                            due: 0,
                            returned: 0,
                        },
                    ],
                })}
                onSearch={search}
                onPickSale={pick}
                onClose={vi.fn()}
            />,
        );
        expect(step()).toContain(t('returns.wizard_step_find'));
        fireEvent.click(
            screen.getByRole('button', { name: /DF1-R1-26000412/ }),
        );
        expect(pick).toHaveBeenCalledWith(41);
        fireEvent.click(
            screen.getByRole('button', { name: t('returns.wizard_verify') }),
        );
        expect(search).toHaveBeenCalledWith('Kar');
    });

    it('walks find → items → refund → confirm and posts the return', () => {
        render(
            <>
                <HotkeyHost />
                <ReturnWizard
                    open
                    loading={false}
                    data={data()}
                    onSearch={vi.fn()}
                    onPickSale={vi.fn()}
                    onClose={vi.fn()}
                />
            </>,
        );
        next();
        expect(step()).toContain(t('returns.wizard_step_items'));

        // Nothing chosen yet: the next step is blocked.
        const blocked = screen.getByRole('button', {
            name: new RegExp(t('returns.wizard_next_refund'), 'i'),
        });
        expect((blocked as HTMLButtonElement).disabled).toBe(true);

        fireEvent.click(
            screen.getByRole('button', {
                name: new RegExp(`^${t('returns.return_all')}`),
            }),
        );
        fireEvent.change(
            screen.getByLabelText(
                t('returns.condition_for', { name: 'Panjabi' }),
            ),
            { target: { value: 'damaged' } },
        );
        next();
        expect(step()).toContain(t('returns.wizard_step_refund'));

        fireEvent.change(screen.getByLabelText(t('returns.refund_method')), {
            target: { value: '4' },
        });
        fireEvent.change(screen.getByLabelText(t('returns.col_reason')), {
            target: { value: 'size' },
        });
        fireEvent.change(screen.getByLabelText(t('returns.memo')), {
            target: { value: 'Too small' },
        });
        next();
        expect(step()).toContain(t('returns.wizard_step_confirm'));
        expect(screen.getByText(/bKash/)).toBeTruthy();

        next();
        expect(posts).toEqual([
            {
                url: '/app/returns',
                data: {
                    sale_id: 41,
                    lines: [{ sale_item_id: 1, qty: 3000, restock: false }],
                    refund_mode: 'refund',
                    refund_method_id: 4,
                    reason: 'size',
                    note: 'Too small',
                },
            },
        ]);
    });

    it('sends a direct exchange to the exchange page with the sale', () => {
        render(
            <ReturnWizard
                open
                loading={false}
                data={data()}
                onSearch={vi.fn()}
                onPickSale={vi.fn()}
                onClose={vi.fn()}
            />,
        );
        next();
        fireEvent.click(
            screen.getByRole('button', {
                name: new RegExp(`^${t('returns.return_all')}`),
            }),
        );
        next();
        fireEvent.click(
            screen.getByLabelText(new RegExp(t('returns.settle_exchange'))),
        );
        next();
        expect(navigations).toEqual(['/app/exchanges/create?sale=41']);
        expect(posts).toHaveLength(0);
    });

    it('offers no store credit to a walk-in and blocks an expired window', () => {
        const walkIn = data();
        if (walkIn.sale) {
            walkIn.sale.customer = { ...customer, walk_in: true };
        }
        const { unmount } = render(
            <ReturnWizard
                open
                loading={false}
                data={walkIn}
                onSearch={vi.fn()}
                onPickSale={vi.fn()}
                onClose={vi.fn()}
            />,
        );
        next();
        fireEvent.click(
            screen.getByRole('button', {
                name: new RegExp(`^${t('returns.return_all')}`),
            }),
        );
        next();
        expect(
            (
                screen.getByLabelText(
                    new RegExp(t('returns.settle_credit')),
                ) as HTMLInputElement
            ).disabled,
        ).toBe(true);
        unmount();

        const expired = data();
        if (expired.sale) {
            expired.sale.window = { days: 7, age: 30, expired: true };
        }
        render(
            <ReturnWizard
                open
                loading={false}
                data={expired}
                onSearch={vi.fn()}
                onPickSale={vi.fn()}
                onClose={vi.fn()}
            />,
        );
        expect(screen.getByRole('alert').textContent).toContain('7');
        expect(
            (
                screen.getByRole('button', {
                    name: new RegExp(t('returns.wizard_next_items'), 'i'),
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(true);
    });
});
