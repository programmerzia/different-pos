import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/** A plain `<select>` in the shared input style: keyboard-native, 44 px on touch, themed by tokens. */
export function NativeSelect({ className, ...props }: ComponentProps<'select'>) {
    return (
        <select
            {...props}
            className={cn(
                'h-9 min-h-11 w-full rounded-md border border-input bg-background px-3 text-base shadow-xs outline-none md:min-h-9 md:text-sm',
                'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                'aria-invalid:border-destructive aria-invalid:ring-destructive/20 disabled:opacity-50',
                className,
            )}
        />
    );
}
