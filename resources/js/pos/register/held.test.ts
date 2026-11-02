import { describe, expect, it } from 'vite-plus/test';
import { cartReducer, emptyCart } from './cart';
import {
    heldKey,
    holdCart,
    loadHeld,
    MAX_HELD,
    parseHeld,
    resumeHeld,
    saveHeld,
    serializeHeld,
} from './held';
import type { CartState } from './types';

const bill = (uuid: string): CartState => {
    const s = cartReducer(emptyCart(uuid), {
        type: 'add',
        items: [
            {
                variant: {
                    id: 7,
                    name: 'Jeans 32',
                    sku: 'JN-32',
                    barcode: '890',
                    label: '32',
                    option1: '32',
                    option2: null,
                    price: 189_000,
                    tax_rate_bp: 0,
                    stock: null,
                    allows_decimal: false,
                },
                qty: 2000,
            },
        ],
    });

    return {
        ...cartReducer(s, {
            type: 'order_discount',
            discount: { type: 'amount', value: 5000 },
        }),
        approval: {
            token: 'secret',
            managerName: 'Karim',
            capBp: 2000,
            expiresAt: Date.now() + 60_000,
        },
    };
};

class MemoryStorage {
    data = new Map<string, string>();
    getItem = (k: string) => this.data.get(k) ?? null;
    setItem = (k: string, v: string) => void this.data.set(k, v);
    removeItem = (k: string) => void this.data.delete(k);
}

describe('held bills', () => {
    it('round-trips through JSON without the manager approval', () => {
        const list = holdCart([], bill('a'), new Date('2026-11-03T08:00:00Z'));
        const back = parseHeld(serializeHeld(list));
        expect(back).toHaveLength(1);
        expect(back[0]?.heldAt).toBe('2026-11-03T08:00:00.000Z');
        expect(back[0]?.state.lines).toEqual(list[0]?.state.lines);
        expect(back[0]?.state.orderDiscount).toEqual({
            type: 'amount',
            value: 5000,
        });
        expect(back[0]?.state.approval).toBeNull();
        expect(serializeHeld(list)).not.toContain('secret');
    });

    it('keeps the newest first, replaces the same bill and caps the list', () => {
        let list = holdCart([], bill('a'));
        list = holdCart(list, bill('b'));
        list = holdCart(list, bill('a'));
        expect(list.map((h) => h.id)).toEqual(['a', 'b']);

        for (let i = 0; i < MAX_HELD + 5; i++) {
            list = holdCart(list, bill(`x${i}`));
        }
        expect(list).toHaveLength(MAX_HELD);
    });

    it('resumes by id and removes it from the list', () => {
        const list = holdCart(holdCart([], bill('a')), bill('b'));
        const { state, list: rest } = resumeHeld(list, 'a');
        expect(state?.clientUuid).toBe('a');
        expect(rest.map((h) => h.id)).toEqual(['b']);
        expect(resumeHeld(rest, 'nope').state).toBeNull();
    });

    it('drops malformed entries instead of failing', () => {
        expect(parseHeld('not json')).toEqual([]);
        expect(parseHeld('{"a":1}')).toEqual([]);
        expect(
            parseHeld(
                JSON.stringify([
                    {
                        id: 'x',
                        heldAt: 'now',
                        state: { lines: [{ key: 1 }], clientUuid: 'x' },
                    },
                ]),
            ),
        ).toEqual([]);
        // A float quantity is not milli-units.
        const good = JSON.parse(serializeHeld(holdCart([], bill('a')))) as {
            state: { lines: { qty: number }[] };
        }[];
        if (good[0]?.state.lines[0]) {
            good[0].state.lines[0].qty = 1.5;
        }
        expect(parseHeld(JSON.stringify(good))).toEqual([]);
    });

    it('stores per branch and register, and removes the key when empty', () => {
        const storage = new MemoryStorage();
        const key = heldKey(3, 12);
        expect(key).not.toBe(heldKey(3, null));
        saveHeld(storage, key, holdCart([], bill('a')));
        expect(loadHeld(storage, key)).toHaveLength(1);
        expect(loadHeld(storage, heldKey(3, 13))).toEqual([]);
        saveHeld(storage, key, []);
        expect(storage.data.has(key)).toBe(false);
    });
});
