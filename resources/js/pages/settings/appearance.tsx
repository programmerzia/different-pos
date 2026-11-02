import { Head } from '@inertiajs/react';
import { Check } from 'lucide-react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { CheckField } from '@/components/admin/check-field';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useT } from '@/i18n';
import {
    SCHEMES,
    usePreferences,
    type Density,
    type Locale,
} from '@/lib/preferences';
import { cn } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';

/** Personal display preferences: mode, language, digits, colour scheme and density. Saved to the account as you change them. */
export default function Appearance() {
    const { t } = useT();
    const prefs = usePreferences();

    return (
        <>
            <Head title={t('appearance.title')} />

            <h1 className="sr-only">{t('appearance.title')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('appearance.title')}
                    description={t('appearance.description')}
                />
                <AppearanceTabs />

                <div className="grid gap-2">
                    <p className="text-sm font-medium">
                        {t('appearance.language')}
                    </p>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        className="w-fit"
                        value={prefs.locale}
                        onValueChange={(v) =>
                            v && prefs.set({ locale: v as Locale })
                        }
                        aria-label={t('appearance.language')}
                    >
                        <ToggleGroupItem
                            value="en"
                            className="min-h-11 px-4 md:min-h-9"
                        >
                            English
                        </ToggleGroupItem>
                        <ToggleGroupItem
                            value="bn"
                            className="min-h-11 px-4 md:min-h-9"
                        >
                            বাংলা
                        </ToggleGroupItem>
                    </ToggleGroup>
                    <CheckField
                        label={t('appearance.bn_digits')}
                        checked={prefs.bn_digits}
                        onChange={(v) => prefs.set({ bn_digits: v })}
                    />
                </div>

                <div className="grid gap-2">
                    <p className="text-sm font-medium">{t('theme.scheme')}</p>
                    <div
                        role="group"
                        aria-label={t('theme.scheme')}
                        className="flex flex-wrap gap-2"
                    >
                        {SCHEMES.map((scheme) => (
                            <button
                                key={scheme}
                                type="button"
                                aria-pressed={prefs.scheme === scheme}
                                onClick={() => prefs.set({ scheme })}
                                className={cn(
                                    'inline-flex min-h-11 items-center gap-1 rounded-md border px-3 text-sm font-medium outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 md:min-h-9',
                                    prefs.scheme === scheme
                                        ? 'border-primary bg-primary-soft text-primary'
                                        : 'border-input hover:bg-muted',
                                )}
                            >
                                {prefs.scheme === scheme ? (
                                    <Check
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                ) : null}
                                {t(`scheme.${scheme}`)}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="grid gap-2">
                    <p className="text-sm font-medium">{t('theme.density')}</p>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        className="w-fit"
                        value={prefs.density}
                        onValueChange={(v) =>
                            v && prefs.set({ density: v as Density })
                        }
                        aria-label={t('theme.density')}
                    >
                        <ToggleGroupItem
                            value="comfortable"
                            className="min-h-11 px-4 md:min-h-9"
                        >
                            {t('theme.comfortable')}
                        </ToggleGroupItem>
                        <ToggleGroupItem
                            value="compact"
                            className="min-h-11 px-4 md:min-h-9"
                        >
                            {t('theme.compact')}
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Appearance settings',
            href: editAppearance(),
        },
    ],
};
