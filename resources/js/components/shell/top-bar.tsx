import { Link, usePage } from '@inertiajs/react';
import { Languages, Search } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { Kbd } from '@/components/kbd';
import { BranchSwitcher } from '@/components/shell/branch-switcher';
import { LiveClock } from '@/components/shell/live-clock';
import { NotificationsBell } from '@/components/shell/notifications-bell';
import { SyncPill } from '@/components/shell/sync-pill';
import { ThemePopover } from '@/components/shell/theme-popover';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { useUiStore } from '@/command/ui-store';
import { useT } from '@/i18n';
import { usePreferences } from '@/lib/preferences';
import { dashboard } from '@/routes';

/** 52 px (44 compact). On phones it shrinks to logo · sync · bell · avatar (02_AppFlow §1). */
export function TopBar() {
    const { t, locale } = useT();
    const prefs = usePreferences();
    const user = usePage().props.auth.user;
    const openOverlay = useUiStore((s) => s.open);

    return (
        <header
            className="sticky top-0 z-40 flex shrink-0 items-center gap-2 border-b bg-card px-2 md:px-4"
            style={{ height: 'var(--topbar-h)' }}
        >
            <SidebarTrigger
                className="-ml-1 max-md:hidden"
                aria-label={t('nav.toggle_sidebar')}
            />
            <Link
                href={dashboard()}
                prefetch
                className="flex min-w-0 items-center gap-1 rounded-md"
                aria-label={t('nav.home')}
            >
                <AppLogo />
            </Link>
            <div className="max-md:hidden">
                <BranchSwitcher />
            </div>

            <button
                type="button"
                onClick={() => openOverlay('palette')}
                className="mx-auto hidden min-h-9 w-full max-w-md items-center gap-2 rounded-md border border-input bg-muted px-3 text-sm text-muted-foreground hover:bg-accent md:flex"
                aria-label={t('palette.open')}
            >
                <Search className="size-4" aria-hidden="true" />
                <span className="flex-1 text-left">
                    {t('palette.placeholder')}
                </span>
                <Kbd keys="mod+k" />
            </button>

            <div className="ml-auto flex items-center gap-1 md:ml-0">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-11 md:hidden"
                    aria-label={t('palette.open')}
                    onClick={() => openOverlay('palette')}
                >
                    <Search aria-hidden="true" />
                </Button>
                <SyncPill />
                <NotificationsBell />
                <LiveClock />
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="min-h-9 gap-1 max-md:hidden"
                    aria-label={t('lang.switch')}
                    title={`${t('lang.switch')} (Alt+L)`}
                    onClick={() =>
                        prefs.set({ locale: locale === 'bn' ? 'en' : 'bn' })
                    }
                >
                    <Languages aria-hidden="true" />
                    {locale === 'bn' ? 'EN' : 'বাং'}
                </Button>
                <div className="max-md:hidden">
                    <ThemePopover />
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            className="size-11 rounded-full p-0 md:size-9"
                            aria-label={t('user.menu')}
                            data-test="sidebar-menu-button"
                        >
                            <UserInfoAvatar />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        className="min-w-56 rounded-lg"
                    >
                        {user ? <UserMenuContent user={user} /> : null}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}

function UserInfoAvatar() {
    const user = usePage().props.auth.user;

    return user ? (
        <span className="[&>div]:hidden">
            <UserInfo user={user} />
        </span>
    ) : null;
}
