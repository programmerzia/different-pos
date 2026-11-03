import { Link } from '@inertiajs/react';
import { CheckCircle2, X } from 'lucide-react';
import { useState } from 'react';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';
import type { DashboardSetup } from '@/types/reports';

const STEPS: { key: keyof DashboardSetup; href: string }[] = [
    { key: 'logo', href: '/app/settings/business' },
    { key: 'payment_methods', href: '/app/settings/payment-methods' },
    { key: 'products', href: '/app/products/create' },
    { key: 'customers', href: '/app/customers/create' },
    { key: 'first_sale', href: '/app/pos' },
];

const DISMISS_KEY = 'dashboard.setup.dismissed';

/** First-run "Store Setup Checklist" card, driven by real state; hidden once every step is done. */
export function SetupChecklist({ setup }: { setup: DashboardSetup }) {
    const { t } = useT();
    const digits = useDigits();
    const [dismissed, setDismissed] = useState(
        () =>
            typeof window !== 'undefined' &&
            window.sessionStorage.getItem(DISMISS_KEY) === '1',
    );
    const done = STEPS.filter((s) => setup[s.key]).length;

    if (dismissed || done === STEPS.length) {
        return null;
    }

    const percent = Math.round((done / STEPS.length) * 100);
    const dismiss = () => {
        window.sessionStorage.setItem(DISMISS_KEY, '1');
        setDismissed(true);
    };

    return (
        <section
            aria-labelledby="setup-title"
            className="relative shrink-0 overflow-hidden rounded-xl border border-primary bg-surface p-3.5 shadow-xs"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-2">
                    <div className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary">
                        <CheckCircle2 className="size-4" aria-hidden="true" />
                    </div>
                    <div>
                        <h2
                            id="setup-title"
                            className="flex flex-wrap items-center gap-2 text-xs font-bold text-foreground"
                        >
                            <span>{t('dashboard.setup.title')}</span>
                            <span className="num rounded bg-primary-soft px-1.5 font-mono text-[10px] font-bold text-primary">
                                {t('dashboard.setup.progress', {
                                    done: digits.n(done),
                                    total: digits.n(STEPS.length),
                                    percent: digits.n(percent),
                                })}
                            </span>
                        </h2>
                        <p className="mt-0.5 text-[11px] text-muted-foreground">
                            {t('dashboard.setup.subtitle')}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={dismiss}
                    aria-label={t('dashboard.setup.dismiss')}
                    title={t('dashboard.setup.dismiss')}
                    className="flex size-7 items-center justify-center rounded text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none [@media(pointer:coarse)]:size-11"
                >
                    <X className="size-3.5" aria-hidden="true" />
                </button>
            </div>
            <div
                className="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-surface-sunken"
                aria-hidden="true"
            >
                <div
                    className="h-full rounded-full bg-primary transition-all duration-300"
                    style={{ width: `${percent}%` }}
                />
            </div>
            <ul className="mt-3 grid grid-cols-1 gap-2.5 text-xs sm:grid-cols-2 lg:grid-cols-5">
                {STEPS.map((step) => {
                    const ok = setup[step.key];

                    return (
                        <li
                            key={step.key}
                            className={cn(
                                'flex items-center justify-between gap-2 rounded-lg border bg-surface-sunken p-2',
                                !ok && 'border-warning',
                            )}
                        >
                            <span className="flex min-w-0 items-center gap-2">
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'flex size-4 shrink-0 items-center justify-center rounded-full text-[10px] font-bold',
                                        ok
                                            ? 'bg-success-soft text-success'
                                            : 'bg-warning-soft text-warning',
                                    )}
                                >
                                    {ok ? '✓' : '!'}
                                </span>
                                <span className="truncate font-medium text-foreground">
                                    {t(`dashboard.setup.steps.${step.key}`)}
                                </span>
                                <span className="sr-only">
                                    {ok
                                        ? t('dashboard.setup.done')
                                        : t('dashboard.setup.todo')}
                                </span>
                            </span>
                            {ok ? (
                                <Link
                                    href={step.href}
                                    className="shrink-0 text-[10px] text-primary hover:underline"
                                >
                                    {t('dashboard.setup.view')}
                                </Link>
                            ) : (
                                <Link
                                    href={step.href}
                                    className="flex h-5 shrink-0 items-center rounded bg-primary px-2 text-[10px] font-bold text-primary-foreground"
                                >
                                    {t('dashboard.setup.start')}
                                </Link>
                            )}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
