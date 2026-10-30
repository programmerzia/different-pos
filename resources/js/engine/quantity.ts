/**
 * Quantity as integer milli-units (1.250 kg = 1250), stored server-side as DECIMAL(14,3).
 * TypeScript twin of app/Support/Quantity/Quantity.php. Both run tests/fixtures/money/cases.json.
 */
import { toBanglaDigits, toLatinDigits } from './digits';

export type QtyMilli = number;

export class QuantityError extends Error {
    override name = 'QuantityError';
}

export const QTY_SCALE = 1000;
/** DECIMAL(14,3) holds at most 11 integer digits. */
export const MAX_QTY_MILLI = 99_999_999_999_999;

const NUMBER_SYNTAX = /^(-?)(\d+)(?:\.(\d+))?$/;

/** Parses "1.25", "2", "১.৫". More than three decimals is rejected, never rounded. */
export function parseQty(input: string): QtyMilli {
    const match = NUMBER_SYNTAX.exec(
        toLatinDigits(input.trim()).replace(/[,\s]/g, ''),
    );
    if (!match) {
        throw new QuantityError(`"${input}" is not a valid quantity.`);
    }
    const [, sign = '', whole = '0', fractionRaw = ''] = match;
    const fraction = fractionRaw.replace(/0+$/, '');
    if (fraction.length > 3) {
        throw new QuantityError(
            `"${input}" has more than three decimal places.`,
        );
    }
    const milli = BigInt(whole) * 1000n + BigInt(fraction.padEnd(3, '0'));
    if (milli > BigInt(MAX_QTY_MILLI)) {
        throw new QuantityError(`"${input}" is out of range.`);
    }
    return Number(sign === '-' ? -milli : milli); // BigInt has no -0
}

export function isWholeQty(milli: QtyMilli): boolean {
    return milli % QTY_SCALE === 0;
}

/** "1.250": the exact DECIMAL(14,3) value. */
export function qtyToDecimalString(milli: QtyMilli): string {
    const abs = Math.abs(milli);
    const text = `${Math.trunc(abs / QTY_SCALE)}.${String(abs % QTY_SCALE).padStart(3, '0')}`;
    return milli < 0 ? `-${text}` : text;
}

/** Display: trailing zeros trimmed ("1.25", "2"); Bangla digits for "bn". */
export function formatQty(milli: QtyMilli, locale: 'en' | 'bn' = 'en'): string {
    const text = qtyToDecimalString(milli).replace(/\.?0+$/, '');
    return locale === 'bn' ? toBanglaDigits(text) : text;
}
