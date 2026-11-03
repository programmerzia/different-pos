import { ArrowDown, ArrowUp, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { useDigits } from '@/hooks/use-digits';
import { cn } from '@/lib/utils';

export type KpiTone = 'default' | 'success' | 'warning' | 'danger' | 'info' | 'primary';

const TONE_BORDER: Record<KpiTone, string> = {
    default: '',
    success: 'border-l-4 border-success',
    warning: 'border-l-4 border-warning',
    danger: 'border-l-4 border-destructive',
    info: 'border-l-4 border-info',
    primary: 'border-l-4 border-primary',
};

/**
 * The mockup KPI tile. Two recipes:
 * - default (dashboard): 11 px label, 16 px bold value, a hairline footer with the delta and a footnote;
 * - `tone` (aging strip, render_pos_sales_returns.py): a 4 px coloured left border, 18 px value, 10 px note.
 * Long taka amounts never clip: the value shrinks in narrow tiles and wraps as a last resort.
 */
export function KpiTile({
    label,
    value,
    deltaPercent,
    delta,
    deltaTone,
    footnote,
    icon: Icon,
    tone = 'default',
    loading = false,
    onClick,
    className,
}: {
    label: string;
    value: ReactNode;
    /** Change vs the previous period, whole percent (▲ success / ▼ danger). */
    deltaPercent?: number;
    /** Free-form delta text (instead of `deltaPercent`). */
    delta?: ReactNode;
    /** Colour of a free-form `delta`. */
    deltaTone?: 'success' | 'danger' | 'muted';
    /** Small caption: right of the delta (default tile) or under the value (toned tile). */
    footnote?: ReactNode;
    icon?: LucideIcon;
    tone?: KpiTone;
    loading?: boolean;
    /** Makes the tile a button (drill-down). */
    onClick?: () => void;
    className?: string;
}) {
    const digits = useDigits();
    const toned = tone !== 'default';
    const hasDelta = deltaPercent !== undefined || delta !== undefined;

    const deltaNode =
        deltaPercent !== undefined ? (
            <span
                className={cn(
                    'num inline-flex items-center gap-0.5 font-semibold',
                    deltaPercent >= 0 ? 'text-success' : 'text-destructive',
                )}
            >
                {deltaPercent >= 0 ? (
                    <ArrowUp className="size-3" aria-hidden="true" />
                ) : (
                    <ArrowDown className="size-3" aria-hidden="true" />
                )}
                {digits.n(Math.abs(deltaPercent))}%
            </span>
        ) : delta !== undefined ? (
            <span
                className={cn(
                    'num font-semibold',
                    deltaTone === 'danger'
                        ? 'text-destructive'
                        : deltaTone === 'muted'
                          ? 'text-muted-foreground'
                          : 'text-success',
                )}
            >
                {delta}
            </span>
        ) : null;

    const Root = onClick ? 'button' : 'div';

    return (
        <Root
            type={onClick ? 'button' : undefined}
            onClick={onClick}
            className={cn(
                '@container flex min-w-0 flex-col justify-between rounded-xl border bg-surface text-left text-card-foreground shadow-xs transition-colors',
                toned ? 'p-3.5' : 'p-3 hover:border-primary',
                TONE_BORDER[tone],
                onClick && 'cursor-pointer',
                className,
            )}
        >
            <div className="min-w-0">
                <div className="flex items-center justify-between gap-2">
                    <span className="truncate text-[11px] font-semibold text-muted-foreground">
                        {label}
                    </span>
                    {Icon ? (
                        <Icon
                            className="size-3.5 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                    ) : null}
                </div>
                {loading ? (
                    <Skeleton className="mt-1.5 h-6 w-28" />
                ) : (
                    <div
                        className={cn(
                            'num mt-1 leading-tight font-bold text-foreground [overflow-wrap:anywhere]',
                            toned
                                ? 'text-lg @max-3xs:text-base'
                                : 'text-base @max-3xs:text-sm',
                        )}
                    >
                        {value}
                    </div>
                )}
                {toned && footnote ? (
                    <div className="mt-0.5 text-[10px] text-muted-foreground">
                        {footnote}
                    </div>
                ) : null}
            </div>
            {!toned && !loading && (hasDelta || footnote) ? (
                <div className="mt-2 flex items-center justify-between gap-2 border-t pt-1 text-[10px]">
                    {deltaNode ?? <span />}
                    {footnote ? (
                        <span className="truncate text-[9px] text-muted-foreground">
                            {footnote}
                        </span>
                    ) : null}
                </div>
            ) : null}
            {toned && hasDelta && !loading ? (
                <div className="mt-1 text-[10px]">{deltaNode}</div>
            ) : null}
        </Root>
    );
}
