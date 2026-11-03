import { router, usePage } from '@inertiajs/react';
import { LogOut, UserCog } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { localStamp } from '@/components/sales/date-time';

/** "Impersonating <Owner> at <Business> — Exit": shown while a platform admin is signed in as a tenant Owner (05_Schema §1). */
export function ImpersonationBanner() {
    const { t } = useT();
    const digits = useDigits();
    const impersonation = usePage().props.impersonation ?? null;
    const [busy, setBusy] = useState(false);

    const exit = () => {
        if (busy || !impersonation) {
            return;
        }
        setBusy(true);
        router.post(
            '/app/impersonation/exit',
            {},
            { onFinish: () => setBusy(false) },
        );
    };

    useHotkeys(
        'global',
        impersonation
            ? [
                  {
                      id: 'shell.exit_impersonation',
                      keys: 'alt+shift+x',
                      labelKey: 'platform.exit_impersonation',
                      allowInInput: true,
                      inDialog: true,
                      handler: exit,
                  },
              ]
            : [],
    );

    if (!impersonation) {
        return null;
    }
    const until = localStamp(impersonation.expires_at).time;

    return (
        <output className="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-warning/30 bg-warning-soft px-3 py-1.5 text-xs text-foreground">
            <div className="flex min-w-0 items-center gap-2">
                <UserCog
                    className="size-4 shrink-0 text-warning"
                    aria-hidden="true"
                />
                <span className="truncate">
                    <span className="font-bold text-warning">
                        {t('platform.impersonating', {
                            user: impersonation.user,
                            tenant: impersonation.tenant ?? '',
                        })}
                    </span>
                    {until ? (
                        <span className="ml-2 text-muted-foreground">
                            {t('platform.impersonation_until', {
                                time: digits.n(until),
                            })}
                        </span>
                    ) : null}
                </span>
            </div>
            <Button
                type="button"
                size="sm"
                variant="outline"
                className="h-7 bg-surface text-[11px] font-semibold [@media(pointer:coarse)]:min-h-11"
                disabled={busy}
                onClick={exit}
            >
                <LogOut aria-hidden="true" />
                {t('platform.exit_impersonation')}
            </Button>
        </output>
    );
}
