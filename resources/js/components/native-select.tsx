import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/** A plain `<select>` in the mockup input style (32 px, sunken well, 12 px): keyboard-native, 44 px on touch. */
export function NativeSelect({ className, ...props }: ComponentProps<'select'>) {
    return (
        <select
            {...props}
            className={cn(
                'h-8 w-full cursor-pointer rounded-lg border border-border bg-surface-sunken px-2 text-base font-medium text-foreground outline-none md:text-xs [@media(pointer:coarse)]:min-h-11',
                'focus-visible:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50',
                'aria-invalid:border-destructive aria-invalid:ring-destructive/20 disabled:opacity-50',
                className,
            )}
        />
    );
}
