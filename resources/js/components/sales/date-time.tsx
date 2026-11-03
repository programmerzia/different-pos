import { useDigits } from '@/hooks/use-digits';

/**
 * Splits a server timestamp into its local date and time ("HH:mm"). The server already sends the
 * tenant's wall time, either as "Y-m-d H:i" or as ISO 8601 with the tenant offset
 * ("2026-11-03T18:26:27+06:00", BusinessDay::localIso), so nothing is converted to the browser zone.
 */
export function localStamp(value: string | null | undefined): {
    date: string;
    time: string;
} {
    const match = /^(\d{4}-\d{2}-\d{2})(?:[T ](\d{2}:\d{2}))?/.exec(
        value ?? '',
    );

    return match
        ? { date: match[1] ?? '', time: match[2] ?? '' }
        : { date: value ?? '', time: '' };
}

/** "2026-11-03 · 13:56": the date and the time never break inside (mockup "29 Oct 2026 · 19:35"). */
export function DateTime({ value }: { value: string | null }) {
    const digits = useDigits();
    const { date, time } = localStamp(value);

    return (
        <span className="num text-muted-foreground">
            <span className="whitespace-nowrap">{digits.n(date)}</span>
            {time ? (
                <>
                    {' · '}
                    <span className="whitespace-nowrap">{digits.n(time)}</span>
                </>
            ) : null}
        </span>
    );
}
