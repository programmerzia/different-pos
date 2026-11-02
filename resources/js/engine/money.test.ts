import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vite-plus/test';
import {
    allocateMoney,
    formatMoney,
    moneyToDecimalString,
    parseMoney,
    parseMoneyRounded,
    percentOfBp,
    timesQty,
} from './money';
import { formatQty, parseQty, qtyToDecimalString } from './quantity';

type Case = Record<string, unknown> & { error?: true };

const cases = JSON.parse(
    readFileSync(
        resolve(__dirname, '../../../tests/fixtures/money/cases.json'),
        'utf8',
    ),
) as Record<string, Case[]>;

const group = (name: string): Case[] => cases[name] ?? [];
const label = (c: Case): string => JSON.stringify(c);

describe('money parity with PHP', () => {
    it.each(group('parse').map((c) => [label(c), c] as const))(
        'parse %s',
        (_, c) => {
            if (c.error) {
                expect(() => parseMoney(c.in as string)).toThrow();
            } else {
                expect(parseMoney(c.in as string)).toBe(c.out);
            }
        },
    );

    it.each(group('parseRounded').map((c) => [label(c), c] as const))(
        'parseRounded %s',
        (_, c) => {
            expect(parseMoneyRounded(c.in as string)).toBe(c.out);
        },
    );

    it.each(group('timesQty').map((c) => [label(c), c] as const))(
        'timesQty %s',
        (_, c) => {
            const run = (): number =>
                timesQty(c.paisa as number, c.milli as number);
            if (c.error) {
                expect(run).toThrow();
            } else {
                expect(run()).toBe(c.out);
            }
        },
    );

    it.each(group('percentOfBp').map((c) => [label(c), c] as const))(
        'percentOfBp %s',
        (_, c) => {
            expect(percentOfBp(c.paisa as number, c.bp as number)).toBe(c.out);
        },
    );

    it.each(group('allocate').map((c) => [label(c), c] as const))(
        'allocate %s',
        (_, c) => {
            const run = (): number[] =>
                allocateMoney(c.paisa as number, c.weights as number[]);
            if (c.error) {
                expect(run).toThrow();
            } else {
                expect(run()).toStrictEqual(c.out);
            }
        },
    );

    it.each(group('format').map((c) => [label(c), c] as const))(
        'format %s',
        (_, c) => {
            const symbol = (c.symbol as boolean | undefined) ?? true;
            expect(formatMoney(c.paisa as number, 'en', symbol)).toBe(c.en);
            expect(formatMoney(c.paisa as number, 'bn', symbol)).toBe(c.bn);
        },
    );

    it.each(group('toDecimalString').map((c) => [label(c), c] as const))(
        'toDecimalString %s',
        (_, c) => {
            expect(moneyToDecimalString(c.paisa as number)).toBe(c.out);
        },
    );
});

describe('quantity parity with PHP', () => {
    it.each(group('qtyParse').map((c) => [label(c), c] as const))(
        'parse %s',
        (_, c) => {
            if (c.error) {
                expect(() => parseQty(c.in as string)).toThrow();
            } else {
                expect(parseQty(c.in as string)).toBe(c.out);
            }
        },
    );

    it.each(group('qtyFormat').map((c) => [label(c), c] as const))(
        'format %s',
        (_, c) => {
            expect(formatQty(c.milli as number, 'en')).toBe(c.en);
            expect(formatQty(c.milli as number, 'bn')).toBe(c.bn);
        },
    );

    it.each(group('qtyDecimal').map((c) => [label(c), c] as const))(
        'decimal %s',
        (_, c) => {
            expect(qtyToDecimalString(c.milli as number)).toBe(c.out);
        },
    );
});

describe('allocation invariant', () => {
    it('parts always sum to the total', () => {
        let seed = 20261031;
        const rand = (max: number): number => {
            seed = (seed * 1103515245 + 12345) % 2147483648;
            return seed % (max + 1);
        };
        for (let i = 0; i < 2000; i++) {
            const total = rand(2_000_000) - 1_000_000;
            const weights: number[] = Array.from({ length: rand(7) + 1 }, () =>
                rand(50),
            );
            if (weights.reduce((sum, w) => sum + w, 0) === 0) {
                weights[0] = 1;
            }
            const parts = allocateMoney(total, weights);
            expect(parts.reduce((s, p) => s + p, 0)).toBe(total);
        }
    });
});
