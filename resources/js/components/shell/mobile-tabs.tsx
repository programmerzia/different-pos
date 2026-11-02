import { Link } from '@inertiajs/react';
import {
    Boxes,
    Ellipsis,
    LayoutGrid,
    Receipt,
    ShoppingCart,
} from 'lucide-react';
import { useSidebar } from '@/components/ui/sidebar';
import { useCan } from '@/hooks/use-can';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

const TABS = [
    {
        id: 'home',
        labelKey: 'tabs.home',
        icon: LayoutGrid,
        href: '/app',
        permission: 'dashboard.view',
    },
    {
        id: 'pos',
        labelKey: 'tabs.pos',
        icon: ShoppingCart,
        href: '/app/pos',
        permission: 'pos.sell',
    },
    {
        id: 'sales',
        labelKey: 'tabs.sales',
        icon: Receipt,
        href: '/app/sales',
        permission: 'sales.view',
    },
    {
        id: 'stock',
        labelKey: 'tabs.stock',
        icon: Boxes,
        href: '/app/stock',
        permission: 'stock.view',
    },
] as const;

/** < 768 px: the sidebar becomes a bottom tab bar [Home · POS · Sales · Stock · More]. */
export function MobileTabs() {
    const { t } = useT();
    const can = useCan();
    const { isCurrentUrl } = useCurrentUrl();
    const { setOpenMobile } = useSidebar();
    const cell =
        'flex min-h-14 min-w-11 flex-1 flex-col items-center justify-center gap-0.5 text-xs';

    return (
        <nav
            aria-label={t('tabs.label')}
            className="sticky bottom-0 z-40 flex border-t bg-card md:hidden"
        >
            {TABS.filter((tab) => can(tab.permission)).map((tab) => {
                const active = isCurrentUrl(tab.href);
                const className = cn(
                    cell,
                    active
                        ? 'font-medium text-primary'
                        : 'text-muted-foreground',
                );
                const inner = (
                    <>
                        <tab.icon className="size-5" aria-hidden="true" />
                        {t(tab.labelKey)}
                    </>
                );

                return 'external' in tab ? (
                    <a key={tab.id} href={tab.href} className={className}>
                        {inner}
                    </a>
                ) : (
                    <Link
                        key={tab.id}
                        href={tab.href}
                        prefetch
                        className={className}
                        aria-current={active ? 'page' : undefined}
                    >
                        {inner}
                    </Link>
                );
            })}
            <button
                type="button"
                className={cn(cell, 'text-muted-foreground')}
                onClick={() => setOpenMobile(true)}
            >
                <Ellipsis className="size-5" aria-hidden="true" />
                {t('tabs.more')}
            </button>
        </nav>
    );
}
