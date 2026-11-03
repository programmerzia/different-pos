import { describe, expect, it } from 'vite-plus/test';
import {
    axisTicks,
    compactTaka,
    deltaPercent,
    donutArcs,
    heatGrid,
    heatOpacity,
    heatSlot,
    hourWindow,
    labelEvery,
    linePoints,
    niceMax,
    pointsAttr,
    sharePercent,
} from './chart-helpers';

const hours = (nets: Record<number, number>) =>
    Array.from({ length: 24 }, (_, h) => ({
        key: String(h).padStart(2, '0'),
        net: nets[h] ?? 0,
        previous: 0,
    }));

describe('deltaPercent / sharePercent', () => {
    it('rounds the change to a whole percent', () => {
        expect(deltaPercent(114_200, 100_000)).toBe(14);
        expect(deltaPercent(96_600, 100_000)).toBe(-3);
        expect(deltaPercent(0, 100_000)).toBe(-100);
    });

    it('has no delta without a previous value', () => {
        expect(deltaPercent(5_000, 0)).toBeUndefined();
    });

    it('shares are 0 of nothing', () => {
        expect(sharePercent(58, 100)).toBe(58);
        expect(sharePercent(10, 0)).toBe(0);
    });
});

describe('axis', () => {
    it('rounds the maximum up to a nice whole-taka step', () => {
        expect(niceMax(8_425_000)).toBe(10_000_000); // ৳84,250 → ৳1,00,000
        expect(niceMax(4_100_000)).toBe(8_000_000); // ৳41,000 → ৳80,000 (৳20k steps)
        expect(niceMax(0)).toBe(10_000); // floor ৳100
        expect(niceMax(123_456_789)).toBe(200_000_000);
    });

    it('splits the axis into whole-taka ticks', () => {
        expect(axisTicks(10_000_000)).toEqual([
            0, 2_500_000, 5_000_000, 7_500_000, 10_000_000,
        ]);
    });

    it('labels amounts in k / lakh / crore', () => {
        expect(compactTaka(0)).toBe('৳0');
        expect(compactTaka(95_000)).toBe('৳950');
        expect(compactTaka(2_500_000)).toBe('৳25k');
        expect(compactTaka(15_000_000)).toBe('৳1.5L');
        expect(compactTaka(2_000_000_000)).toBe('৳2Cr');
        expect(compactTaka(-2_500_000)).toBe('-৳25k');
    });
});

describe('hourWindow', () => {
    it('shows at least the 10:00–22:00 shop day', () => {
        const window = hourWindow(hours({ 12: 100 }));
        expect(window[0]?.key).toBe('10');
        expect(window.at(-1)?.key).toBe('22');
    });

    it('widens to early and late sales', () => {
        const window = hourWindow(hours({ 8: 100, 23: 5 }));
        expect(window[0]?.key).toBe('08');
        expect(window.at(-1)?.key).toBe('23');
    });
});

describe('line geometry', () => {
    const plot = {
        width: 100,
        height: 60,
        left: 10,
        right: 10,
        top: 10,
        bottom: 10,
    };

    it('spreads values across the plot, 0 at the bottom', () => {
        expect(linePoints([0, 50, 100], 100, plot)).toEqual([
            { x: 10, y: 50 },
            { x: 50, y: 30 },
            { x: 90, y: 10 },
        ]);
    });

    it('centres a single value and clamps negatives to the axis', () => {
        expect(linePoints([-5], 100, plot)).toEqual([{ x: 50, y: 50 }]);
        expect(pointsAttr([{ x: 1.234, y: 2 }])).toBe('1.23,2');
    });

    it('prints at most n labels, always the last', () => {
        const show = labelEvery(30, 8);
        const shown = Array.from({ length: 30 }, (_, i) => i).filter(show);
        expect(shown.length).toBeLessThanOrEqual(8);
        expect(shown).toContain(0);
        expect(shown).toContain(29);
        expect(labelEvery(5)(3)).toBe(true);
    });
});

describe('donutArcs', () => {
    it('closes the ring in input order', () => {
        const arcs = donutArcs([58, 26, 16], 100);
        expect(arcs.map((a) => a.percent)).toEqual([58, 26, 16]);
        expect(arcs.map((a) => a.offset)).toEqual([-0, -58, -84]);
        expect(arcs.reduce((s, a) => s + a.length, 0)).toBeCloseTo(100);
    });

    it('draws nothing for no money', () => {
        expect(donutArcs([0, 0], 100).map((a) => a.length)).toEqual([0, 0]);
    });
});

describe('heat-map', () => {
    it('buckets hours into two-hour slots', () => {
        expect([8, 9, 10, 11, 12, 13, 20, 21, 23].map(heatSlot)).toEqual([
            0, 0, 0, 1, 1, 2, 5, 6, 6,
        ]);
    });

    it('puts Saturday first and sums cells of a slot', () => {
        const { grid, max } = heatGrid([
            { dow: 6, hour: 10, count: 2 },
            { dow: 6, hour: 9, count: 1 },
            { dow: 5, hour: 22, count: 7 },
        ]);
        expect(grid[0]?.[0]).toBe(3);
        expect(grid[6]?.[6]).toBe(7);
        expect(max).toBe(7);
    });

    it('keeps empty cells faint and the busiest solid', () => {
        expect(heatOpacity(0, 7)).toBe(0.08);
        expect(heatOpacity(7, 7)).toBe(1);
        expect(heatOpacity(3, 0)).toBe(0.08);
    });
});
