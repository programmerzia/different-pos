import { Trash2 } from 'lucide-react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import type { HeldCart } from '../held';
import { PosDialog } from './pos-dialog';

/** Shift+F8: held bills on this register. ↑↓ move, Enter resumes, Delete discards. */
export function HeldDialog({
    open,
    held,
    totalOf,
    onResume,
    onDiscard,
    onClose,
}: {
    open: boolean;
    held: readonly HeldCart[];
    totalOf: (h: HeldCart) => number;
    onResume: (id: string) => void;
    onDiscard: (id: string) => void;
    onClose: () => void;
}) {
    const { t } = useT();
    const d = useDigits();
    const listRef = useRef<HTMLUListElement>(null);
    const time = (iso: string) =>
        d.n(
            new Date(iso).toLocaleTimeString(undefined, {
                hour: '2-digit',
                minute: '2-digit',
            }),
        );
    const focusRow = (i: number) =>
        listRef.current
            ?.querySelectorAll<HTMLButtonElement>('[data-held]')
            [i]?.focus();

    return (
        <PosDialog
            open={open}
            onClose={onClose}
            title={t('pos.held.title')}
            description={t('pos.held.hint')}
            className="sm:max-w-xl"
        >
            {held.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('pos.held.none')}
                </p>
            ) : (
                <ul
                    ref={listRef}
                    aria-label={t('pos.held.title')}
                    className="divide-y rounded-md border"
                >
                    {held.map((h, i) => (
                        <li key={h.id} className="flex items-center gap-1 pr-1">
                            <button
                                type="button"
                                data-held
                                autoFocus={i === 0}
                                className="flex min-h-11 min-w-0 flex-1 items-center gap-3 px-3 py-2 text-left hover:bg-accent focus-visible:bg-primary-soft focus-visible:outline-none"
                                onClick={() => onResume(h.id)}
                                onKeyDown={(e) => {
                                    if (
                                        e.key === 'ArrowDown' ||
                                        e.key === 'ArrowUp'
                                    ) {
                                        e.preventDefault();
                                        focusRow(
                                            Math.max(
                                                0,
                                                Math.min(
                                                    held.length - 1,
                                                    i +
                                                        (e.key === 'ArrowDown'
                                                            ? 1
                                                            : -1),
                                                ),
                                            ),
                                        );
                                    }
                                    if (e.key === 'Delete') {
                                        e.preventDefault();
                                        onDiscard(h.id);
                                        requestAnimationFrame(() =>
                                            focusRow(Math.max(0, i - 1)),
                                        );
                                    }
                                }}
                            >
                                <span className="num w-14 text-sm text-muted-foreground">
                                    {time(h.heldAt)}
                                </span>
                                <span className="min-w-0 flex-1 truncate">
                                    {h.state.customer?.name ??
                                        t('pos.customer.walk_in')}{' '}
                                    ·{' '}
                                    {t('pos.held.lines', {
                                        count: h.state.lines.length,
                                    })}
                                </span>
                                <span className="num font-semibold">
                                    {d.money(totalOf(h))}
                                </span>
                            </button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-11"
                                aria-label={t('pos.held.discard')}
                                onClick={() => onDiscard(h.id)}
                            >
                                <Trash2 aria-hidden="true" />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </PosDialog>
    );
}
