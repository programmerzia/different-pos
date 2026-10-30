/**
 * Money as integer paisa (1 taka = 100 paisa). 04_TRD §3.4.
 *
 * TypeScript twin of app/Support/Money/Money.php. Both run tests/fixtures/money/cases.json.
 * Rules: no floats, ever. Rounding is half-up (ties away from zero). Intermediate products use
 * BigInt so they cannot overflow. Every result must stay within Number.MAX_SAFE_INTEGER.
 */
import { groupIndian, toBanglaDigits, toLatinDigits } from './digits';

export type Paisa = number;

export class MoneyError extends Error {
    override name = 'MoneyError';
}

export const MAX_PAISA = Number.MAX_SAFE_INTEGER;
export const MONEY_SYMBOL = '৳';

const NUMBER_SYNTAX = /^(-?)(\d+)(?:\.(\d+))?$/;

export function assertPaisa(value: number): Paisa {
    if (!Number.isSafeInteger(value)) {
        throw new MoneyError(`${value} is out of range or not integer paisa.`);
    }
    return value;
}

function fromBigInt(value: bigint, context: string): Paisa {
    if (value > BigInt(MAX_PAISA) || value < -BigInt(MAX_PAISA)) {
        throw new MoneyError(`"${context}" is out of range.`);
    }
    return Number(value);
}

function clean(input: string): string {
    return toLatinDigits(input.trim()).replace(/[,৳\s ]/g, '');
}

function parseDecimal(input: string, rounded: boolean): Paisa {
    const match = NUMBER_SYNTAX.exec(clean(input));
    if (!match) {
        throw new MoneyError(`"${input}" is not a valid amount.`);
    }
    const [, sign = '', whole = '0', fractionRaw = ''] = match;
    const fraction = fractionRaw.replace(/0+$/, '');

    if (!rounded && fraction.length > 2) {
        throw new MoneyError(`"${input}" has more than two decimal places.`);
    }

    let paisa =
        BigInt(whole) * 100n + BigInt(fraction.slice(0, 2).padEnd(2, '0'));
    // Half-up: only the first discarded digit decides.
    if (fraction.length > 2 && Number(fraction[2]) >= 5) {
        paisa += 1n;
    }

    return fromBigInt(sign === '-' ? -paisa : paisa, input);
}

/** Parses user input ("1,234.50", "৳ 1234.5", "১২৩৪.৫০"). More than 2 decimals is rejected. */
export function parseMoney(input: string): Paisa {
    return parseDecimal(input, false);
}

/** Like parseMoney but rounds extra decimals half-up. Only for known-imprecise sources. */
export function parseMoneyRounded(input: string): Paisa {
    return parseDecimal(input, true);
}

/** round_half_up(a × b ÷ divisor), exact for any safe-integer inputs. */
export function mulDivHalfUp(a: number, b: number, divisor: number): Paisa {
    const product = BigInt(assertPaisa(a)) * BigInt(assertPaisa(b));
    const d = BigInt(divisor);
    const negative = product < 0n;
    const abs = negative ? -product : product;
    const rounded = (abs * 2n + d) / (2n * d);
    return fromBigInt(negative ? -rounded : rounded, `${a} × ${b}`);
}

export function addMoney(...amounts: Paisa[]): Paisa {
    return fromBigInt(
        amounts.reduce((sum, amount) => sum + BigInt(assertPaisa(amount)), 0n),
        'sum',
    );
}

export function subtractMoney(a: Paisa, b: Paisa): Paisa {
    return fromBigInt(
        BigInt(assertPaisa(a)) - BigInt(assertPaisa(b)),
        'difference',
    );
}

/** Unit price × quantity (milli-units), rounded half-up to the paisa. */
export function timesQty(paisa: Paisa, qtyMilli: number): Paisa {
    return mulDivHalfUp(paisa, qtyMilli, 1000);
}

/** A rate in basis points (1500 = 15.00 %), rounded half-up to the paisa. */
export function percentOfBp(paisa: Paisa, basisPoints: number): Paisa {
    return mulDivHalfUp(paisa, basisPoints, 10_000);
}

/**
 * Splits an amount in proportion to the weights; the parts always sum to the amount.
 * Largest-remainder method, ties go to the lower index (same as Money::allocate()).
 */
export function allocateMoney(paisa: Paisa, weights: number[]): Paisa[] {
    if (weights.length === 0) {
        throw new MoneyError('Cannot allocate money: no weights.');
    }
    if (weights.some((w) => !Number.isSafeInteger(w) || w < 0)) {
        throw new MoneyError('Cannot allocate money: negative weight.');
    }
    const sum = weights.reduce((s, w) => s + BigInt(w), 0n);
    if (sum === 0n) {
        throw new MoneyError('Cannot allocate money: weights sum to zero.');
    }

    const total = BigInt(Math.abs(assertPaisa(paisa)));
    const sign = paisa < 0 ? -1 : 1;
    const parts = weights.map((w) => (total * BigInt(w)) / sum);
    const remainders = weights.map((w) => (total * BigInt(w)) % sum);
    let left = Number(total - parts.reduce((s, p) => s + p, 0n));

    const order = weights
        .map((_, index) => index)
        .sort((a, b) => {
            const ra = remainders[a] ?? 0n;
            const rb = remainders[b] ?? 0n;
            if (ra !== rb) {
                return rb > ra ? 1 : -1;
            }
            return a - b;
        });

    for (const index of order) {
        if (left === 0) {
            break;
        }
        parts[index] = (parts[index] ?? 0n) + 1n;
        left--;
    }

    return parts.map((part) => Number(sign < 0 ? -part : part)); // BigInt has no -0
}

/** "1234.50": for exports and logs, never for display. */
export function moneyToDecimalString(paisa: Paisa): string {
    const abs = Math.abs(assertPaisa(paisa));
    const text = `${Math.trunc(abs / 100)}.${String(abs % 100).padStart(2, '0')}`;
    return paisa < 0 ? `-${text}` : text;
}

/** Display: "৳1,23,456.50", "-৳50.00"; Bangla digits for "bn". Same as MoneyFormatter.php. */
export function formatMoney(
    paisa: Paisa,
    locale: 'en' | 'bn' = 'en',
    symbol = true,
): string {
    const abs = Math.abs(assertPaisa(paisa));
    const taka = Math.trunc(abs / 100);
    const text =
        (paisa < 0 ? '-' : '') +
        (symbol ? MONEY_SYMBOL : '') +
        `${groupIndian(String(taka))}.${String(abs % 100).padStart(2, '0')}`;
    return locale === 'bn' ? toBanglaDigits(text) : text;
}
