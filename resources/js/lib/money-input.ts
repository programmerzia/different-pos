import { toLatinDigits } from '@/engine/digits';
import { MoneyError, parseMoney, type Paisa } from '@/engine/money';

/** Keeps only digits and one ".", at most two decimals; Bangla digits are normalised to 0-9. */
export function sanitizeMoneyText(raw: string): string {
    const latin = toLatinDigits(raw).replace(/[^0-9.]/g, '');
    const dot = latin.indexOf('.');
    if (dot === -1) {
        return latin;
    }
    const whole = latin.slice(0, dot);
    const fraction = latin
        .slice(dot + 1)
        .replace(/\./g, '')
        .slice(0, 2);

    return `${whole}.${fraction}`;
}

/** '' (or a lone ".") → null; otherwise integer paisa. Never uses floats. */
export function paisaFromText(text: string): Paisa | null {
    const clean = sanitizeMoneyText(text);
    if (clean === '' || clean === '.') {
        return null;
    }
    try {
        return parseMoney(clean.endsWith('.') ? clean.slice(0, -1) : clean);
    } catch (error) {
        if (error instanceof MoneyError) {
            return null;
        }
        throw error;
    }
}

/** Raw editing text for a value: "1234.5" → "1234.50"; no grouping while editing. */
export function textFromPaisa(paisa: Paisa | null): string {
    if (paisa === null) {
        return '';
    }
    const abs = Math.abs(paisa);
    const text = `${Math.trunc(abs / 100)}.${String(abs % 100).padStart(2, '0')}`;

    return paisa < 0 ? `-${text}` : text;
}
