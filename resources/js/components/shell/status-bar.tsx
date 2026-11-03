import { usePage } from '@inertiajs/react';
import { Fragment, type ReactNode } from 'react';
import { Kbd } from '@/components/kbd';
import { useOnline } from '@/components/shell/sync-pill';
import { useUiStore } from '@/command/ui-store';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

/**
 * Desktop status bar (mockup render_admin_and_states.py "DESKTOP STATUS BAR"): 28 px, sunken, 11 px,
 * `│`-separated. Real data only: branch, user (role), connectivity, the shortcuts hint and the version.
 * Printer / scanner / local DB segments arrive with the POS devices; the demo sales ticker is omitted.
 */
export function StatusBar() {
    const { t } = useT();
    const { auth } = usePage().props;
    const online = useOnline();
    const openOverlay = useUiStore((s) => s.open);
    const branch = auth.branches.find((b) => b.id === auth.branch_id);
    const role = auth.roles?.[0];

    const left: ReactNode[] = [
        <span key="branch">
            {t('status_bar.branch')}{' '}
            <strong className="text-foreground">
                {branch?.name ?? t('status_bar.no_branch')}
            </strong>
        </span>,
    ];
    if (auth.user) {
        left.push(
            <span key="user">
                {t('status_bar.user')}{' '}
                <strong className="text-foreground">
                    {auth.user.name}
                    {role ? ` (${role})` : ''}
                </strong>
            </span>,
        );
    }
    left.push(
        <span
            key="sync"
            className={cn(
                'font-medium',
                online ? 'text-success' : 'font-semibold text-warning',
            )}
        >
            {online ? t('status_bar.sync_ok') : t('status_bar.sync_offline')}
        </span>,
    );

    return (
        <footer
            className="z-20 hidden shrink-0 items-center justify-between gap-3 border-t bg-surface-sunken px-3 text-[11px] text-muted-foreground select-none md:flex"
            style={{ height: 'var(--statusbar-h)' }}
        >
            <div className="flex min-w-0 items-center gap-2 truncate">
                {left.map((segment, i) => (
                    <Fragment key={i}>
                        {i > 0 ? <span aria-hidden="true">│</span> : null}
                        {segment}
                    </Fragment>
                ))}
            </div>
            <div className="flex shrink-0 items-center gap-3">
                <button
                    type="button"
                    onClick={() => openOverlay('shortcuts')}
                    className="inline-flex items-center gap-1 rounded-sm hover:text-foreground"
                >
                    <Kbd keys="?" className="px-1 py-0 text-[10px]" />
                    {t('status_bar.shortcuts')}
                </button>
                <span aria-hidden="true">│</span>
                <span className="font-mono">{t('status_bar.version')}</span>
            </div>
        </footer>
    );
}
