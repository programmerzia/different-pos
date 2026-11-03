import { Languages } from 'lucide-react';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT, type Locale } from '@/i18n';
import { applyToDocument } from '@/lib/preferences';
import { cn } from '@/lib/utils';

const LANGUAGES: { locale: Locale; label: string }[] = [
    { locale: 'en', label: 'English' },
    { locale: 'bn', label: 'বাংলা' },
];

/**
 * EN/BN switch for the sign-in pages. Same mechanism as the shell's switch (applyToDocument), whose
 * `locale` cookie the server's SetLocale reads for guests, so the next page renders in it too. Alt+L.
 */
export function GuestLanguageSwitch({ className }: { className?: string }) {
    const { t, locale } = useT();
    const choose = (next: Locale) => applyToDocument({ locale: next });

    useHotkeys('global', [
        {
            id: 'auth.language',
            keys: 'alt+l',
            labelKey: 'hotkeys.language',
            allowInInput: true,
            handler: (event) => {
                event.preventDefault();
                choose(locale === 'bn' ? 'en' : 'bn');
            },
        },
    ]);

    return (
        <fieldset className={cn('flex items-center gap-1 text-xs', className)}>
            <legend className="sr-only">{`${t('lang.switch')} (Alt+L)`}</legend>
            <Languages
                className="mr-1 size-4 text-muted-foreground"
                aria-hidden="true"
            />
            {LANGUAGES.map((language) => (
                <button
                    key={language.locale}
                    type="button"
                    lang={language.locale}
                    aria-pressed={locale === language.locale}
                    onClick={() => choose(language.locale)}
                    className={cn(
                        'min-h-11 min-w-11 rounded-md px-3 transition-colors focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none',
                        locale === language.locale
                            ? 'bg-accent font-semibold text-accent-foreground'
                            : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                    )}
                >
                    {language.label}
                </button>
            ))}
        </fieldset>
    );
}
