import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { AppSidebar } from '@/components/shell/app-sidebar';
import { CommandPalette } from '@/components/shell/command-palette';
import { ImpersonationBanner } from '@/components/shell/impersonation-banner';
import { MobileTabs } from '@/components/shell/mobile-tabs';
import { PreferencesSync } from '@/components/shell/preferences-sync';
import { ShellHotkeys } from '@/components/shell/shell-hotkeys';
import { ShortcutsOverlay } from '@/components/shell/shortcuts-overlay';
import { StatusBar } from '@/components/shell/status-bar';
import { TopBar } from '@/components/shell/top-bar';
import { HotkeyHost } from '@/hotkeys/host';
import { useT } from '@/i18n';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { t } = useT();

    return (
        <AppShell variant="sidebar">
            <PreferencesSync />
            <HotkeyHost />
            <ShellHotkeys />
            <CommandPalette />
            <ShortcutsOverlay />
            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-primary focus:px-3 focus:py-2 focus:text-primary-foreground"
            >
                {t('shell.skip')}
            </a>
            <TopBar />
            <ImpersonationBanner />
            <div className="flex min-h-0 flex-1">
                <AppSidebar />
                <AppContent
                    variant="sidebar"
                    className="min-w-0 overflow-hidden bg-background"
                >
                    {breadcrumbs.length > 0 && (
                        <div className="flex h-10 shrink-0 items-center border-b px-4">
                            <Breadcrumbs breadcrumbs={breadcrumbs} />
                        </div>
                    )}
                    <div
                        id="main"
                        tabIndex={-1}
                        scroll-region=""
                        className="flex min-h-0 min-w-0 flex-1 flex-col overflow-x-clip overflow-y-auto outline-none"
                    >
                        {children}
                    </div>
                </AppContent>
            </div>
            <StatusBar />
            <MobileTabs />
        </AppShell>
    );
}
