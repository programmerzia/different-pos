import {
    act,
    cleanup,
    fireEvent,
    render,
    screen,
} from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vite-plus/test';
import { HotkeyHost } from '@/hotkeys/host';
import { i18n } from '@/i18n';
import { ProductDialog, type ProductDetail } from './product-dialog';

const visit = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Link: ({
        children,
        href,
        ...props
    }: {
        children: React.ReactNode;
        href: unknown;
    }) => (
        <a href={typeof href === 'string' ? href : '#'} {...props}>
            {children}
        </a>
    ),
    router: { visit: (...args: unknown[]) => visit(...args) },
}));

const options = {
    categories: [{ id: 1, name: 'Panjabi' }],
    brands: [{ id: 2, name: 'In-house' }],
    units: [{ id: 3, name: 'Piece', short_name: 'pcs', allows_decimal: false }],
    taxRates: [{ id: 4, name: 'Standard', rate_bp: 1500 }],
    vendors: [{ id: 5, name: 'Silk Mills' }],
    variantOptions: [],
};

const product: ProductDetail = {
    id: 9,
    uuid: 'p-uuid',
    name: 'Cotton Panjabi',
    name_bn: null,
    sku: 'PAN-1',
    barcode: '8941001',
    hs_code: null,
    type: 'standard',
    item_type: 'finished',
    category_id: 1,
    brand_id: null,
    unit_id: 3,
    tax_rate_id: null,
    default_vendor_id: null,
    sale_price: 145000,
    cost_price: 95000,
    min_sale_price: null,
    wholesale_price: null,
    min_sale_qty: 0,
    reorder_level: 5000,
    track_stock: true,
    is_active: true,
    description: null,
    image_url: null,
    family_id: null,
    family_uuid: null,
    family_code: null,
    variant_label: null,
};

const t = (key: string, options?: Record<string, unknown>) =>
    String(i18n.t(key, options ?? {}));

function open(props: Partial<Parameters<typeof ProductDialog>[0]> = {}) {
    const onClose = vi.fn();
    render(
        <>
            <HotkeyHost />
            <ProductDialog
                product={null}
                options={options}
                canViewCost
                listQuery="?q=pan"
                onClose={onClose}
                {...props}
            />
        </>,
    );

    return onClose;
}

const pressEscape = () =>
    act(() => {
        fireEvent.keyDown(document.activeElement ?? document.body, {
            key: 'Escape',
            code: 'Escape',
        });
    });

beforeAll(async () => {
    // jsdom has no ResizeObserver; the Radix checkbox measures itself with one.
    globalThis.ResizeObserver ??= class {
        observe() {}
        unobserve() {}
        disconnect() {}
    } as unknown as typeof ResizeObserver;
    await i18n.changeLanguage('en');
});

afterEach(() => {
    cleanup();
    visit.mockReset();
});

describe('ProductDialog', () => {
    it('opens as the add dialog with both mockup panels and focuses the name', () => {
        open();

        expect(
            screen.getByRole('dialog', { name: t('products.dialog_new') }),
        ).toBeTruthy();
        expect(screen.getByText(t('products.panel_identity'))).toBeTruthy();
        expect(screen.getByText(t('products.panel_pricing'))).toBeTruthy();
        expect(document.activeElement).toBe(
            screen.getByLabelText(`${t('products.name_en')} *`),
        );
    });

    it('shows the required errors inline and does not submit', () => {
        open();

        fireEvent.click(
            screen.getByRole('button', { name: t('products.save_product') }),
        );

        expect(screen.getByText(t('products.err_name'))).toBeTruthy();
        expect(screen.getByText(t('products.err_sku'))).toBeTruthy();
        expect(screen.getByText(t('products.err_price'))).toBeTruthy();
        expect(visit).not.toHaveBeenCalled();
        expect(
            screen
                .getByLabelText(`${t('products.name_en')} *`)
                .getAttribute('aria-invalid'),
        ).toBe('true');
    });

    it('posts a valid new product in paisa, keeping the list query', () => {
        open();

        fireEvent.change(screen.getByLabelText(`${t('products.name_en')} *`), {
            target: { value: 'Silk Saree' },
        });
        fireEvent.change(
            screen.getByLabelText(`${t('products.sku_label')} *`),
            {
                target: { value: 'SAR-1' },
            },
        );
        fireEvent.change(
            screen.getByLabelText(`${t('products.mrp_label')} *`),
            {
                target: { value: '1450' },
            },
        );
        fireEvent.keyDown(
            screen.getByLabelText(`${t('products.sku_label')} *`),
            {
                key: 'Enter',
                code: 'Enter',
                ctrlKey: true,
            },
        );

        expect(visit).toHaveBeenCalledTimes(1);
        const [url, opts] = visit.mock.calls[0] as [
            string,
            { method: string; data: Record<string, unknown>; replace: boolean },
        ];
        expect(url).toBe('/app/products?q=pan');
        expect(opts.method).toBe('post');
        expect(opts.replace).toBe(true);
        expect(opts.data).toMatchObject({
            name: 'Silk Saree',
            sku: 'SAR-1',
            sale_price: 145000,
            unit_id: 3,
            barcode: null,
        });
    });

    it('hides the retail price and variants for a raw material and posts a zero price', () => {
        open();

        fireEvent.change(screen.getByLabelText(t('products.item_type')), {
            target: { value: 'raw_material' },
        });

        expect(
            screen.queryByLabelText(`${t('products.mrp_label')} *`),
        ).toBeNull();
        expect(
            screen.queryByLabelText(t('products.wholesale_label')),
        ).toBeNull();
        expect(screen.queryByLabelText(t('products.type'))).toBeNull();
        expect(screen.getByText(t('products.raw_hint'))).toBeTruthy();
        expect(
            screen.getByLabelText(t('products.raw_cost_label')),
        ).toBeTruthy();

        fireEvent.change(screen.getByLabelText(`${t('products.name_en')} *`), {
            target: { value: 'Cotton fabric' },
        });
        fireEvent.change(
            screen.getByLabelText(`${t('products.sku_label')} *`),
            { target: { value: 'FAB-1' } },
        );
        fireEvent.change(screen.getByLabelText(t('products.raw_cost_label')), {
            target: { value: '450' },
        });
        fireEvent.click(
            screen.getByRole('button', { name: t('products.save_product') }),
        );

        expect(visit).toHaveBeenCalledTimes(1);
        const [, opts] = visit.mock.calls[0] as [
            string,
            { data: Record<string, unknown> },
        ];
        expect(opts.data).toMatchObject({
            item_type: 'raw_material',
            type: 'standard',
            sale_price: 0,
            cost_price: 45000,
            track_stock: true,
        });
    });

    it('offers the inline brand "+" only with permission', () => {
        open();
        expect(
            screen.queryByRole('button', { name: t('products.brand_add') }),
        ).toBeNull();
        cleanup();

        open({ canCreateBrand: true });
        expect(
            screen.getByRole('button', { name: t('products.brand_add') }),
        ).toBeTruthy();
    });

    it('closes at once on Esc when nothing changed', () => {
        const onClose = open();

        pressEscape();

        expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('asks before discarding changes on Esc', () => {
        const onClose = open();

        fireEvent.change(screen.getByLabelText(`${t('products.name_en')} *`), {
            target: { value: 'Half typed' },
        });
        pressEscape();

        expect(onClose).not.toHaveBeenCalled();
        expect(screen.getByText(t('products.discard_title'))).toBeTruthy();

        fireEvent.click(
            screen.getByRole('button', { name: t('products.discard') }),
        );
        expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('edits an existing product with PUT and shows the margin', () => {
        open({ product });

        expect(
            screen.getByRole('dialog', { name: /Edit Product Specifications/ }),
        ).toBeTruthy();
        expect(screen.getByText(t('products.margin', { n: 53 }))).toBeTruthy();

        fireEvent.click(
            screen.getByRole('button', { name: t('products.save_product') }),
        );

        const [url, opts] = visit.mock.calls[0] as [
            string,
            { method: string; data: Record<string, unknown> },
        ];
        expect(url).toBe('/app/products/p-uuid?q=pan');
        expect(opts.method).toBe('put');
        expect(opts.data).toMatchObject({
            name: 'Cotton Panjabi',
            sale_price: 145000,
        });
    });

    it('shows size and colour chips directly; ticking one turns "Has variants" on and sends the grid', () => {
        open({
            options: {
                ...options,
                variantOptions: [
                    {
                        id: 7,
                        name: 'Size',
                        values: [
                            { id: 71, value: 'M', code: 'M' },
                            { id: 72, value: 'L', code: 'L' },
                        ],
                    },
                    {
                        id: 8,
                        name: 'Colour',
                        values: [{ id: 81, value: 'Black', code: 'BLK' }],
                    },
                ],
            },
        });
        const toggle = screen.getByRole('switch', {
            name: t('products.has_variants'),
        });
        expect(toggle.getAttribute('aria-checked')).toBe('false');
        expect(
            screen.getByText(t('products.applicable', { name: 'Size' })),
        ).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'M' }));
        expect(toggle.getAttribute('aria-checked')).toBe('true');

        fireEvent.change(screen.getByLabelText(`${t('products.name_en')} *`), {
            target: { value: 'Polo' },
        });
        fireEvent.change(
            screen.getByLabelText(`${t('products.family_code')} *`),
            {
                target: { value: 'POLO' },
            },
        );
        fireEvent.change(
            screen.getByLabelText(`${t('products.mrp_label')} *`),
            {
                target: { value: '500' },
            },
        );
        fireEvent.click(
            screen.getByRole('button', { name: t('products.save_product') }),
        );
        const [, opts] = visit.mock.calls[0] as [
            string,
            { data: Record<string, unknown> },
        ];
        expect(opts.data['variants']).toMatchObject({
            option1_id: 7,
            option2_id: 8,
            rows: [{ option1_value_id: 71 }],
        });

        // Turning the toggle off clears the chips.
        fireEvent.click(toggle);
        expect(toggle.getAttribute('aria-checked')).toBe('false');
    });

    it('shows the margin from cost and sale price with integer maths', () => {
        open({ product: { ...product, cost_price: 300, sale_price: 1000 } });
        expect(screen.getByText(t('products.margin', { n: 233 }))).toBeTruthy();
    });
});
