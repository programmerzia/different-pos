import { describe, expect, it } from 'vite-plus/test';
import {
    agingBucket,
    allocateOldestFirst,
    allocateSelected,
    allocatedTotal,
    bucketTotals,
} from './dues';

describe('agingBucket', () => {
    it.each([
        [0, 'd0_30'],
        [30, 'd0_30'],
        [31, 'd31_60'],
        [60, 'd31_60'],
        [61, 'd61_90'],
        [90, 'd61_90'],
        [91, 'd90_plus'],
        [400, 'd90_plus'],
    ] as const)('%i days → %s', (days, bucket) => {
        expect(agingBucket(days)).toBe(bucket);
    });

    it('sums dues per bucket', () => {
        expect(
            bucketTotals([
                { due: 100, age: 3 },
                { due: 50, age: 45 },
                { due: 25, age: 45 },
                { due: 7, age: 120 },
            ]),
        ).toEqual({ d0_30: 100, d31_60: 75, d61_90: 0, d90_plus: 7 });
    });
});

describe('allocateOldestFirst', () => {
    const invoices = [
        { id: 1, due: 60_000 },
        { id: 2, due: 30_000 },
        { id: 3, due: 100_000 },
    ];

    it('settles the oldest dues first', () => {
        expect(allocateOldestFirst(invoices, 70_000)).toEqual({
            allocations: { 1: 60_000, 2: 10_000 },
            advance: 0,
        });
    });

    it('keeps an overpayment as an advance', () => {
        const result = allocateOldestFirst(invoices, 250_000);
        expect(result.advance).toBe(60_000);
        expect(allocatedTotal(result)).toBe(190_000);
    });

    it('skips settled invoices and ignores a non-positive amount', () => {
        expect(
            allocateOldestFirst([{ id: 9, due: 0 }, ...invoices], 1),
        ).toEqual({ allocations: { 1: 1 }, advance: 0 });
        expect(allocateOldestFirst(invoices, -5)).toEqual({
            allocations: {},
            advance: 0,
        });
    });

    it('allocates only to the chosen invoices', () => {
        expect(allocateSelected(invoices, new Set([3, 2]), 50_000)).toEqual({
            allocations: { 2: 30_000, 3: 20_000 },
            advance: 0,
        });
    });
});
