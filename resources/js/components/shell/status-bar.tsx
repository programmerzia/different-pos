import { usePage } from '@inertiajs/react';
import { useT } from '@/i18n';

/** Desktop only, 28 px. Device segments (printer, scanner, sync, DB size) arrive with the POS devices. */
export function StatusBar() {
    const { t } = useT();
    const { auth } = usePage().props;
    const branch = auth.branches.find((b) => b.id === auth.branch_id);

    return (
        <footer
            className="hidden shrink-0 items-center gap-3 border-t bg-muted px-4 text-xs text-muted-foreground md:flex"
            style={{ height: 'var(--statusbar-h)' }}
        >
            <span>{branch?.name ?? t('status_bar.no_branch')}</span>
            <span aria-hidden="true">│</span>
            <span>{auth.user?.name}</span>
            <span className="ml-auto">{t('status_bar.version')}</span>
        </footer>
    );
}
