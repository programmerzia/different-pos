import { useSyncExternalStore } from 'react';
import { Kbd } from '@/components/kbd';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { hotkeys } from '@/hotkeys/registry';
import { useUiStore } from '@/command/ui-store';
import { useT } from '@/i18n';

/** `?`: every shortcut that works on this page, straight from the registry. */
export function ShortcutsOverlay() {
    const { t } = useT();
    const open = useUiStore((s) => s.overlay === 'shortcuts');
    const close = useUiStore((s) => s.close);
    useSyncExternalStore(
        hotkeys.subscribe,
        hotkeys.getVersion,
        hotkeys.getVersion,
    );

    // No dialog scope here: the page scope underneath must stay readable for the list.
    const entries = open ? hotkeys.listVisible() : [];
    const groups = [
        {
            id: 'global',
            title: t('shortcuts.global'),
            items: entries.filter((e) => e.scope === 'global'),
        },
        {
            id: 'page',
            title: t('shortcuts.page'),
            items: entries.filter(
                (e) => e.scope !== 'global' && e.scope !== 'dialog',
            ),
        },
    ].filter((g) => g.items.length > 0);

    return (
        <Dialog open={open} onOpenChange={(next) => !next && close()}>
            <DialogContent className="max-h-[80vh] max-w-xl overflow-y-auto">
                <DialogTitle>{t('shortcuts.title')}</DialogTitle>
                <DialogDescription>{t('shortcuts.hint')}</DialogDescription>
                {groups.map((group) => (
                    <section key={group.id} aria-label={group.title}>
                        <h3 className="mb-1 text-sm font-semibold">
                            {group.title}
                        </h3>
                        <ul className="divide-y">
                            {group.items.map((entry) => (
                                <li
                                    key={entry.id}
                                    className="flex min-h-9 items-center justify-between gap-4 py-1 text-sm"
                                >
                                    <span>{t(entry.labelKey)}</span>
                                    <Kbd keys={entry.keys} />
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </DialogContent>
        </Dialog>
    );
}
