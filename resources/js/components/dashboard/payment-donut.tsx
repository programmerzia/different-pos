import { donutArcs } from '@/components/dashboard/chart-helpers';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

/** Arc colours in order (mockup: cash success, bKash primary, card info, then the chart ramp). */
export const MIX_COLOURS = [
    'var(--success)',
    'var(--primary)',
    'var(--info)',
    'var(--warning)',
    'var(--chart-4)',
    'var(--chart-5)',
    'var(--chart-7)',
];

const R = 60;
const CIRCUMFERENCE = 2 * Math.PI * R;

/** Payment-method donut with the money total in the hole and a legend (mockup "Payment Method Mix"). */
export function PaymentDonut({
    mix,
}: {
    mix: { method: string; type: string; amount: number }[];
}) {
    const { t } = useT();
    const digits = useDigits();
    const shown = mix.slice(0, MIX_COLOURS.length);
    const total = mix.reduce((s, m) => s + m.amount, 0);
    const arcs = donutArcs(
        shown.map((m) => m.amount),
        CIRCUMFERENCE,
    );
    const totalText = digits.money(total);

    return (
        <div className="flex flex-col">
            <div className="relative flex items-center justify-center py-2">
                <svg
                    viewBox="0 0 160 160"
                    className="size-36 -rotate-90"
                    aria-hidden="true"
                >
                    <circle
                        cx="80"
                        cy="80"
                        r={R}
                        fill="transparent"
                        stroke="var(--surface-sunken)"
                        strokeWidth="18"
                    />
                    {arcs.map((arc, i) =>
                        arc.length > 0 ? (
                            <circle
                                key={shown[i]?.method}
                                cx="80"
                                cy="80"
                                r={R}
                                fill="transparent"
                                stroke={MIX_COLOURS[i]}
                                strokeWidth="18"
                                strokeDasharray={`${arc.length} ${CIRCUMFERENCE}`}
                                strokeDashoffset={arc.offset}
                            >
                                <title>{`${shown[i]?.method}: ${digits.money(shown[i]?.amount ?? 0)} (${digits.n(arc.percent)}%)`}</title>
                            </circle>
                        ) : null,
                    )}
                </svg>
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                    <span className="text-[10px] font-medium text-muted-foreground">
                        {t('dashboard.mix.tendered')}
                    </span>
                    <span
                        className={cn(
                            'num max-w-24 leading-tight font-bold [overflow-wrap:anywhere] text-foreground',
                            totalText.length > 12 ? 'text-[10px]' : 'text-xs',
                        )}
                    >
                        {totalText}
                    </span>
                </div>
            </div>
            <ul className="space-y-1.5 border-t pt-2 text-xs">
                {shown.length === 0 ? (
                    <li className="text-muted-foreground">
                        {t('dashboard.nothing')}
                    </li>
                ) : (
                    shown.map((m, i) => (
                        <li
                            key={m.method}
                            className="flex items-center justify-between gap-2"
                        >
                            <span className="flex min-w-0 items-center gap-1.5">
                                <span
                                    className="inline-block size-2.5 shrink-0 rounded-full"
                                    style={{ backgroundColor: MIX_COLOURS[i] }}
                                />
                                <span className="truncate font-medium text-foreground">
                                    {m.method} (
                                    {digits.n(arcs[i]?.percent ?? 0)}%)
                                </span>
                            </span>
                            <span className="num shrink-0 text-muted-foreground">
                                {digits.money(m.amount)}
                            </span>
                        </li>
                    ))
                )}
            </ul>
        </div>
    );
}
