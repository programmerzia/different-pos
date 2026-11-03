export type RangePreset =
    | 'today'
    | 'last7'
    | 'thisMonth'
    | 'lastMonth'
    | 'custom';
export type DateRange = { from: string; to: string };

const pad = (n: number) => String(n).padStart(2, '0');

/** Local calendar date as YYYY-MM-DD (the API date format). */
export function isoDate(date: Date): string {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/**
 * The Saturday on or before `day` (local calendar day): the Bangladesh week. Every "This week" (list
 * toolbars and reports) starts here, as App\Support\Dates\Period::startOfWeek does on the server.
 */
export function startOfWeek(day: Date): Date {
    // getDay(): 0 Sun … 6 Sat.
    const sinceSaturday = (day.getDay() + 1) % 7;

    return new Date(
        day.getFullYear(),
        day.getMonth(),
        day.getDate() - sinceSaturday,
    );
}

export function presetRange(
    preset: Exclude<RangePreset, 'custom'>,
    today: Date,
): DateRange {
    const y = today.getFullYear();
    const m = today.getMonth();
    switch (preset) {
        case 'today':
            return { from: isoDate(today), to: isoDate(today) };
        case 'last7':
            return {
                from: isoDate(new Date(y, m, today.getDate() - 6)),
                to: isoDate(today),
            };
        case 'thisMonth':
            return { from: isoDate(new Date(y, m, 1)), to: isoDate(today) };
        case 'lastMonth':
            return {
                from: isoDate(new Date(y, m - 1, 1)),
                to: isoDate(new Date(y, m, 0)),
            };
    }
}

/** Which preset (if any) a range equals today; otherwise `custom`. */
export function detectPreset(range: DateRange, today: Date): RangePreset {
    for (const preset of [
        'today',
        'last7',
        'thisMonth',
        'lastMonth',
    ] as const) {
        const candidate = presetRange(preset, today);
        if (candidate.from === range.from && candidate.to === range.to) {
            return preset;
        }
    }

    return 'custom';
}
