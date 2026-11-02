import { cva, type VariantProps } from 'class-variance-authority';
import {
    AlertTriangle,
    CheckCircle2,
    CircleDot,
    Clock,
    Info,
    MinusCircle,
    PauseCircle,
    XCircle,
} from 'lucide-react';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

const badge = cva(
    'inline-flex items-center gap-1 rounded-sm px-2 py-0.5 text-xs font-medium whitespace-nowrap',
    {
        variants: {
            tone: {
                neutral: 'bg-muted text-muted-foreground',
                success: 'bg-success-soft text-success',
                warning: 'bg-warning-soft text-warning',
                danger: 'bg-danger-soft text-destructive',
                info: 'bg-info-soft text-info',
                accent: 'bg-highlight/15 text-highlight',
            },
        },
        defaultVariants: { tone: 'neutral' },
    },
);

export type Tone = NonNullable<VariantProps<typeof badge>['tone']>;

/** The fixed status map (03_UI_Spec §5 Badge): Paid=success, Partial=warning, Unpaid/Due=danger, Draft=info, Held=accent, Void=neutral struck-through. */
export const STATUS_TONE: Record<string, Tone> = {
    paid: 'success',
    completed: 'success',
    received: 'success',
    in_stock: 'success',
    partial: 'warning',
    low_stock: 'warning',
    due_soon: 'warning',
    unpaid: 'danger',
    due: 'danger',
    overdue: 'danger',
    out_of_stock: 'danger',
    draft: 'info',
    held: 'accent',
    void: 'neutral',
    voided: 'neutral',
    cancelled: 'neutral',
};

// Status colour is never the only signal: every tone carries an icon.
const ICON = {
    neutral: MinusCircle,
    success: CheckCircle2,
    warning: AlertTriangle,
    danger: XCircle,
    info: Info,
    accent: PauseCircle,
} as const;

export function StatusBadge({
    status,
    tone,
    label,
    className,
}: {
    /** A key of STATUS_TONE; its text comes from `status.<key>`. */
    status: string;
    tone?: Tone;
    label?: string;
    className?: string;
}) {
    const { t } = useT();
    const resolved = tone ?? STATUS_TONE[status] ?? 'neutral';
    const Icon = ICON[resolved] ?? CircleDot ?? Clock;
    const struck = status === 'void' || status === 'voided';

    return (
        <span
            className={cn(
                badge({ tone: resolved }),
                struck && 'line-through',
                className,
            )}
        >
            <Icon className="size-3.5" aria-hidden="true" />
            {label ?? t(`status.${status}`)}
        </span>
    );
}
