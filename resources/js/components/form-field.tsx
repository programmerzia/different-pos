import { useId, type ReactElement, cloneElement } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/** Label + control + error (linked with aria). The control gets `id`, `aria-invalid` and `aria-describedby`. */
export function FormField({
    label,
    error,
    hint,
    className,
    children,
}: {
    label: string;
    error?: string | undefined;
    hint?: string | undefined;
    className?: string;
    children: ReactElement<{ id?: string; 'aria-invalid'?: boolean; 'aria-describedby'?: string }>;
}) {
    const id = useId();
    const describedBy = error ? `${id}-error` : hint ? `${id}-hint` : undefined;

    return (
        <div className={cn('grid gap-1.5', className)}>
            <Label htmlFor={id}>{label}</Label>
            {cloneElement(children, {
                id,
                ...(error ? { 'aria-invalid': true } : {}),
                ...(describedBy ? { 'aria-describedby': describedBy } : {}),
            })}
            {hint && !error ? (
                <p id={`${id}-hint`} className="text-xs text-muted-foreground">
                    {hint}
                </p>
            ) : null}
            {error ? (
                <p id={`${id}-error`} role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
