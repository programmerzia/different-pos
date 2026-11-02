import { describe, expect, it } from 'vite-plus/test';
import { parsePercentBp } from './components/discount-dialog';
import { groupVariants } from './components/variant-picker';
import type { CatalogVariant } from './types';

const v = (
    id: number,
    option1: string | null,
    option2: string | null,
): CatalogVariant => ({
    id,
    name: 'Panjabi',
    sku: `P-${id}`,
    barcode: null,
    label: null,
    option1,
    option2,
    price: 100,
    tax_rate_bp: 0,
    stock: 1000,
    allows_decimal: false,
});

describe('variant picker grouping', () => {
    it('groups by the option with fewer values (colour), rows are sizes', () => {
        const groups = groupVariants([
            v(1, 'M', 'Red'),
            v(2, 'L', 'Red'),
            v(3, 'XL', 'Red'),
            v(4, 'M', 'Blue'),
        ]);
        expect(groups.map((g) => [g.label, g.rows.map((r) => r.id)])).toEqual([
            ['Red', [1, 2, 3]],
            ['Blue', [4]],
        ]);
    });

    it('keeps one group when there is a single option', () => {
        expect(groupVariants([v(1, 'M', null), v(2, 'L', null)])).toEqual([
            { label: '', rows: [v(1, 'M', null), v(2, 'L', null)] },
        ]);
    });
});

describe('parsePercentBp', () => {
    it('reads percentages as integer basis points', () => {
        expect(parsePercentBp('12.5')).toBe(1250);
        expect(parsePercentBp('১০')).toBe(1000);
        expect(parsePercentBp('')).toBe(0);
        expect(parsePercentBp('100')).toBe(10_000);
    });

    it('rejects more than 100 %, negatives and junk', () => {
        expect(parsePercentBp('100.01')).toBeNull();
        expect(parsePercentBp('-1')).toBeNull();
        expect(parsePercentBp('abc')).toBeNull();
        expect(parsePercentBp('1.234')).toBeNull();
    });
});
