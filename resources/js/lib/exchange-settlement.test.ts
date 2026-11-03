import { describe, expect, it } from 'vite-plus/test';
import {
    giveBackValue,
    parseGiveBack,
    settlementSummary,
} from '@/lib/exchange-settlement';

describe('settlementSummary', () => {
    it('gives the whole value back on a refund or store credit, whatever the cart', () => {
        expect(settlementSummary('refund', 150_00, 0)).toEqual({
            kind: 'gets',
            amount: 150_00,
        });
        expect(settlementSummary('credit', 150_00, 999_00)).toEqual({
            kind: 'gets',
            amount: 150_00,
        });
    });

    it('nets an exchange against the replacement cart', () => {
        expect(settlementSummary('exchange', 150_00, 200_00)).toEqual({
            kind: 'pays',
            amount: 50_00,
        });
        expect(settlementSummary('exchange', 150_00, 100_00)).toEqual({
            kind: 'gets',
            amount: 50_00,
        });
        expect(settlementSummary('exchange', 150_00, 150_00)).toEqual({
            kind: 'even',
            amount: 0,
        });
    });

    it('is even before anything is chosen', () => {
        expect(settlementSummary('refund', 0, 0)).toEqual({
            kind: 'even',
            amount: 0,
        });
    });
});

describe('give back choice', () => {
    it('round-trips store credit and a refund method', () => {
        expect(parseGiveBack(giveBackValue({ mode: 'credit' }))).toEqual({
            mode: 'credit',
        });
        expect(
            parseGiveBack(giveBackValue({ mode: 'refund', methodId: 7 })),
        ).toEqual({ mode: 'refund', methodId: 7 });
    });

    it('rejects junk', () => {
        expect(parseGiveBack('')).toBeNull();
        expect(parseGiveBack('mx')).toBeNull();
        expect(giveBackValue(null)).toBe('');
    });
});
