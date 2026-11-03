import { useId, type ReactNode } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { sectionId } from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';

/**
 * Settings hub content blocks (mockup screen 21): a section with a 16 px bold title and 12 px subtitle
 * (`id` is the menu's #anchor; the element id is `section-<id>`), the sunken field card, and the "title + description + checkbox" row.
 */
export function HubSection({
    id,
    title,
    subtitle,
    actions,
    children,
    className,
}: {
    id?: string;
    title: string;
    subtitle?: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    const headingId = useId();

    return (
        <section
            id={id ? sectionId(id) : undefined}
            aria-labelledby={headingId}
            className={cn('max-w-3xl scroll-mt-4 space-y-4', className)}
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="min-w-0">
                    <h2
                        id={headingId}
                        className="text-base font-bold text-foreground"
                    >
                        {title}
                    </h2>
                    {subtitle ? (
                        <p className="text-xs text-muted-foreground">
                            {subtitle}
                        </p>
                    ) : null}
                </div>
                {actions ? (
                    <div className="flex items-center gap-2">{actions}</div>
                ) : null}
            </div>
            {children}
        </section>
    );
}

/** The sunken card that holds a section's fields (inputs inside use `bg-surface`). */
export function HubCard({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'space-y-3.5 rounded-xl border bg-surface-sunken p-4 text-xs [&_input]:bg-surface [&_select]:bg-surface [&_textarea]:bg-surface',
                className,
            )}
        >
            {children}
        </div>
    );
}

/** Mockup toggle row: bold title and a muted line on the left, the checkbox on the right. */
export function HubToggle({
    label,
    description,
    checked,
    onChange,
    disabled,
}: {
    label: string;
    description?: string;
    checked: boolean;
    onChange: (value: boolean) => void;
    disabled?: boolean;
}) {
    const id = useId();

    return (
        <div className="flex items-center justify-between gap-3 rounded-lg border bg-surface p-3 [@media(pointer:coarse)]:min-h-11">
            <label htmlFor={id} className="min-w-0 cursor-pointer">
                <span className="block font-semibold text-foreground">
                    {label}
                </span>
                {description ? (
                    <span className="block text-[11px] text-muted-foreground">
                        {description}
                    </span>
                ) : null}
            </label>
            <Checkbox
                id={id}
                checked={checked}
                disabled={disabled}
                onCheckedChange={(v) => onChange(v === true)}
                className="size-5 md:size-4"
            />
        </div>
    );
}
