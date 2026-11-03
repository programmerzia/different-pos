import { Link } from '@inertiajs/react';
import {
    Calendar,
    Download,
    FileText,
    MoreVertical,
    RefreshCw,
} from 'lucide-react';
import {
    createContext,
    type KeyboardEvent,
    type ReactNode,
    useContext,
} from 'react';
import {
    isContextMenuKey,
    openContextMenuAt,
} from '@/components/row-context-menu';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuSub,
    ContextMenuSubContent,
    ContextMenuSubTrigger,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import { navigateTo } from '@/command/navigate';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';
import type { DashboardPeriodKey } from '@/types/reports';

export const PERIOD_KEYS: DashboardPeriodKey[] = [
    'today',
    'yesterday',
    '7d',
    '30d',
    'this_month',
    'custom',
];

type DashboardControls = {
    period: DashboardPeriodKey;
    setPeriod: (period: DashboardPeriodKey) => void;
    refresh: () => void;
};

export const DashboardContext = createContext<DashboardControls>({
    period: 'today',
    setPeriod: () => undefined,
    refresh: () => undefined,
});

/**
 * A dashboard card (mockup: `p-4 rounded-xl` surface, 12 px bold title, 11 px muted subtitle, a
 * mono meta on the right). Right-click, Shift+F10 or the Menu key opens the widget menu
 * (02_AppFlow §1.2): Refresh · Period ▸ · Open full report · Export.
 */
export function Widget({
    title,
    subtitle,
    subtitleTone = 'muted',
    meta,
    reportHref = '/app/reports',
    onExport,
    footer,
    className,
    bodyClassName,
    children,
}: {
    title: string;
    subtitle?: ReactNode;
    subtitleTone?: 'muted' | 'warning' | 'danger';
    meta?: ReactNode;
    reportHref?: string;
    onExport?: () => void;
    footer?: ReactNode;
    className?: string;
    bodyClassName?: string;
    children: ReactNode;
}) {
    const { t } = useT();
    const { period, setPeriod, refresh } = useContext(DashboardContext);

    const onKeyDown = (event: KeyboardEvent<HTMLElement>) => {
        if (isContextMenuKey(event)) {
            event.preventDefault();
            event.stopPropagation();
            openContextMenuAt(event.currentTarget);
        }
    };

    return (
        <ContextMenu>
            <ContextMenuTrigger asChild onKeyDown={onKeyDown}>
                <section
                    aria-label={title}
                    className={cn(
                        'group flex min-w-0 flex-col justify-between rounded-xl border bg-surface p-4 text-card-foreground shadow-xs',
                        className,
                    )}
                >
                    <div className="mb-2 flex items-start justify-between gap-2">
                        <div className="min-w-0">
                            <h2 className="text-xs font-bold text-foreground">
                                {title}
                            </h2>
                            {subtitle ? (
                                <p
                                    className={cn(
                                        'text-[11px]',
                                        subtitleTone === 'warning'
                                            ? 'font-semibold text-warning'
                                            : subtitleTone === 'danger'
                                              ? 'font-medium text-destructive'
                                              : 'text-muted-foreground',
                                    )}
                                >
                                    {subtitle}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex shrink-0 items-center gap-1">
                            {meta ? (
                                <div className="font-mono text-[10px] text-muted-foreground">
                                    {meta}
                                </div>
                            ) : null}
                            <button
                                type="button"
                                aria-label={t('dashboard.widget.menu')}
                                title={t('dashboard.widget.menu')}
                                onClick={(event) => {
                                    const card =
                                        event.currentTarget.closest('section');
                                    if (card) {
                                        openContextMenuAt(card);
                                    }
                                }}
                                className="flex size-5 items-center justify-center rounded text-[11px] text-muted-foreground opacity-0 group-hover:opacity-100 hover:text-foreground focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none [@media(pointer:coarse)]:size-11 [@media(pointer:coarse)]:opacity-100"
                            >
                                <MoreVertical
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                            </button>
                        </div>
                    </div>
                    <div className={cn('min-w-0 flex-1', bodyClassName)}>
                        {children}
                    </div>
                    {footer ? (
                        <div className="mt-2 flex items-center justify-between gap-2 border-t pt-2 text-[10px] text-muted-foreground">
                            {footer}
                        </div>
                    ) : null}
                </section>
            </ContextMenuTrigger>
            <ContextMenuContent aria-label={t('dashboard.widget.menu')}>
                <ContextMenuItem onSelect={refresh}>
                    <RefreshCw aria-hidden="true" />
                    <span>{t('dashboard.widget.refresh')}</span>
                </ContextMenuItem>
                <ContextMenuSub>
                    <ContextMenuSubTrigger>
                        <Calendar aria-hidden="true" />
                        <span>{t('dashboard.widget.period')}</span>
                    </ContextMenuSubTrigger>
                    <ContextMenuSubContent>
                        {PERIOD_KEYS.filter((k) => k !== 'custom').map(
                            (key) => (
                                <ContextMenuItem
                                    key={key}
                                    onSelect={() => setPeriod(key)}
                                    aria-current={
                                        key === period ? 'true' : undefined
                                    }
                                    className={cn(
                                        key === period && 'font-semibold',
                                    )}
                                >
                                    {t(`dashboard.period.${key}`)}
                                </ContextMenuItem>
                            ),
                        )}
                    </ContextMenuSubContent>
                </ContextMenuSub>
                <ContextMenuItem onSelect={() => navigateTo(reportHref)}>
                    <FileText aria-hidden="true" />
                    <span>{t('dashboard.widget.open_report')}</span>
                </ContextMenuItem>
                {onExport ? (
                    <>
                        <ContextMenuSeparator />
                        <ContextMenuItem onSelect={onExport}>
                            <Download aria-hidden="true" />
                            <span>{t('dashboard.widget.export')}</span>
                        </ContextMenuItem>
                    </>
                ) : null}
            </ContextMenuContent>
        </ContextMenu>
    );
}

/** Mockup footer link ("All Products Catalog →"). */
export function WidgetLink({
    href,
    children,
}: {
    href: string;
    children: ReactNode;
}) {
    return (
        <Link
            href={href}
            className="ml-auto text-[11px] font-semibold text-primary hover:underline focus-visible:underline focus-visible:outline-none"
        >
            {children} →
        </Link>
    );
}

/** Rank bubble of the top-5 lists. */
export function Rank({ n }: { n: string }) {
    return (
        <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-surface-sunken font-mono text-[10px] font-bold text-muted-foreground">
            {n}
        </span>
    );
}
