import { ArrowDown, ArrowUp } from 'lucide-react';
import type { ReactNode } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { useDigits } from '@/hooks/use-digits';
import { cn } from '@/lib/utils';

export function KpiTile({
    label,
    value,
    deltaPercent,
    loading = false,
    className,
}: {
    label: string;
    value: ReactNode;
    /** Change vs the previous period, whole percent (▲ success / ▼ danger). */
    deltaPercent?: number;
    loading?: boolean;
    className?: string;
}) {
    const digits = useDigits();

    return (
        <div
            className={cn(
                'rounded-lg border bg-card text-card-foreground shadow-xs',
                className,
            )}
            style={{ padding: 'var(--card-pad)' }}
        >
            <p className="text-sm text-muted-foreground">{label}</p>
            {loading ? (
                <Skeleton className="mt-2 h-9 w-32" />
            ) : (
                <p className="num mt-1 text-3xl font-bold">{value}</p>
            )}
            {deltaPercent !== undefined && !loading ? (
                <p
                    className={cn(
                        'num mt-1 inline-flex items-center gap-1 text-sm font-medium',
                        deltaPercent >= 0 ? 'text-success' : 'text-destructive',
                    )}
                >
                    {deltaPercent >= 0 ? (
                        <ArrowUp className="size-3.5" aria-hidden="true" />
                    ) : (
                        <ArrowDown className="size-3.5" aria-hidden="true" />
                    )}
                    {digits.n(Math.abs(deltaPercent))}%
                </p>
            ) : null}
        </div>
    );
}
