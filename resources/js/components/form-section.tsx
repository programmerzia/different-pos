import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** A titled group of fields: label→input 6 px, field→field 16 px, two columns from lg. */
export function FormSection({
    title,
    description,
    children,
    className,
}: {
    title: string;
    description?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className={cn('max-w-180', className)}>
            <h2 className="text-xl font-semibold">{title}</h2>
            {description ? (
                <p className="mt-1 text-sm text-muted-foreground">
                    {description}
                </p>
            ) : null}
            <div className="mt-4 grid gap-4 lg:grid-cols-2">{children}</div>
        </section>
    );
}
