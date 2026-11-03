import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

/**
 * The business logo on a card-coloured chip (legible in dark mode, 03_UI_Spec §images), or the generic
 * app mark when there is none or the image fails to load.
 */
export function TenantLogo({
    url,
    name,
    className,
    imageClassName,
}: {
    url: string | null | undefined;
    name: string;
    className?: string;
    imageClassName?: string;
}) {
    const [failed, setFailed] = useState(false);

    if (!url || failed) {
        return (
            <div
                className={cn(
                    'flex aspect-square items-center justify-center rounded-md bg-primary text-primary-foreground',
                    className,
                )}
            >
                <AppLogoIcon
                    className="size-3/5 fill-current"
                    aria-hidden="true"
                />
            </div>
        );
    }

    return (
        <div
            className={cn(
                'flex items-center justify-center overflow-hidden rounded-md bg-card ring-1 ring-border',
                className,
            )}
        >
            <img
                src={url}
                alt={name}
                className={cn('size-full object-contain', imageClassName)}
                onError={() => setFailed(true)}
            />
        </div>
    );
}
