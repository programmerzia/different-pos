import { describe, expect, it } from 'vite-plus/test';
import { calculateCart } from '@/engine/pricing';
import { cartReducer, cartTotals, emptyCart, exceedsCap } from './cart';
import type { CartState, CatalogVariant, CustomerCard } from './types';

const variant = (
    id: number,
    price: number,
    over: Partial<CatalogVariant> = {},
): CatalogVariant => ({
    id,
    name: `Panjabi ${id}`,
    sku: `PJ-${id}`,
    barcode: null,
    label: null,
    option1: null,
    option2: null,
    price,
    tax_rate_bp: 0,
    stock: 10_000,
    allows_decimal: false,
    ...over,
});

const customer = (over: Partial<CustomerCard> = {}): CustomerCard => ({
    id: 9,
    uuid: 'c-9',
    number: '1000 0017',
    name: 'Rahim',
    name_bn: null,
    shop_name: 'Rahim Store',
    phone: '01711000000',
    group: null,
    balance: 0,
    credit_limit: null,
    advance: 0,
    is_active: true,
    ...over,
});

const settings = { tax_inclusive: false, rounding_step: 0 };
const add = (state: CartState, ...items: [CatalogVariant, number][]) =>
    cartReducer(state, {
        type: 'add',
        items: items.map(([v, qty]) => ({ variant: v, qty })),
    });

describe('cartReducer', () => {
    it('adds new lines and merges the same product (one line per variant)', () => {
        const m = variant(1, 145_000);
        const l = variant(2, 145_000);
        let s = add(emptyCart('u1'), [m, 2000], [l, 1000]);
        expect(s.lines.map((x) => [x.productId, x.qty])).toEqual([
            [1, 2000],
            [2, 1000],
        ]);
        expect(s.selected).toBe(1);

        s = add(s, [m, 1000]);
        expect(s.lines).toHaveLength(2);
        expect(s.lines[0]?.qty).toBe(3000);
        expect(s.selected).toBe(0);
    });

    it('ignores zero quantities from the picker', () => {
        const s = add(emptyCart('u1'), [variant(1, 100), 0]);
        expect(s.lines).toEqual([]);
    });

    it('sets a quantity and removes the line at zero, keeping the selection in range', () => {
        let s = add(
            emptyCart('u1'),
            [variant(1, 100), 1000],
            [variant(2, 100), 1000],
        );
        s = cartReducer(s, { type: 'qty', key: '1', qty: 5000 });
        expect(s.lines[0]?.qty).toBe(5000);
        s = cartReducer(s, { type: 'qty', key: '2', qty: 0 });
        expect(s.lines.map((l) => l.key)).toEqual(['1']);
        expect(s.selected).toBe(0);
        s = cartReducer(s, { type: 'remove', key: '1' });
        expect(s.selected).toBe(-1);
    });

    it('moves the selection within the bill', () => {
        let s = add(
            emptyCart('u1'),
            [variant(1, 100), 1000],
            [variant(2, 100), 1000],
            [variant(3, 100), 1000],
        );
        s = cartReducer(s, { type: 'move', delta: -1 });
        expect(s.selected).toBe(1);
        s = cartReducer(s, { type: 'move', delta: -5 });
        expect(s.selected).toBe(0);
        s = cartReducer(s, { type: 'move', delta: 9 });
        expect(s.selected).toBe(2);
    });

    it('sets line and bill discounts and clears to a new bill id', () => {
        let s = add(emptyCart('u1'), [variant(1, 100_000), 1000]);
        s = cartReducer(s, {
            type: 'line_discount',
            key: '1',
            discount: { type: 'percent_bp', value: 1000 },
        });
        s = cartReducer(s, {
            type: 'order_discount',
            discount: { type: 'amount', value: 500 },
        });
        expect(s.lines[0]?.discount).toEqual({
            type: 'percent_bp',
            value: 1000,
        });
        expect(s.orderDiscount).toEqual({ type: 'amount', value: 500 });

        const cleared = cartReducer(s, { type: 'clear', uuid: 'u2' });
        expect(cleared).toEqual(emptyCart('u2'));
    });
});

describe('cartTotals', () => {
    it('equals calculateCart for the same input, including group discount, VAT and rounding', () => {
        let s = add(
            emptyCart('u1'),
            [variant(1, 145_050, { tax_rate_bp: 750 }), 2000],
            [variant(2, 189_000), 1500],
        );
        s = cartReducer(s, {
            type: 'line_discount',
            key: '1',
            discount: { type: 'amount', value: 10_000 },
        });
        s = cartReducer(s, {
            type: 'order_discount',
            discount: { type: 'percent_bp', value: 500 },
        });
        s = cartReducer(s, {
            type: 'customer',
            customer: customer({
                group: { name: 'Wholesale', discount_bp: 300 },
            }),
        });

        for (const mode of [
            { tax_inclusive: false, rounding_step: 0 },
            { tax_inclusive: true, rounding_step: 100 },
        ]) {
            const expected = calculateCart({
                lines: [
                    {
                        unit_price: 145_050,
                        qty_milli: 2000,
                        line_discount: { type: 'amount', value: 10_000 },
                        tax_rate_bp: 750,
                        tax_inclusive: mode.tax_inclusive,
                    },
                    {
                        unit_price: 189_000,
                        qty_milli: 1500,
                        line_discount: { type: 'none', value: 0 },
                        tax_rate_bp: 0,
                        tax_inclusive: mode.tax_inclusive,
                    },
                ],
                order_discount: { type: 'percent_bp', value: 500 },
                group_discount_bp: 300,
                rounding_step: mode.rounding_step,
                tenders: [],
            });
            expect(cartTotals(s, mode)).toEqual(expected);
        }
    });

    it('prices an empty bill at zero', () => {
        expect(cartTotals(emptyCart('u'), settings).order.grand_total).toBe(0);
    });
});

describe('exceedsCap', () => {
    it('flags a manual discount above the cap and ignores the group discount', () => {
        let s = add(emptyCart('u1'), [variant(1, 100_000), 1000]);
        s = cartReducer(s, {
            type: 'customer',
            customer: customer({ group: { name: 'VIP', discount_bp: 5000 } }),
        });
        expect(exceedsCap(cartTotals(s, settings), 0)).toBe(false);

        s = cartReducer(s, {
            type: 'line_discount',
            key: '1',
            discount: { type: 'percent_bp', value: 1000 },
        });
        expect(exceedsCap(cartTotals(s, settings), 1000)).toBe(false);
        expect(exceedsCap(cartTotals(s, settings), 999)).toBe(true);
    });
});
