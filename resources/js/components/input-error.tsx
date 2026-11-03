import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string | undefined }) {
    return message ? (
        <p {...props} className={cn('text-sm text-destructive', className)}>
            {message}
        </p>
    ) : null;
}
