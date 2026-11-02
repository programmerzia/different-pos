import { Bell, BellRing } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Kbd } from '@/components/kbd';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useUiStore } from '@/command/ui-store';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

const TABS = ['all', 'alerts', 'exports'] as const;

/** Placeholder panel (380 px, tabs All · Alerts · Exports); the feed arrives with the notifications API. */
export function NotificationsBell() {
    const { t } = useT();
    const [tab, setTab] = useState<(typeof TABS)[number]>('all');
    const open = useUiStore((s) => s.overlay === 'notifications');
    const toggle = useUiStore((s) => s.toggle);
    const close = useUiStore((s) => s.close);

    return (
        <Popover
            open={open}
            onOpenChange={(next) => (next ? toggle('notifications') : close())}
        >
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-11 md:size-9"
                    aria-label={t('notifications.title')}
                    title={t('notifications.title')}
                >
                    <Bell aria-hidden="true" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-95 max-w-[calc(100vw-1rem)] p-0">
                <div className="flex items-center justify-between border-b px-4 py-3">
                    <h2 className="text-sm font-semibold">
                        {t('notifications.title')}
                    </h2>
                    <Kbd keys="alt+n" />
                </div>
                <div
                    role="tablist"
                    aria-label={t('notifications.title')}
                    className="flex gap-1 border-b px-2 py-1"
                >
                    {TABS.map((id) => (
                        <button
                            key={id}
                            type="button"
                            role="tab"
                            aria-selected={tab === id}
                            onClick={() => setTab(id)}
                            className={cn(
                                'min-h-11 rounded-md px-3 text-sm md:min-h-8',
                                tab === id
                                    ? 'bg-primary-soft font-medium text-primary'
                                    : 'text-muted-foreground hover:bg-muted',
                            )}
                        >
                            {t(`notifications.tab_${id}`)}
                        </button>
                    ))}
                </div>
                <EmptyState
                    icon={BellRing}
                    title={t('notifications.empty')}
                    className="py-8"
                />
            </PopoverContent>
        </Popover>
    );
}
