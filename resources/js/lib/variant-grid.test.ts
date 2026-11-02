import { describe, expect, it } from 'vite-plus/test';
import {
    buildVariantGrid,
    defaultSku,
    rowKey,
    toggleId,
    type OptionValue,
} from './variant-grid';

const S: OptionValue = { id: 1, value: 'S', code: 'S' };
const M: OptionValue = { id: 2, value: 'M', code: 'M' };
const RED: OptionValue = { id: 10, value: 'Red', code: 'RE' };
const BLUE: OptionValue = { id: 11, value: 'Blue', code: 'BL' };

describe('buildVariantGrid', () => {
    it('crosses two options, option 1 outermost', () => {
        const rows = buildVariantGrid([S, M], [RED, BLUE], 'tee');
        expect(rows.map((r) => r.label)).toEqual([
            'S / Red',
            'S / Blue',
            'M / Red',
            'M / Blue',
        ]);
        expect(rows.map((r) => r.sku)).toEqual([
            'TEE-S-RE',
            'TEE-S-BL',
            'TEE-M-RE',
            'TEE-M-BL',
        ]);
        expect(rows[0]).toMatchObject({
            option1_value_id: 1,
            option2_value_id: 10,
            sale_price: null,
        });
    });

    it('makes one row per value with a single option', () => {
        const rows = buildVariantGrid([S, M], [], 'tee');
        expect(rows).toHaveLength(2);
        expect(rows[0]).toMatchObject({ option2_value_id: null, label: 'S', sku: 'TEE-S' });
    });

    it('is empty with nothing selected', () => {
        expect(buildVariantGrid([], [RED], 'tee')).toEqual([]);
    });

    it('keeps typed prices and a custom SKU when the chips change', () => {
        const first = buildVariantGrid([S], [RED], 'tee');
        first[0] = { ...first[0]!, sale_price: 55000, cost_price: 30000, sku: 'CUSTOM-1' };
        const next = buildVariantGrid([S, M], [RED], 'tee', first);
        expect(next[0]).toMatchObject({ sale_price: 55000, cost_price: 30000, sku: 'CUSTOM-1' });
        expect(next[1]).toMatchObject({ sale_price: null, sku: 'TEE-M-RE' });
    });

    it('re-derives an untouched SKU when the family code changes', () => {
        const first = buildVariantGrid([S], [], 'tee');
        const next = buildVariantGrid([S], [], 'shirt', first);
        expect(next[0]?.sku).toBe('SHIRT-S');
    });
});

describe('helpers', () => {
    it('builds keys and default SKUs', () => {
        expect(rowKey(1, null)).toBe('1-');
        expect(rowKey(1, 10)).toBe('1-10');
        expect(defaultSku(' ab ', S, null)).toBe('AB-S');
    });

    it('toggles a chip id', () => {
        expect(toggleId([1, 2], 3)).toEqual([1, 2, 3]);
        expect(toggleId([1, 2], 1)).toEqual([2]);
    });
});
