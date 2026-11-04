import { describe, expect, it } from 'vite-plus/test';
import {
    checkTender,
    exchangeNeeds,
    exchangeResult,
    giveBackValue,
    parseGiveBack,
    settlementSummary,
    shortLines,
    type ExchangeQuote,
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
        ).toEqual({
            mode: 'refund',
            methodId: 7,
        });
    });

    it('rejects junk', () => {
        expect(parseGiveBack('')).toBeNull();
        expect(parseGiveBack('mx')).toBeNull();
        expect(giveBackValue(null)).toBe('');
    });
});

const quote = (over: Partial<ExchangeQuote> = {}): ExchangeQuote => ({
    return_value: 450_00,
    replacement_total: 450_00,
    replacement_tax: 0,
    group_discount: 0,
    pays: 0,
    surplus: 0,
    due_cleared: 0,
    gets_back: 0,
    original_due: 0,
    walk_in: false,
    lines: [],
    ...over,
});

describe('exchangeNeeds', () => {
    it('lists what is still missing', () => {
        expect(exchangeNeeds(0, 0)).toEqual(['return', 'replacement']);
        expect(exchangeNeeds(1, 0)).toEqual(['replacement']);
        expect(exchangeNeeds(0, 2)).toEqual(['return']);
        expect(exchangeNeeds(1, 2)).toEqual([]);
    });
});

describe('exchangeResult', () => {
    it('is a single result line', () => {
        expect(exchangeResult(quote({ pays: 50_00 }))).toEqual({
            kind: 'pays',
            amount: 50_00,
        });
        expect(exchangeResult(quote())).toEqual({ kind: 'even' });
        expect(
            exchangeResult(
                quote({
                    surplus: 150_00,
                    due_cleared: 100_00,
                    gets_back: 50_00,
                }),
            ),
        ).toEqual({ kind: 'surplus', dueCleared: 100_00, getsBack: 50_00 });
        expect(
            exchangeResult(
                quote({ surplus: 80_00, due_cleared: 80_00, gets_back: 0 }),
            ),
        ).toEqual({ kind: 'surplus', dueCleared: 80_00, getsBack: 0 });
    });
});

describe('shortLines', () => {
    it('keeps only lines the branch cannot cover', () => {
        const line = {
            product_id: 1,
            name: 'A',
            sku: 'A',
            qty: 2000,
            total: 1,
            stock: 1000,
        };
        expect(
            shortLines(
                quote({
                    lines: [
                        { ...line, short: true },
                        { ...line, product_id: 2, short: false },
                    ],
                }),
            ).map((l) => l.product_id),
        ).toEqual([1]);
    });
});

describe('checkTender', () => {
    const cash = { type: 'cash', requires_reference: false };
    const bkash = { type: 'mobile_wallet', requires_reference: true };
    const base = { pays: 100_00, reference: '', walkIn: false };

    it('gives change from cash only', () => {
        expect(
            checkTender({ ...base, received: 120_00, method: cash }),
        ).toEqual({
            change: 20_00,
            onAccount: 0,
            problem: null,
        });
        expect(
            checkTender({
                ...base,
                received: 120_00,
                method: { ...bkash, requires_reference: false },
            }).problem,
        ).toBe('overpay_non_cash');
    });

    it('leaves the shortfall on a named customer account', () => {
        expect(checkTender({ ...base, received: 40_00, method: cash })).toEqual(
            {
                change: 0,
                onAccount: 60_00,
                problem: null,
            },
        );
        expect(
            checkTender({ ...base, received: 0, method: cash }).onAccount,
        ).toBe(100_00);
    });

    it('makes a walk-in pay in full', () => {
        expect(
            checkTender({
                ...base,
                walkIn: true,
                received: 99_99,
                method: cash,
            }).problem,
        ).toBe('walk_in_short');
        expect(
            checkTender({
                ...base,
                walkIn: true,
                received: 100_00,
                method: cash,
            }).problem,
        ).toBeNull();
    });

    it('wants the reference once money goes through a method that needs one', () => {
        expect(
            checkTender({ ...base, received: 100_00, method: bkash }).problem,
        ).toBe('reference');
        expect(
            checkTender({
                ...base,
                received: 100_00,
                method: bkash,
                reference: ' TX1 ',
            }).problem,
        ).toBeNull();
        expect(
            checkTender({ ...base, received: 0, method: bkash }).problem,
        ).toBeNull();
    });

    it('needs nothing when nothing is owed', () => {
        expect(
            checkTender({ ...base, pays: 0, received: 0, method: null }),
        ).toEqual({
            change: 0,
            onAccount: 0,
            problem: null,
        });
    });
});
