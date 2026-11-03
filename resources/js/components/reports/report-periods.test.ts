import { describe, expect, it } from 'vite-plus/test';
import { fiscalYearLabel, reportPeriodRange } from './report-periods';

// Tuesday 3 November 2026
const today = new Date(2026, 10, 3);

describe('reportPeriodRange', () => {
    it('covers today and yesterday', () => {
        expect(reportPeriodRange('today', today)).toEqual({
            from: '2026-11-03',
            to: '2026-11-03',
        });
        expect(reportPeriodRange('yesterday', today)).toEqual({
            from: '2026-11-02',
            to: '2026-11-02',
        });
    });

    it('starts the week on Saturday', () => {
        expect(reportPeriodRange('this_week', today)).toEqual({
            from: '2026-10-31',
            to: '2026-11-03',
        });
        const saturday = new Date(2026, 9, 31);
        expect(reportPeriodRange('this_week', saturday).from).toBe(
            '2026-10-31',
        );
    });

    it('gives month to date and the previous calendar quarter', () => {
        expect(reportPeriodRange('this_month', today)).toEqual({
            from: '2026-11-01',
            to: '2026-11-03',
        });
        expect(reportPeriodRange('last_quarter', today)).toEqual({
            from: '2026-07-01',
            to: '2026-09-30',
        });
        expect(
            reportPeriodRange('last_quarter', new Date(2026, 1, 10)),
        ).toEqual({ from: '2025-10-01', to: '2025-12-31' });
    });

    it('starts the fiscal year on 1 July', () => {
        expect(reportPeriodRange('fiscal_year', today)).toEqual({
            from: '2026-07-01',
            to: '2026-11-03',
        });
        expect(
            reportPeriodRange('fiscal_year', new Date(2026, 5, 30)).from,
        ).toBe('2025-07-01');
        expect(fiscalYearLabel(today)).toBe('2026-27');
        expect(fiscalYearLabel(new Date(2026, 0, 5))).toBe('2025-26');
    });
});

describe('directoryCards', async () => {
    const { directoryCards, REPORT_CATALOGUE } = await import('./catalogue');

    it('enables only the reports the server lists', () => {
        const cards = directoryCards(['daily-cash', 'returns']);
        expect(cards).toHaveLength(REPORT_CATALOGUE.length);
        expect(cards.filter((c) => c.built).map((c) => c.report)).toEqual([
            'daily-cash',
            'returns',
        ]);
        expect(cards.find((c) => c.id === 'vat')?.built).toBe(false);
    });

    it('never hides a report the catalogue does not know', () => {
        const cards = directoryCards(['brand-new']);
        expect(cards.at(-1)).toMatchObject({
            id: 'brand-new',
            report: 'brand-new',
            built: true,
        });
    });
});
