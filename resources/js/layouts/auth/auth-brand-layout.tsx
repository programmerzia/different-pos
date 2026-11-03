import { usePage } from '@inertiajs/react';
import { GuestLanguageSwitch } from '@/components/auth/guest-language-switch';
import { TenantLogo } from '@/components/tenant-logo';
import { HotkeyHost } from '@/hotkeys/host';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';
import type { AuthLayoutProps } from '@/types';

type Brand = { name: string; logo_url: string | null; tagline?: string | null };

/** The business shown on the sign-in pages: the remembered one at login, else the signed-in tenant, else none. */
function useBrand(): Brand | null {
    const props = usePage().props as {
        business?: Brand | null;
        tenant?: Brand | null;
    };

    return props.business ?? props.tenant ?? null;
}

/**
 * Every auth page (login, forgot/reset/confirm password, two-factor, forced password change), ported from the
 * mockup login screen (tools/mockup/baseline/parts/render_shell.py, mockup commit 2078d6c): one centred card with the brand header
 * (business logo or mark, name, tagline) above the page's content; language switch and staff line below it.
 */
export default function AuthBrandLayout({
    children,
    title,
    description,
    titleSrOnly = false,
}: AuthLayoutProps & { titleSrOnly?: boolean }) {
    const { t } = useT();
    const { name: appName } = usePage().props;
    const brand = useBrand();
    const name = brand?.name ?? appName;
    const tagline = brand?.tagline || t('auth.tagline');

    return (
        <div className="relative flex min-h-svh w-full flex-col items-center justify-center gap-4 overflow-y-auto bg-background p-4">
            <HotkeyHost />

            <main className="w-full max-w-md animate-in space-y-5 rounded-2xl border bg-card p-6 text-card-foreground shadow-2xl duration-200 fade-in">
                {/* Brand header */}
                <div className="space-y-1.5 text-center">
                    <TenantLogo
                        url={brand?.logo_url}
                        name={name}
                        className="mx-auto size-12 rounded-xl shadow-md"
                        imageClassName="p-0.5"
                    />
                    <p className="text-xl font-bold tracking-tight text-foreground">
                        {name}
                    </p>
                    <p className="text-xs text-muted-foreground">{tagline}</p>
                </div>

                <div
                    className={cn(
                        'space-y-0.5 text-center',
                        titleSrOnly && 'sr-only',
                    )}
                >
                    <h1 className="text-sm font-bold text-foreground">
                        {title}
                    </h1>
                    {description ? (
                        <p className="text-[11px] text-muted-foreground">
                            {description}
                        </p>
                    ) : null}
                </div>

                <div className="space-y-4">{children}</div>
            </main>

            <footer className="flex w-full max-w-md flex-wrap items-center justify-between gap-2 px-1">
                <GuestLanguageSwitch />
                <p className="text-[11px] text-muted-foreground">
                    {t('auth.staff_only')}
                </p>
            </footer>
        </div>
    );
}
