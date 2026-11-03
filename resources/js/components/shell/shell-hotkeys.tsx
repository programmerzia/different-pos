import { useMemo } from 'react';
import { NAV } from '@/command/nav';
import { navigateTo } from '@/command/navigate';
import { useUiStore } from '@/command/ui-store';
import { useCan } from '@/hooks/use-can';
import { useHotkeys, type HotkeySpec } from '@/hotkeys/use-hotkeys';
import { usePreferencesStore } from '@/lib/preferences';
import { POS_F1_EVENT, POS_PATH } from '@/pos/register/hotkeys';

/** Global shortcuts (02_AppFlow §1.3): palette, overlay, G-chords, F1, Alt+L/T/B/N, Ctrl+B (listed; the sidebar owns it). */
export function ShellHotkeys() {
    const can = useCan();

    const specs = useMemo<HotkeySpec[]>(() => {
        const ui = () => useUiStore.getState();
        const go = (href: string, external?: boolean) =>
            navigateTo(href, { external: external === true });
        const chords: HotkeySpec[] = NAV.filter(
            (g) => g.chord !== undefined,
        ).map((g) => ({
            id: `nav.${g.id}`,
            keys: `g ${g.chord}`,
            labelKey: g.labelKey,
            handler: () => can(g.permission) && go(g.href, g.external),
        }));

        return [
            {
                id: 'shell.palette',
                keys: 'mod+k',
                labelKey: 'hotkeys.palette',
                inDialog: true,
                handler: () => ui().toggle('palette'),
            },
            {
                id: 'shell.shortcuts',
                keys: '?',
                labelKey: 'hotkeys.shortcuts',
                handler: () => ui().toggle('shortcuts'),
            },
            {
                id: 'shell.pos',
                keys: 'f1',
                labelKey: 'hotkeys.pos',
                // On the register itself F1 starts a new sale / focuses the scan field instead.
                handler: () => {
                    if (!can('pos.sell')) {
                        return;
                    }
                    if (window.location.pathname === POS_PATH) {
                        window.dispatchEvent(new CustomEvent(POS_F1_EVENT));

                        return;
                    }
                    go(POS_PATH);
                },
            },
            {
                id: 'shell.sidebar',
                keys: 'mod+b',
                labelKey: 'hotkeys.sidebar',
                display: true,
                handler: () => undefined,
            },
            {
                id: 'shell.language',
                keys: 'alt+l',
                labelKey: 'hotkeys.language',
                // The language switch also works with a dialog open and in a field (02_AppFlow §1).
                inDialog: true,
                allowInInput: true,
                handler: () => {
                    const { locale, set } = usePreferencesStore.getState();
                    set({ locale: locale === 'bn' ? 'en' : 'bn' });
                },
            },
            {
                id: 'shell.theme',
                keys: 'alt+t',
                labelKey: 'hotkeys.theme',
                handler: () => ui().toggle('theme'),
            },
            {
                id: 'shell.branch',
                keys: 'alt+b',
                labelKey: 'hotkeys.branch',
                handler: () => can('branches.switch') && ui().toggle('branch'),
            },
            {
                id: 'shell.notifications',
                keys: 'alt+n',
                labelKey: 'hotkeys.notifications',
                handler: () => ui().toggle('notifications'),
            },
            {
                id: 'shell.back',
                keys: 'alt+arrowleft',
                labelKey: 'hotkeys.back',
                handler: () => window.history.back(),
            },
            {
                id: 'shell.context_menu',
                keys: 'shift+f10',
                labelKey: 'hotkeys.context_menu',
                display: true,
                handler: () => undefined,
            },
            ...chords,
        ];
    }, [can]);

    useHotkeys('global', specs);

    return null;
}
