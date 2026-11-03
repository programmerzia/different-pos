import { cva, type VariantProps } from 'class-variance-authority';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

/**
 * Mockup status chip (render_pos_sales_returns.py): soft background + strong text, 10 px bold.
 * `pill` (default) is the sales-status chip (`rounded-full px-2`); `tag` is the smaller
 * card/PIN status tag (`rounded px-1.5`, medium weight) from the customer list.
 */
const badge = cva(
    'inline-flex items-center gap-1 py-0.5 text-[10px] leading-4 whitespace-nowrap',
    {
        variants: {
            tone: {
                neutral: 'bg-surface-sunken text-muted-foreground',
                success: 'bg-success-soft text-success',
                warning: 'bg-warning-soft text-warning',
                danger: 'bg-danger-soft text-destructive',
                info: 'bg-info-soft text-info',
                accent: 'bg-highlight/10 text-highlight',
                primary: 'bg-primary-soft text-primary',
            },
            shape: {
                pill: 'rounded-full px-2 font-bold',
                tag: 'rounded-sm px-1.5 font-medium',
            },
        },
        defaultVariants: { tone: 'neutral', shape: 'pill' },
    },
);

export type Tone = NonNullable<VariantProps<typeof badge>['tone']>;

/**
 * The fixed status map (03_UI_Spec §5 Badge): Paid=success, Partial=warning, Unpaid/Due=danger,
 * Draft=info, Held=accent, Void=neutral struck-through; plus the record states used by the lists.
 */
export const STATUS_TONE: Record<string, Tone> = {
    paid: 'success',
    completed: 'success',
    received: 'success',
    in_stock: 'success',
    active: 'success',
    verified: 'success',
    set: 'success',
    sent: 'success',
    partial: 'warning',
    low_stock: 'warning',
    due_soon: 'warning',
    locked: 'warning',
    not_set: 'warning',
    pending: 'warning',
    unpaid: 'danger',
    due: 'danger',
    overdue: 'danger',
    out_of_stock: 'danger',
    blocked: 'danger',
    failed: 'danger',
    draft: 'info',
    returned: 'info',
    held: 'accent',
    void: 'neutral',
    voided: 'neutral',
    cancelled: 'neutral',
    inactive: 'neutral',
    lost: 'neutral',
    none: 'neutral',
};

export function StatusBadge({
    status,
    tone,
    label,
    shape,
    className,
}: {
    /** A key of STATUS_TONE; its text comes from `status.<key>`. */
    status: string;
    tone?: Tone;
    label?: string;
    shape?: 'pill' | 'tag';
    className?: string;
}) {
    const { t } = useT();
    const resolved = tone ?? STATUS_TONE[status] ?? 'neutral';
    const struck = status === 'void' || status === 'voided';

    // The text label always carries the meaning; colour is never the only signal.
    return (
        <span
            className={cn(
                badge({ tone: resolved, shape }),
                struck && 'line-through',
                className,
            )}
        >
            {label ?? t(`status.${status}`)}
        </span>
    );
}
