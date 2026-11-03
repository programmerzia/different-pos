/**
 * Pure helpers behind the dashboard's hand-rolled SVG charts. Amounts are integer paisa; only the
 * pixel geometry is fractional.
 */
import type { DashboardHeatCell, DashboardTrendPoint } from '@/types/reports';

/** Whole-percent change vs the previous window; undefined when there is nothing to compare with. */
export function deltaPercent(now: number, before: number): number | undefined {
    if (before <= 0) {
        return undefined;
    }

    return Math.round(((now - before) / before) * 100);
}

/** Share of `part` in `total` as a whole percent (0 when the total is 0). */
export function sharePercent(part: number, total: number): number {
    return total <= 0 ? 0 : Math.round((part / total) * 100);
}

const NICE_STEPS = [1, 2, 2.5, 5, 10];

/**
 * A round axis maximum (whole taka, returned in paisa) at or above `paisa`, so `ticks` of it are
 * round too: 1, 2, 2.5 or 5 × 10ⁿ taka per quarter. Never below ৳100.
 */
export function niceMax(paisa: number, steps = 4): number {
    const taka = Math.max(100, Math.ceil(Math.max(0, paisa) / 100));
    const rawStep = taka / steps;
    const magnitude = 10 ** Math.floor(Math.log10(rawStep));
    const step =
        (NICE_STEPS.find((s) => s * magnitude >= rawStep) ?? 10) * magnitude;

    return Math.round(step * steps) * 100;
}

/** `steps + 1` evenly spaced axis values from 0 to `max` (paisa, rounded to whole taka). */
export function axisTicks(max: number, steps = 4): number[] {
    return Array.from(
        { length: steps + 1 },
        (_, i) => Math.round((max * i) / steps / 100) * 100,
    );
}

/** Short axis label in Bangladeshi units: ৳950, ৳25k, ৳1.5L, ৳2Cr (Latin digits; localise after). */
export function compactTaka(paisa: number): string {
    const taka = Math.round(paisa / 100);
    const sign = taka < 0 ? '-' : '';
    const abs = Math.abs(taka);
    const short = (n: number) => String(Number(n.toFixed(1)));

    if (abs >= 10_000_000) {
        return `${sign}৳${short(abs / 10_000_000)}Cr`;
    }
    if (abs >= 100_000) {
        return `${sign}৳${short(abs / 100_000)}L`;
    }
    if (abs >= 1_000) {
        return `${sign}৳${short(abs / 1_000)}k`;
    }

    return `${sign}৳${abs}`;
}

/**
 * The hours worth drawing for a one-day trend: from the first to the last hour with sales, widened
 * to at least 10:00–22:00 (the usual shop day).
 */
export function hourWindow(
    points: DashboardTrendPoint[],
): DashboardTrendPoint[] {
    const busy = points
        .map((p, i) => (p.net !== 0 || p.previous !== 0 ? i : -1))
        .filter((i) => i >= 0);
    const start = Math.min(10, ...busy);
    const end = Math.max(22, ...busy);

    return points.slice(start, end + 1);
}

export type Plot = {
    width: number;
    height: number;
    left: number;
    right: number;
    top: number;
    bottom: number;
};

/** x/y of each value on a plot whose y axis runs from 0 to `max`. */
export function linePoints(
    values: number[],
    max: number,
    plot: Plot,
): { x: number; y: number }[] {
    const innerW = plot.width - plot.left - plot.right;
    const innerH = plot.height - plot.top - plot.bottom;
    const n = values.length;

    return values.map((v, i) => ({
        x: plot.left + (n <= 1 ? innerW / 2 : (innerW * i) / (n - 1)),
        y: plot.top + innerH - (max <= 0 ? 0 : (Math.max(0, v) / max) * innerH),
    }));
}

/** SVG `points` attribute text. */
export function pointsAttr(points: { x: number; y: number }[]): string {
    return points.map((p) => `${round(p.x)},${round(p.y)}`).join(' ');
}

function round(n: number): number {
    return Math.round(n * 100) / 100;
}

/** Which of `count` x labels to print so that at most `max` show (always the first and the last). */
export function labelEvery(count: number, max = 8): (index: number) => boolean {
    const step = Math.max(1, Math.ceil((count - 1) / (max - 1)));

    return (i) =>
        i === count - 1 || (i % step === 0 && count - 1 - i >= step / 2);
}

/**
 * Donut arcs (stroke-dasharray / dashoffset on a circle of `circumference`), in input order.
 * Each arc's percent is rounded; the arcs always close the ring.
 */
export function donutArcs(
    amounts: number[],
    circumference: number,
): { length: number; offset: number; percent: number }[] {
    const total = amounts.reduce((a, b) => a + Math.max(0, b), 0);
    let start = 0;

    return amounts.map((amount) => {
        const length =
            total <= 0 ? 0 : (Math.max(0, amount) / total) * circumference;
        const arc = {
            length: round(length),
            offset: round(-start),
            percent: sharePercent(Math.max(0, amount), total),
        };
        start += length;

        return arc;
    });
}

/** Heat-map rows: Saturday first (the Bangladeshi week), as `Date#getDay()` numbers. */
export const HEAT_DAYS = [6, 0, 1, 2, 3, 4, 5] as const;

/** Heat-map columns: two-hour slots labelled by their middle hour (10 am … 10 pm). */
export const HEAT_SLOTS = [10, 12, 14, 16, 18, 20, 22] as const;

/** Slot index of a local hour: 9-10 → 10 am, 11-12 → 12 pm … 21+ → 10 pm; earlier hours join the first. */
export function heatSlot(hour: number): number {
    return Math.min(
        HEAT_SLOTS.length - 1,
        Math.max(0, Math.floor((hour - 9) / 2)),
    );
}

/** 7 × 7 grid of sale counts (rows HEAT_DAYS, columns HEAT_SLOTS) and its largest cell. */
export function heatGrid(cells: DashboardHeatCell[]): {
    grid: number[][];
    max: number;
} {
    const grid = HEAT_DAYS.map(() => HEAT_SLOTS.map(() => 0));
    for (const cell of cells) {
        const row = HEAT_DAYS.indexOf(cell.dow as (typeof HEAT_DAYS)[number]);
        if (row >= 0) {
            const cols = grid[row]!;
            const col = heatSlot(cell.hour);
            cols[col] = (cols[col] ?? 0) + cell.count;
        }
    }
    const max = Math.max(0, ...grid.flat());

    return { grid, max };
}

/** Cell opacity: empty cells stay faint, the busiest is solid. */
export function heatOpacity(count: number, max: number): number {
    if (max <= 0 || count <= 0) {
        return 0.08;
    }

    return round(0.2 + (0.8 * count) / max);
}
