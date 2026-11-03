import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

export function AppShell({ children, variant = 'sidebar' }: Props) {
    const isOpen = usePage().props.sidebarOpen;

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">{children}</div>
        );
    }

    // Column: top bar, then (sidebar | content), then the status bar. The shell is exactly one viewport
    // tall (as the mockup): only the content area scrolls, so the status bar is always visible.
    return (
        <SidebarProvider
            defaultOpen={isOpen}
            className="h-svh min-h-0 flex-col overflow-hidden"
        >
            {children}
        </SidebarProvider>
    );
}
