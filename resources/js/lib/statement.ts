import {
    detectPreset,
    presetRange,
    type DateRange,
    type RangePreset,
} from './date-range';

/** The period chips of the Customer Statement screen. */
export const STATEMENT_PRESETS = [
    'last7',
    'thisMonth',
    'lastMonth',
    'custom',
] as const;

export type StatementPreset = (typeof STATEMENT_PRESETS)[number];

const ISO = /^\d{4}-\d{2}-\d{2}$/;

export function statementRange(
    preset: Exclude<StatementPreset, 'custom'>,
    today: Date,
): DateRange {
    return presetRange(preset, today);
}

/** Which chip a range matches; "today" is not a chip here, so it reads as custom. */
export function statementPreset(
    range: DateRange,
    today: Date,
): StatementPreset {
    const found: RangePreset = detectPreset(range, today);

    return found === 'today' ? 'custom' : found;
}

export function isValidRange(range: DateRange): boolean {
    return ISO.test(range.from) && ISO.test(range.to) && range.from <= range.to;
}

/** `/app/customers/{uuid}/statement?from=&to=`, or the picker page when no customer is chosen yet. */
export function statementHref(
    customerUuid: string | null,
    range: DateRange,
): string {
    const base =
        customerUuid === null
            ? '/app/customers/statement'
            : `/app/customers/${customerUuid}/statement`;

    return `${base}?from=${range.from}&to=${range.to}`;
}
