import { Wifi, WifiOff } from 'lucide-react';
import { useSyncExternalStore } from 'react';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

const subscribe = (cb: () => void) => {
    window.addEventListener('online', cb);
    window.addEventListener('offline', cb);

    return () => {
        window.removeEventListener('online', cb);
        window.removeEventListener('offline', cb);
    };
};

/** Placeholder until the sync center lands (D-next): connectivity only. Offline is amber, never red. */
export function SyncPill({ className }: { className?: string }) {
    const { t } = useT();
    const online = useSyncExternalStore(
        subscribe,
        () => navigator.onLine,
        () => true,
    );

    return (
        <output
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
                online
                    ? 'bg-success-soft text-success'
                    : 'bg-warning-soft text-warning',
                className,
            )}
        >
            {online ? (
                <Wifi className="size-3.5" aria-hidden="true" />
            ) : (
                <WifiOff className="size-3.5" aria-hidden="true" />
            )}
            <span className="max-md:sr-only">
                {online ? t('sync.online') : t('sync.offline')}
            </span>
        </output>
    );
}
