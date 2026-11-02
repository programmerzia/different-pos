import { Command } from 'cmdk';
import { Clock, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Kbd } from '@/components/kbd';
import { actions, useActionVersion, type AppAction } from '@/command/actions';
import { NAV } from '@/command/nav';
import { navigateTo } from '@/command/navigate';
import { pushRecent, readRecents, type Recent } from '@/command/recents';
import {
    EMPTY_RESULTS,
    hitHref,
    searchApi,
    type SearchHit,
    type SearchResults,
} from '@/command/search';
import { useUiStore } from '@/command/ui-store';
import { useCan } from '@/hooks/use-can';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeyScope } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';

const itemClass =
    'flex min-h-11 cursor-default items-center gap-2 rounded-md px-2 text-sm md:min-h-9 data-[selected=true]:bg-primary-soft [&_svg]:size-4 [&_svg]:text-muted-foreground';
const headingClass =
    '[&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:py-1 [&_[cmdk-group-heading]]:text-xs [&_[cmdk-group-heading]]:font-medium [&_[cmdk-group-heading]]:text-muted-foreground';

/**
 * Ctrl+K: pages and actions (from the shared action registry) filter locally; products, customers
 * (`#number`) and invoices come from GET /app/api/search with a debounce and abort.
 */
export function CommandPalette() {
    const { t } = useT();
    const can = useCan();
    const digits = useDigits();
    const open = useUiStore((s) => s.overlay === 'palette');
    const close = useUiStore((s) => s.close);
    const version = useActionVersion();
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResults>(EMPTY_RESULTS);
    const [failed, setFailed] = useState(false);
    const [recents, setRecents] = useState<Recent[]>([]);
    useHotkeyScope('dialog', open);

    useEffect(() => {
        if (open) {
            setRecents(readRecents());
        } else {
            setQuery('');
            setResults(EMPTY_RESULTS);
            setFailed(false);
        }
    }, [open]);

    useEffect(() => {
        const q = query.trim();
        if (!open || q.length < 2) {
            setResults(EMPTY_RESULTS);

            return;
        }
        const controller = new AbortController();
        const timer = setTimeout(() => {
            searchApi(q, controller.signal)
                .then((r) => {
                    setResults(r);
                    setFailed(false);
                })
                .catch((error: unknown) => {
                    if ((error as Error).name !== 'AbortError') {
                        setFailed(true);
                    }
                });
        }, 200);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query, open]);

    const needle = query.trim().toLowerCase();
    const matches = (text: string) =>
        needle === '' || text.toLowerCase().includes(needle);

    const pages = useMemo(
        () =>
            NAV.filter((g) => can(g.permission)).flatMap((g) => [
                {
                    id: g.id,
                    label: t(g.labelKey),
                    href: g.href,
                    external: g.external === true,
                    hint: g.chord ? `g ${g.chord}` : g.keys,
                },
                ...(g.children ?? [])
                    .filter((c) => can(c.permission))
                    .map((c) => ({
                        id: c.id,
                        label: `${t(g.labelKey)} › ${t(c.labelKey)}`,
                        href: c.href,
                        external: false,
                        hint: undefined,
                    })),
            ]),
        [can, t],
    );
    // `version` re-runs the memo when actions are (un)registered.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    const commands = useMemo(() => actions.commands(can), [can, version]);

    const go = (id: string, label: string, href: string, external = false) => {
        pushRecent({ id, label, href });
        close();
        navigateTo(href, { external });
    };

    const runCommand = (action: AppAction) => {
        close();
        if (action.href) {
            navigateTo(action.href);
        } else {
            action.run?.({ row: undefined, navigate: navigateTo });
        }
    };

    const hits = (
        kind: 'product' | 'customer' | 'sale',
        list: SearchHit[],
        group: string,
    ) =>
        list.length > 0 && (
            <Command.Group heading={group} className={headingClass}>
                {list.map((hit) => {
                    const href = hitHref[kind](hit);
                    const money = kind === 'product' ? hit.price : hit.total;

                    return (
                        <Command.Item
                            key={`${kind}-${hit.id}`}
                            value={`${kind}-${hit.id}`}
                            onSelect={() =>
                                go(`${kind}-${hit.id}`, hit.label, href)
                            }
                            className={itemClass}
                        >
                            <span className="truncate">{hit.label}</span>
                            {hit.sub ? (
                                <span className="truncate text-xs text-muted-foreground">
                                    {hit.sub}
                                </span>
                            ) : null}
                            {money !== undefined ? (
                                <span className="num ml-auto text-xs">
                                    {digits.money(money)}
                                </span>
                            ) : null}
                        </Command.Item>
                    );
                })}
            </Command.Group>
        );

    return (
        <Dialog open={open} onOpenChange={(next) => !next && close()}>
            <DialogContent className="top-[20%] max-w-xl translate-y-0 gap-0 overflow-hidden p-0">
                <DialogTitle className="sr-only">
                    {t('palette.title')}
                </DialogTitle>
                <DialogDescription className="sr-only">
                    {t('palette.hint')}
                </DialogDescription>
                <Command shouldFilter={false} label={t('palette.title')} loop>
                    <div className="flex items-center gap-2 border-b px-3">
                        <Search
                            className="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Command.Input
                            value={query}
                            onValueChange={setQuery}
                            placeholder={t('palette.placeholder')}
                            className="h-12 flex-1 bg-transparent text-sm outline-none placeholder:text-subtle-foreground"
                        />
                        <Kbd keys="escape" />
                    </div>
                    <Command.List className="max-h-80 overflow-y-auto p-2">
                        <Command.Empty className="px-2 py-6 text-center text-sm text-muted-foreground">
                            {failed ? t('palette.error') : t('palette.empty')}
                        </Command.Empty>

                        {needle === '' && recents.length > 0 && (
                            <Command.Group
                                heading={t('palette.recent')}
                                className={headingClass}
                            >
                                {recents.map((r) => (
                                    <Command.Item
                                        key={r.id}
                                        value={`recent-${r.id}`}
                                        onSelect={() =>
                                            go(r.id, r.label, r.href)
                                        }
                                        className={itemClass}
                                    >
                                        <Clock aria-hidden="true" />
                                        {r.label}
                                    </Command.Item>
                                ))}
                            </Command.Group>
                        )}

                        {pages.some((p) => matches(p.label)) && (
                            <Command.Group
                                heading={t('palette.pages')}
                                className={headingClass}
                            >
                                {pages
                                    .filter((p) => matches(p.label))
                                    .map((p) => (
                                        <Command.Item
                                            key={p.id}
                                            value={`page-${p.id}`}
                                            onSelect={() =>
                                                go(
                                                    `page-${p.id}`,
                                                    p.label,
                                                    p.href,
                                                    p.external,
                                                )
                                            }
                                            className={itemClass}
                                        >
                                            {p.label}
                                            {p.hint ? (
                                                <Kbd
                                                    keys={p.hint}
                                                    className="ml-auto"
                                                />
                                            ) : null}
                                        </Command.Item>
                                    ))}
                            </Command.Group>
                        )}

                        {commands.some((c) => matches(t(c.labelKey))) && (
                            <Command.Group
                                heading={t('palette.actions')}
                                className={headingClass}
                            >
                                {commands
                                    .filter((c) => matches(t(c.labelKey)))
                                    .map((c) => (
                                        <Command.Item
                                            key={c.id}
                                            value={c.id}
                                            onSelect={() => runCommand(c)}
                                            className={itemClass}
                                        >
                                            {c.icon ? (
                                                <c.icon aria-hidden="true" />
                                            ) : null}
                                            {t(c.labelKey)}
                                            {c.keys ? (
                                                <Kbd
                                                    keys={c.keys}
                                                    className="ml-auto"
                                                />
                                            ) : null}
                                        </Command.Item>
                                    ))}
                            </Command.Group>
                        )}

                        {hits(
                            'product',
                            results.products,
                            t('palette.products'),
                        )}
                        {hits(
                            'customer',
                            results.customers,
                            t('palette.customers'),
                        )}
                        {hits('sale', results.sales, t('palette.invoices'))}
                    </Command.List>
                </Command>
            </DialogContent>
        </Dialog>
    );
}
