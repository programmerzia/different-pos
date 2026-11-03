import { useDigits } from '@/hooks/use-digits';

/** "2026-11-03 · 13:56": the date and the time never break inside (mockup "29 Oct 2026 · 19:35"). */
export function DateTime({ value }: { value: string | null }) {
    const digits = useDigits();
    const [date = '', time = ''] = (value ?? '').split(' ');

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
