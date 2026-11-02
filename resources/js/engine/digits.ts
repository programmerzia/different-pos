/** Latin (0-9) ↔ Bangla (০-৯) digits and Indian grouping. */
const BANGLA = '০১২৩৪৫৬৭৮৯';

export function toLatinDigits(value: string): string {
    return value.replace(/[০-৯]/g, (d) => String(BANGLA.indexOf(d)));
}

export function toBanglaDigits(value: string): string {
    return value.replace(/[0-9]/g, (d) => BANGLA.charAt(Number(d)));
}

/** 1234567 → "12,34,567" (unsigned integer digits only). */
export function groupIndian(digits: string): string {
    if (digits.length <= 3) {
        return digits;
    }
    const last3 = digits.slice(-3);
    const rest = digits.slice(0, -3);
    const groups: string[] = [];
    for (let end = rest.length; end > 0; end -= 2) {
        groups.unshift(rest.slice(Math.max(0, end - 2), end));
    }
    return `${groups.join(',')},${last3}`;
}
