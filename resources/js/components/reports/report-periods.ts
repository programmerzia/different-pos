/**
 * The reports directory's period presets (mockup: Today · Yesterday · This Week · This Month ·
 * Last Quarter · Fiscal Year). Weeks start on Saturday and the fiscal year on 1 July, as in
 * Bangladesh. Dates are local calendar days, YYYY-MM-DD.
 */
import { isoDate, startOfWeek, type DateRange } from '@/lib/date-range';

export const REPORT_PERIODS = [
    'today',
    'yesterday',
    'this_week',
    'this_month',
    'last_quarter',
    'fiscal_year',
] as const;

export type ReportPeriod = (typeof REPORT_PERIODS)[number];

export function reportPeriodRange(
    period: ReportPeriod,
    today: Date,
): DateRange {
    const y = today.getFullYear();
    const m = today.getMonth();
    const d = today.getDate();

    switch (period) {
        case 'today':
            return { from: isoDate(today), to: isoDate(today) };
        case 'yesterday': {
            const day = isoDate(new Date(y, m, d - 1));

            return { from: day, to: day };
        }
        case 'this_week':
            return { from: isoDate(startOfWeek(today)), to: isoDate(today) };
        case 'this_month':
            return { from: isoDate(new Date(y, m, 1)), to: isoDate(today) };
        case 'last_quarter': {
            const start = Math.floor(m / 3) * 3 - 3;

            return {
                from: isoDate(new Date(y, start, 1)),
                to: isoDate(new Date(y, start + 3, 0)),
            };
        }
        case 'fiscal_year': {
            const startYear = m >= 6 ? y : y - 1;

            return {
                from: isoDate(new Date(startYear, 6, 1)),
                to: isoDate(today),
            };
        }
    }
}

/** `2026-27` for the fiscal year that contains `today`. */
export function fiscalYearLabel(today: Date): string {
    const startYear =
        today.getMonth() >= 6 ? today.getFullYear() : today.getFullYear() - 1;

    return `${startYear}-${String((startYear + 1) % 100).padStart(2, '0')}`;
}
