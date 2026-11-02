import type { LucideIcon } from 'lucide-react';
import { Inbox } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function EmptyState({
    icon: Icon = Inbox,
    title,
    description,
    action,
    className,
}: {
    icon?: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
    className?: string;
}) {
    return (
        <output
            className={cn(
                'flex flex-col items-center justify-center gap-2 px-4 py-12 text-center',
                className,
            )}
        >
            <span className="flex size-12 items-center justify-center rounded-full bg-primary-soft text-primary">
                <Icon className="size-6" aria-hidden="true" />
            </span>
            <p className="text-lg font-semibold">{title}</p>
            {description ? (
                <p className="max-w-md text-sm text-muted-foreground">
                    {description}
                </p>
            ) : null}
            {action ? <div className="mt-2">{action}</div> : null}
        </output>
    );
}
