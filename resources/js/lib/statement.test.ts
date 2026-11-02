import { describe, expect, it } from 'vite-plus/test';
import {
    isValidRange,
    statementHref,
    statementPreset,
    statementRange,
} from './statement';

const today = new Date(2026, 10, 3); // 3 Nov 2026

describe('statement period', () => {
    it('last 7 days includes today and the 6 days before', () => {
        expect(statementRange('last7', today)).toEqual({
            from: '2026-10-28',
            to: '2026-11-03',
        });
    });

    it('this month runs from the 1st to today, last month is the whole previous month', () => {
        expect(statementRange('thisMonth', today)).toEqual({
            from: '2026-11-01',
            to: '2026-11-03',
        });
        expect(statementRange('lastMonth', today)).toEqual({
            from: '2026-10-01',
            to: '2026-10-31',
        });
    });

    it('detects the chip of a range, and treats a single day as custom', () => {
        expect(statementPreset(statementRange('lastMonth', today), today)).toBe(
            'lastMonth',
        );
        expect(
            statementPreset({ from: '2026-11-03', to: '2026-11-03' }, today),
        ).toBe('custom');
        expect(
            statementPreset({ from: '2026-01-01', to: '2026-02-01' }, today),
        ).toBe('custom');
    });

    it('validates ordering and format', () => {
        expect(isValidRange({ from: '2026-11-01', to: '2026-11-03' })).toBe(
            true,
        );
        expect(isValidRange({ from: '2026-11-04', to: '2026-11-03' })).toBe(
            false,
        );
        expect(isValidRange({ from: '2026-11-1', to: '2026-11-03' })).toBe(
            false,
        );
    });

    it('builds the statement url', () => {
        const range = { from: '2026-11-01', to: '2026-11-03' };
        expect(statementHref('abc', range)).toBe(
            '/app/customers/abc/statement?from=2026-11-01&to=2026-11-03',
        );
        expect(statementHref(null, range)).toBe(
            '/app/customers/statement?from=2026-11-01&to=2026-11-03',
        );
    });
});
