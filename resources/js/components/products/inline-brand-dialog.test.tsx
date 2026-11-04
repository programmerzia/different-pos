import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vite-plus/test';
import { i18n } from '@/i18n';
import { InlineBrandDialog } from './inline-brand-dialog';

const fetchMock = vi.fn();
const t = (key: string) => String(i18n.t(key));

beforeAll(async () => {
    await i18n.changeLanguage('en');
});

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    fetchMock.mockReset();
});

function open() {
    const onCreated = vi.fn();
    const onOpenChange = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    render(
        <InlineBrandDialog
            open
            onOpenChange={onOpenChange}
            onCreated={onCreated}
        />,
    );

    return { onCreated, onOpenChange };
}

describe('InlineBrandDialog', () => {
    it('creates the brand over JSON and hands it back', async () => {
        fetchMock.mockResolvedValue({
            ok: true,
            json: async () => ({ id: 5, name: 'Different Fashion' }),
        });
        const { onCreated, onOpenChange } = open();

        fireEvent.change(screen.getByLabelText(t('products.brand_name')), {
            target: { value: ' Different Fashion ' },
        });
        fireEvent.click(
            screen.getByRole('button', { name: t('products.brand_add') }),
        );

        await waitFor(() =>
            expect(onCreated).toHaveBeenCalledWith({
                id: 5,
                name: 'Different Fashion',
            }),
        );
        expect(onOpenChange).toHaveBeenCalledWith(false);
        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toBe('/app/products/brands');
        expect(init.method).toBe('POST');
        expect(JSON.parse(init.body as string)).toEqual({
            name: 'Different Fashion',
        });
    });

    it('shows a duplicate name as a field error and keeps the dialog open', async () => {
        fetchMock.mockResolvedValue({
            ok: false,
            status: 422,
            statusText: 'Unprocessable',
            json: async () => ({
                message: 'x',
                errors: {
                    name: ['A brand "Different Fashion" already exists.'],
                },
            }),
        });
        const { onCreated, onOpenChange } = open();

        fireEvent.change(screen.getByLabelText(t('products.brand_name')), {
            target: { value: 'Different Fashion' },
        });
        fireEvent.click(
            screen.getByRole('button', { name: t('products.brand_add') }),
        );

        expect(
            await screen.findByText(
                'A brand "Different Fashion" already exists.',
            ),
        ).toBeTruthy();
        expect(onCreated).not.toHaveBeenCalled();
        expect(onOpenChange).not.toHaveBeenCalledWith(false);
    });

    it('asks for a name before calling the server', () => {
        const { onCreated } = open();

        fireEvent.click(
            screen.getByRole('button', { name: t('products.brand_add') }),
        );

        expect(screen.getByText(t('products.err_brand_name'))).toBeTruthy();
        expect(fetchMock).not.toHaveBeenCalled();
        expect(onCreated).not.toHaveBeenCalled();
    });
});
