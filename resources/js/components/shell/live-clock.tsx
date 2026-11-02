import { useEffect, useState } from 'react';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';

/** hh:mm:ss am/pm + day/date; tabular so it never shifts the layout. */
export function LiveClock() {
    const { locale } = useT();
    const digits = useDigits();
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const id = setInterval(() => setNow(new Date()), 1000);

        return () => clearInterval(id);
    }, []);

    const tag = locale === 'bn' ? 'bn-BD-u-nu-latn' : 'en-GB';
    const time = now.toLocaleTimeString(tag, {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    });
    const date = now.toLocaleDateString(tag, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });

    return (
        <div
            className="hidden flex-col items-end leading-tight xl:flex"
            aria-hidden="true"
        >
            <span className="num min-w-24 text-right text-sm">
                {digits.n(time)}
            </span>
            <span className="text-xs text-muted-foreground">
                {digits.n(date)}
            </span>
        </div>
    );
}
