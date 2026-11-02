import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vite-plus/test';
import { calculateCart, PricingError, type CartInput, type CartTotals } from './pricing';

interface Case {
    name: string;
    input: CartInput;
    expected: CartTotals;
}

const file = JSON.parse(
    readFileSync(
        resolve(__dirname, '../../../tests/fixtures/pricing/cases.json'),
        'utf8',
    ),
) as { cases: Case[] };

describe('pricing parity with PHP', () => {
    it('has at least 40 golden cases', () => {
        expect(file.cases.length).toBeGreaterThanOrEqual(40);
    });

    it.each(file.cases.map((c) => [c.name, c] as const))('%s', (_, c) => {
        expect(calculateCart(c.input)).toEqual(c.expected);
    });

    it.each([
        ['unknown discount type', { order_discount: { type: 'bogus', value: 1 } }],
        ['negative discount', { order_discount: { type: 'amount', value: -1 } }],
        ['group over 100%', { group_discount_bp: 10_001 }],
        ['negative step', { rounding_step: -1 }],
    ])('rejects %s', (_, patch) => {
        const input = {
            lines: [],
            order_discount: { type: 'none', value: 0 },
            group_discount_bp: 0,
            rounding_step: 0,
            tenders: [],
            ...patch,
        } as CartInput;
        expect(() => calculateCart(input)).toThrow(PricingError);
    });
});
