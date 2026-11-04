import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vite-plus/test';
import { i18n } from '@/i18n';
import { NewItemDialog } from './new-item-dialog';

const fetchMock = vi.fn();
const t = (key: string) => String(i18n.t(key));
const units = [
    { id: 1, name: 'Piece', short_name: 'pcs', allows_decimal: false },
    { id: 2, name: 'Metre', short_name: 'm', allows_decimal: true },
];

beforeAll(async () => {
    await i18n.changeLanguage('en');
});

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    fetchMock.mockReset();
});

function open(initialName = 'Silk') {
    const onCreated = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    render(
        <NewItemDialog
            open
            onOpenChange={vi.fn()}
            units={units}
            initialName={initialName}
            onCreated={onCreated}
        />,
    );

    return onCreated;
}

describe('NewItemDialog', () => {
    it('defaults to a raw material, prefilled with what was typed, and creates it', async () => {
        fetchMock.mockResolvedValue({
            ok: true,
            json: async () => ({
                id: 9,
                name: 'Silk',
                variant_label: null,
                sku: 'RM-000001',
                barcode: '2000000000015',
                item_type: 'raw_material',
                unit: 'm',
                track_stock: true,
                cost_price: 0,
                sale_price: 0,
                on_hand: 0,
            }),
        });
        const onCreated = open();

        expect(
            (
                screen.getByLabelText(
                    `${t('products.name_en')} *`,
                ) as HTMLInputElement
            ).value,
        ).toBe('Silk');
        expect(
            (
                screen.getByLabelText(
                    t('products.item_type'),
                ) as HTMLSelectElement
            ).value,
        ).toBe('raw_material');
        expect(
            screen.queryByLabelText(`${t('products.mrp_label')} *`),
        ).toBeNull();

        fireEvent.change(
            screen.getByLabelText(`${t('products.unit_label')} *`),
            {
                target: { value: '2' },
            },
        );
        fireEvent.change(screen.getByLabelText(t('products.raw_cost_label')), {
            target: { value: '900' },
        });
        fireEvent.click(
            screen.getByRole('button', { name: t('purchases.new_item_add') }),
        );

        await waitFor(() => expect(onCreated).toHaveBeenCalledTimes(1));
        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toBe('/app/purchases/items');
        expect(JSON.parse(init.body as string)).toEqual({
            name: 'Silk',
            item_type: 'raw_material',
            unit_id: 2,
            cost_price: 90000,
        });
        // The cost typed here rides on the bill line even for a user who may not see costs.
        expect(onCreated.mock.calls[0]?.[0]).toMatchObject({
            id: 9,
            cost_price: 90000,
        });
    });

    it('asks for the retail price of a finished product and a name before calling the server', () => {
        const onCreated = open('');

        fireEvent.click(
            screen.getByRole('button', { name: t('purchases.new_item_add') }),
        );
        expect(screen.getByText(t('purchases.err_item_name'))).toBeTruthy();

        fireEvent.change(screen.getByLabelText(t('products.item_type')), {
            target: { value: 'finished' },
        });
        fireEvent.change(screen.getByLabelText(`${t('products.name_en')} *`), {
            target: { value: 'Cap' },
        });
        fireEvent.click(
            screen.getByRole('button', { name: t('purchases.new_item_add') }),
        );

        expect(screen.getByText(t('products.err_price'))).toBeTruthy();
        expect(fetchMock).not.toHaveBeenCalled();
        expect(onCreated).not.toHaveBeenCalled();
    });

    it('shows a server field error', async () => {
        fetchMock.mockResolvedValue({
            ok: false,
            status: 422,
            statusText: 'Unprocessable',
            json: async () => ({
                errors: { unit_id: ['The selected unit is invalid.'] },
            }),
        });
        const onCreated = open();

        fireEvent.click(
            screen.getByRole('button', { name: t('purchases.new_item_add') }),
        );

        expect(
            await screen.findByText('The selected unit is invalid.'),
        ).toBeTruthy();
        expect(onCreated).not.toHaveBeenCalled();
    });
});
