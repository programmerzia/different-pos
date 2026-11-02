import { Check, Monitor, Moon, Palette, Sun } from 'lucide-react';
import { Kbd } from '@/components/kbd';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useUiStore } from '@/command/ui-store';
import { useT } from '@/i18n';
import {
    SCHEMES,
    previewScheme,
    usePreferences,
    type Density,
    type Scheme,
    type Theme,
} from '@/lib/preferences';
import { cn } from '@/lib/utils';

/** Mode, colour scheme and density (03_UI_Spec §5 Theme popover). Hovering a swatch previews it live. */
export function ThemePopover() {
    const { t } = useT();
    const prefs = usePreferences();
    const open = useUiStore((s) => s.overlay === 'theme');
    const toggle = useUiStore((s) => s.toggle);
    const close = useUiStore((s) => s.close);

    const restore = () => previewScheme(prefs.scheme);

    const modes: { value: Theme; icon: typeof Sun; key: string }[] = [
        { value: 'light', icon: Sun, key: 'theme.light' },
        { value: 'dark', icon: Moon, key: 'theme.dark' },
        { value: 'system', icon: Monitor, key: 'theme.system' },
    ];

    return (
        <Popover
            open={open}
            onOpenChange={(next) => (next ? toggle('theme') : close())}
        >
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-11 md:size-9"
                    aria-label={t('theme.title')}
                    title={t('theme.title')}
                >
                    <Palette aria-hidden="true" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-80" onCloseAutoFocus={restore}>
                <div className="flex items-center justify-between">
                    <h2 className="text-sm font-semibold">
                        {t('theme.title')}
                    </h2>
                    <Kbd keys="alt+t" />
                </div>
                <p className="mt-3 text-xs text-muted-foreground">
                    {t('theme.mode')}
                </p>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    className="mt-1 w-full"
                    value={prefs.theme}
                    onValueChange={(v) => v && prefs.set({ theme: v as Theme })}
                    aria-label={t('theme.mode')}
                >
                    {modes.map(({ value, icon: Icon, key }) => (
                        <ToggleGroupItem
                            key={value}
                            value={value}
                            className="min-h-11 flex-1 gap-1 md:min-h-9"
                        >
                            <Icon className="size-4" aria-hidden="true" />
                            {t(key)}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
                <p className="mt-3 text-xs text-muted-foreground">
                    {t('theme.scheme')}
                </p>
                <ToggleGroup
                    type="single"
                    className="mt-1 grid w-full grid-cols-6 gap-2"
                    value={prefs.scheme}
                    onValueChange={(v) =>
                        v && prefs.set({ scheme: v as Scheme })
                    }
                    onMouseLeave={restore}
                    aria-label={t('theme.scheme')}
                >
                    {SCHEMES.map((scheme) => (
                        <ToggleGroupItem
                            key={scheme}
                            value={scheme}
                            aria-label={t(`scheme.${scheme}`)}
                            title={t(`scheme.${scheme}`)}
                            data-scheme={scheme}
                            onMouseEnter={() => previewScheme(scheme)}
                            onFocus={() => previewScheme(scheme)}
                            onBlur={restore}
                            className={cn(
                                'size-11 min-w-0 rounded-full bg-primary p-0 text-primary-foreground hover:bg-primary-hover data-[state=on]:bg-primary data-[state=on]:text-primary-foreground md:size-7',
                                prefs.scheme === scheme &&
                                    'ring-2 ring-ring ring-offset-2 ring-offset-popover',
                            )}
                        >
                            {prefs.scheme === scheme ? (
                                <Check className="size-4" aria-hidden="true" />
                            ) : null}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
                <p className="mt-3 text-xs text-muted-foreground">
                    {t('theme.density')}
                </p>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    className="mt-1 w-full"
                    value={prefs.density}
                    onValueChange={(v) =>
                        v && prefs.set({ density: v as Density })
                    }
                    aria-label={t('theme.density')}
                >
                    <ToggleGroupItem
                        value="comfortable"
                        className="min-h-11 flex-1 md:min-h-9"
                    >
                        {t('theme.comfortable')}
                    </ToggleGroupItem>
                    <ToggleGroupItem
                        value="compact"
                        className="min-h-11 flex-1 md:min-h-9"
                    >
                        {t('theme.compact')}
                    </ToggleGroupItem>
                </ToggleGroup>
            </PopoverContent>
        </Popover>
    );
}
