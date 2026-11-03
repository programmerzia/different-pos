import type { KeyboardEvent, MouseEvent } from 'react';

/** Mockup "Date:" presets (icons_and_helpers.py datePresets): All · Today · Yesterday · This week · This month · Custom range. */
export const DATE_PRESETS = [
    '',
    'today',
    'yesterday',
    'week',
    'month',
    'custom',
] as const;
export type DatePreset = (typeof DATE_PRESETS)[number];

const iso = (d: Date): string =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

/** The from/to (inclusive, YYYY-MM-DD) a preset stands for; `custom` and `''` clear nothing themselves. */
export function presetRange(
    preset: DatePreset,
    now: Date = new Date(),
): { from: string; to: string } {
    const day = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const shift = (days: number) =>
        new Date(day.getFullYear(), day.getMonth(), day.getDate() + days);
    switch (preset) {
        case 'today':
            return { from: iso(day), to: iso(day) };
        case 'yesterday':
            return { from: iso(shift(-1)), to: iso(shift(-1)) };
        case 'week':
            return { from: iso(shift(-6)), to: iso(day) };
        case 'month':
            return {
                from: iso(new Date(day.getFullYear(), day.getMonth(), 1)),
                to: iso(day),
            };
        default:
            return { from: '', to: '' };
    }
}

/** Which preset a from/to pair matches (anything else set is `custom`). */
export function presetOf(
    from: string,
    to: string,
    now: Date = new Date(),
): DatePreset {
    if (!from && !to) {
        return '';
    }
    for (const preset of ['today', 'yesterday', 'week', 'month'] as const) {
        const range = presetRange(preset, now);
        if (range.from === from && range.to === to) {
            return preset;
        }
    }

    return 'custom';
}

const INTERACTIVE =
    'a,button,input,select,textarea,label,[role="checkbox"],[role="menuitem"]';

/**
 * Mockup lists open a record on a single row click (DataList onRowClick), on Enter and on double click.
 * Wraps a `<DataTable>` and delegates by row index, so the table keeps its own keyboard grid; clicks on
 * controls inside a row (checkbox, links, buttons) are left alone.
 */
export function rowOpenHandlers<Row>(
    rows: readonly Row[],
    open: (row: Row) => void,
) {
    const rowOf = (target: EventTarget | null): Row | undefined => {
        if (!(target instanceof Element)) {
            return undefined;
        }
        const tr = target.closest('tbody > tr');
        if (!tr?.parentElement || tr.closest('tfoot')) {
            return undefined;
        }

        return rows[Array.from(tr.parentElement.children).indexOf(tr)];
    };

    return {
        onClick: (event: MouseEvent<HTMLElement>) => {
            if (
                event.target instanceof Element &&
                event.target.closest(INTERACTIVE)
            ) {
                return;
            }
            const row = rowOf(event.target);
            if (row !== undefined) {
                open(row);
            }
        },
        onDoubleClickCapture: (event: MouseEvent<HTMLElement>) => {
            if (rowOf(event.target) !== undefined) {
                event.stopPropagation();
            }
        },
        onKeyDownCapture: (event: KeyboardEvent<HTMLElement>) => {
            if (
                event.key !== 'Enter' ||
                event.ctrlKey ||
                event.metaKey ||
                event.shiftKey ||
                event.altKey ||
                !(event.target instanceof HTMLTableRowElement)
            ) {
                return;
            }
            const row = rowOf(event.target);
            if (row !== undefined) {
                event.preventDefault();
                event.stopPropagation();
                open(row);
            }
        },
    };
}

/** Share of the credit limit in use (0–100, integer), or null when the customer has no limit. */
export function creditUse(
    balance: number,
    limit: number | null,
): number | null {
    if (limit === null) {
        return null;
    }
    if (limit <= 0) {
        return balance > 0 ? 100 : 0;
    }

    return Math.min(100, Math.max(0, Math.round((balance * 100) / limit)));
}
