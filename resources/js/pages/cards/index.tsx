import { Head, router } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Download,
    Printer,
    RefreshCw,
    Search,
} from 'lucide-react';
import {
    useEffect,
    useMemo,
    useRef,
    useState,
    type KeyboardEvent,
} from 'react';
import {
    cardCode,
    formatNumber,
    tierOf,
    type CardBrand,
    type CardMember,
} from '@/components/cards/card-face';
import {
    MembershipCard,
    type CardSide,
} from '@/components/cards/membership-card';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { useServerTable } from '@/components/data-table/server-table';
import { Kbd } from '@/components/kbd';
import { RowContextMenu } from '@/components/row-context-menu';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { actions, type AppAction } from '@/command/actions';
import { registerMemberCardActions } from '@/command/customer-actions';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import {
    exportUrl,
    moveCursor,
    PER_SHEET,
    printUrl,
    queueAll,
    refreshQueue,
    sheetCount,
    sheetMembers,
    toggleQueued,
    type PrintLayout,
    type StudioLayout,
} from '@/lib/card-studio';
import { cn } from '@/lib/utils';
import type { TableMetaProp } from '@/types/customers';

type Totals = {
    members: number;
    issued: number;
    not_issued: number;
    lost: number;
};
type Group = { id: number; name: string; count: number };

type Props = {
    members: CardMember[];
    meta: TableMetaProp;
    totals: Totals;
    groups: Group[];
    brand: CardBrand;
    can: { issue: boolean; customers: boolean };
};

const STATUSES = ['issued', 'not_issued', 'lost'] as const;

/** The mockup's surface-sunken toolbar button (h-8), 44 px on touch. */
const toolButton =
    'flex min-h-11 items-center gap-1.5 rounded-lg border bg-muted px-3 text-xs font-semibold text-foreground shadow-xs outline-none transition-colors hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50 md:min-h-8';
const primaryButton =
    'flex min-h-11 items-center justify-center gap-1.5 rounded-lg bg-primary px-3.5 text-xs font-semibold text-primary-foreground shadow-xs outline-none hover:bg-primary-hover focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50 md:min-h-8';
const focusRing =
    'outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50';

/** Opens the print-ready page (the browser's print dialog saves the PDF) and records the print. */
function press(uuids: string[], layout: PrintLayout, crop: boolean) {
    if (uuids.length === 0) {
        return;
    }
    window.open(printUrl(uuids, layout, crop), '_blank', 'noopener');
    router.post(
        '/app/membership-cards/printed',
        { customers: uuids, layout },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['members', 'totals'],
        },
    );
}

/**
 * Membership Card Studio & Production, the baseline mockup's screen: member picker, flip, the CR80
 * preview, the bulk print queue, the A4 10-up sheet. Every customer is a member; printing a member
 * without an active card issues one on the server.
 */
export default function CardsIndex({
    members,
    meta,
    totals,
    groups,
    brand,
    can,
}: Props) {
    const { t } = useT();
    const digits = useDigits();
    const table = useServerTable({
        only: ['members', 'meta', 'totals', 'groups'],
        defaults: { page: 1, perPage: 40, sort: null, q: '', filters: {} },
    });
    const [layout, setLayout] = useState<StudioLayout>('cr80');
    const [side, setSide] = useState<CardSide>('front');
    const [current, setCurrent] = useState<CardMember | null>(
        members[0] ?? null,
    );
    const [queue, setQueue] = useState<CardMember[]>([]);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [cursor, setCursor] = useState(0);
    const [sheet, setSheet] = useState(0);
    const [crop, setCrop] = useState(true);
    const [reissuing, setReissuing] = useState<CardMember | null>(null);
    const searchRef = useRef<HTMLInputElement>(null);
    const pickerButton = useRef<HTMLButtonElement>(null);
    const listRef = useRef<HTMLUListElement>(null);

    useEffect(() => registerMemberCardActions(), []);

    // Server reloads (search, filters, prints) refresh the shown member and the queued ones.
    const [seen, setSeen] = useState(members);
    if (seen !== members) {
        setSeen(members);
        setQueue((q) => refreshQueue(q, members));
        setCursor(0);
        const fresh = current
            ? members.find((m) => m.uuid === current.uuid)
            : undefined;
        if (fresh) {
            setCurrent(fresh);
        } else if (!current && members[0]) {
            setCurrent(members[0]);
        }
    }

    const queued = (m: CardMember) => queue.some((q) => q.uuid === m.uuid);
    const flip = () => setSide((s) => (s === 'front' ? 'back' : 'front'));
    const sideLabel = t(`cards.studio.side_${side}`);

    /** Print Batch: the queue, or the member on screen when nothing is queued. */
    const batch = (): string[] =>
        queue.length > 0
            ? queue.map((m) => m.uuid)
            : current
              ? [current.uuid]
              : [];
    const sheetSource = queue.length > 0 ? queue : members;
    const sheets = sheetCount(sheetSource.length);
    const shownSheet = Math.min(sheet, sheets - 1);
    const sheetRows = sheetMembers(sheetSource, shownSheet);

    const printBatch = () =>
        layout === 'a4'
            ? press(
                  queue.length > 0
                      ? queue.map((m) => m.uuid)
                      : sheetRows.map((m) => m.uuid),
                  'a4',
                  crop,
              )
            : press(batch(), 'cr80', false);
    const pressPdf = () => press(batch(), 'bleed', crop);
    const pressCsv = () => {
        const uuids = batch();
        if (uuids.length > 0) {
            window.location.assign(exportUrl(uuids));
        }
    };
    const toggleCurrent = () =>
        current && setQueue((q) => toggleQueued(q, current));
    const openPicker = () => {
        setPickerOpen(true);
        requestAnimationFrame(() => searchRef.current?.focus());
    };

    useHotkeys('list', [
        {
            id: 'cards.search',
            keys: '/',
            labelKey: 'cards.studio.find_member',
            handler: (e) => {
                e.preventDefault();
                openPicker();
            },
        },
        {
            id: 'cards.layout.cr80',
            keys: 'alt+1',
            labelKey: 'cards.studio.layout_cr80',
            handler: () => setLayout('cr80'),
        },
        {
            id: 'cards.layout.a4',
            keys: 'alt+2',
            labelKey: 'cards.studio.layout_a4',
            handler: () => setLayout('a4'),
        },
        {
            id: 'cards.flip',
            keys: 'f',
            labelKey: 'cards.studio.flip',
            handler: flip,
        },
        {
            id: 'cards.queue',
            keys: 'q',
            labelKey: 'cards.studio.queue_toggle',
            handler: toggleCurrent,
        },
        ...(can.issue
            ? [
                  {
                      id: 'cards.print',
                      keys: 'p',
                      labelKey: 'cards.studio.print_batch',
                      handler: printBatch,
                  },
                  {
                      id: 'cards.export_csv',
                      keys: 'shift+e',
                      labelKey: 'cards.press.csv',
                      handler: pressCsv,
                  },
              ]
            : []),
    ]);

    // Palette entries for the studio while it is open; handlers read the latest render.
    const latest = useRef({
        printBatch,
        pressPdf,
        pressCsv,
        flip,
        toggleCurrent,
        openPicker,
    });
    useEffect(() => {
        latest.current = {
            printBatch,
            pressPdf,
            pressCsv,
            flip,
            toggleCurrent,
            openPicker,
        };
    });
    useEffect(() => {
        const list: AppAction[] = [
            {
                id: 'page.cards.find',
                entity: null,
                labelKey: 'cards.studio.find_member',
                icon: Search,
                keys: '/',
                run: () => latest.current.openPicker(),
            },
            {
                id: 'page.cards.cr80',
                entity: null,
                labelKey: 'cards.studio.layout_cr80',
                keys: 'alt+1',
                run: () => setLayout('cr80'),
            },
            {
                id: 'page.cards.a4',
                entity: null,
                labelKey: 'cards.studio.layout_a4',
                keys: 'alt+2',
                run: () => setLayout('a4'),
            },
            {
                id: 'page.cards.flip',
                entity: null,
                labelKey: 'cards.studio.flip',
                icon: RefreshCw,
                keys: 'f',
                run: () => latest.current.flip(),
            },
            {
                id: 'page.cards.queue',
                entity: null,
                labelKey: 'cards.studio.queue_toggle',
                keys: 'q',
                run: () => latest.current.toggleCurrent(),
            },
            ...(can.issue
                ? [
                      {
                          id: 'page.cards.print',
                          entity: null,
                          labelKey: 'cards.studio.print_batch',
                          icon: Printer,
                          keys: 'p',
                          run: () => latest.current.printBatch(),
                      },
                      {
                          id: 'page.cards.pdf',
                          entity: null,
                          labelKey: 'cards.studio.export_pdf',
                          icon: Download,
                          run: () => latest.current.pressPdf(),
                      },
                      {
                          id: 'page.cards.csv',
                          entity: null,
                          labelKey: 'cards.press.csv',
                          icon: Download,
                          keys: 'shift+e',
                          run: () => latest.current.pressCsv(),
                      },
                  ]
                : []),
        ];

        return actions.register(...list);
    }, [can.issue]);

    const handlers = useMemo(
        () => ({
            'member_card.queue': (m: CardMember) =>
                setQueue((q) => toggleQueued(q, m)),
            ...(can.issue
                ? {
                      'member_card.print': (m: CardMember) =>
                          press([m.uuid], 'cr80', false),
                      'member_card.export': (m: CardMember) =>
                          window.location.assign(exportUrl([m.uuid])),
                      'member_card.reissue': (m: CardMember) => setReissuing(m),
                  }
                : {}),
        }),
        [can.issue],
    );

    const reissue = (member: CardMember) =>
        new Promise<void>((resolve, reject) => {
            const card = member.card;
            if (!card) {
                resolve();

                return;
            }
            const again = () =>
                router.post(
                    `/app/membership-cards/${card.id}/reissue`,
                    {},
                    {
                        preserveScroll: true,
                        onSuccess: () => resolve(),
                        onError: () => reject(new Error('reissue')),
                    },
                );
            if (card.status === 'active') {
                router.post(
                    `/app/membership-cards/${card.id}/lost`,
                    {},
                    {
                        preserveScroll: true,
                        onSuccess: again,
                        onError: () => reject(new Error('lost')),
                    },
                );
            } else {
                again();
            }
        });

    const pick = (m: CardMember) => {
        setCurrent(m);
        setPickerOpen(false);
        pickerButton.current?.focus();
    };
    const focusMember = (i: number) =>
        listRef.current
            ?.querySelector<HTMLButtonElement>(`[data-member="${i}"]`)
            ?.focus();
    /** Up/Down walk the members (from the search box too), Enter shows one, Space queues it. */
    const onListKey = (e: KeyboardEvent<HTMLElement>) => {
        const inSearch = e.target === searchRef.current;
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const next =
                inSearch && e.key === 'ArrowDown'
                    ? 0
                    : moveCursor(
                          cursor,
                          e.key === 'ArrowDown' ? 1 : -1,
                          members.length,
                      );
            if (next >= 0) {
                setCursor(next);
                focusMember(next);
            }
        } else if (e.key === 'Enter' && inSearch) {
            e.preventDefault();
            const m = members[cursor] ?? members[0];
            if (m) {
                pick(m);
            }
        } else if (e.key === ' ' && !inSearch) {
            e.preventDefault();
            const m = members[cursor];
            if (m) {
                setQueue((q) => toggleQueued(q, m));
            }
        }
    };

    const memberLabel = (m: CardMember) =>
        `${m.name} (${digits.n(formatNumber(m.customer_no))}) · ${m.group ?? t('cards.studio.ungrouped')}`;
    const printStatus = (m: CardMember) => {
        if (m.card?.status === 'lost') {
            return {
                label: t('cards.studio.print_status.lost'),
                cls: 'bg-danger-soft text-danger',
            };
        }
        if (m.card?.status === 'active' && m.card.print_count > 0) {
            return {
                label: t('cards.studio.print_status.printed', {
                    count: digits.n(m.card.print_count),
                }),
                cls: 'bg-success-soft text-success',
            };
        }

        return {
            label: t('cards.studio.print_status.ready'),
            cls: 'bg-warning-soft text-warning',
        };
    };
    const tierShort = (m: CardMember) =>
        t(`cards.studio.tier_short.${tierOf(m.group)}`);

    return (
        <>
            <Head title={t('cards.title')} />
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
                {/* Studio header */}
                <div className="flex flex-col justify-between gap-2 border-b pb-3 sm:flex-row sm:items-center">
                    <div className="min-w-0">
                        <h1 className="text-lg font-bold text-foreground">
                            {t('cards.studio.title')}
                        </h1>
                        <p className="text-xs text-muted-foreground">
                            {t('cards.studio.subtitle')}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 xl:shrink-0 xl:flex-nowrap">
                        {can.issue ? (
                            <button
                                type="button"
                                disabled={
                                    !current?.card ||
                                    !['active', 'lost'].includes(
                                        current.card.status,
                                    )
                                }
                                onClick={() => current && setReissuing(current)}
                                className={cn(
                                    'flex min-h-11 items-center gap-1.5 rounded-lg bg-warning-soft px-3 text-xs font-bold text-warning transition-colors hover:bg-warning hover:text-primary-foreground disabled:opacity-50 md:min-h-8',
                                    focusRing,
                                )}
                            >
                                <RefreshCw
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                                <span>{t('cards.studio.lost_reissue')}</span>
                            </button>
                        ) : null}

                        <fieldset className="m-0 flex min-w-0 rounded-lg border bg-muted p-0.5 text-xs">
                            <legend className="sr-only">
                                {t('cards.studio.layout')}
                            </legend>
                            {(
                                [
                                    [
                                        'cr80',
                                        'cards.studio.layout_cr80',
                                        'Alt+1',
                                    ],
                                    ['a4', 'cards.studio.layout_a4', 'Alt+2'],
                                ] as const
                            ).map(([id, key, keys]) => (
                                <button
                                    key={id}
                                    type="button"
                                    aria-pressed={layout === id}
                                    aria-keyshortcuts={keys}
                                    onClick={() => setLayout(id)}
                                    className={cn(
                                        'min-h-11 rounded px-3 font-medium md:min-h-7',
                                        focusRing,
                                        layout === id
                                            ? 'bg-card font-bold text-primary shadow-xs'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {t(key)}
                                </button>
                            ))}
                        </fieldset>

                        {can.issue ? (
                            <>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button
                                            type="button"
                                            className={toolButton}
                                        >
                                            <Download
                                                className="size-3.5"
                                                aria-hidden="true"
                                            />
                                            <span>
                                                {t('cards.studio.export')}
                                            </span>
                                            <ChevronDown
                                                className="size-3"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        className="w-80"
                                    >
                                        <DropdownMenuItem
                                            onSelect={pressPdf}
                                            className="flex-col items-start gap-0.5 py-2"
                                        >
                                            <span className="text-xs font-semibold">
                                                {t('cards.studio.export_pdf')}
                                            </span>
                                            <span className="text-[10px] text-muted-foreground">
                                                {t(
                                                    'cards.studio.export_pdf_hint',
                                                )}
                                            </span>
                                        </DropdownMenuItem>
                                        <DropdownMenuCheckboxItem
                                            checked={crop}
                                            onCheckedChange={(v) =>
                                                setCrop(v === true)
                                            }
                                            onSelect={(e) => e.preventDefault()}
                                            className="text-[11px] text-muted-foreground"
                                        >
                                            {t('cards.studio.crop_marks')}
                                        </DropdownMenuCheckboxItem>
                                        <DropdownMenuItem
                                            onSelect={pressCsv}
                                            className="flex-col items-start gap-0.5 py-2"
                                        >
                                            <span className="text-xs font-semibold">
                                                {t('cards.studio.export_csv')}
                                            </span>
                                            <span className="text-[10px] text-muted-foreground">
                                                {queue.length > 0
                                                    ? t(
                                                          'cards.studio.export_queued',
                                                          {
                                                              count: digits.n(
                                                                  queue.length,
                                                              ),
                                                          },
                                                      )
                                                    : t(
                                                          'cards.studio.export_current',
                                                      )}
                                            </span>
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuLabel className="text-[10px] font-normal text-muted-foreground">
                                            {t('cards.studio.press_note')}
                                        </DropdownMenuLabel>
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                <button
                                    type="button"
                                    className={primaryButton}
                                    onClick={printBatch}
                                    disabled={batch().length === 0}
                                >
                                    <Printer
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <span>{t('cards.studio.print_batch')}</span>
                                    <Kbd keys="p" />
                                </button>
                            </>
                        ) : null}
                    </div>
                </div>

                {/* Customer selector & flip switcher strip */}
                <div className="flex flex-col justify-between gap-2 rounded-xl border bg-card p-3 shadow-xs sm:flex-row sm:items-center">
                    <div className="flex flex-wrap items-center gap-3">
                        <span
                            id="member-select-label"
                            className="text-xs font-semibold text-foreground"
                        >
                            {t('cards.studio.select_member')}
                        </span>
                        <Popover open={pickerOpen} onOpenChange={setPickerOpen}>
                            <PopoverTrigger asChild>
                                <button
                                    ref={pickerButton}
                                    type="button"
                                    aria-labelledby="member-select-label"
                                    aria-haspopup="listbox"
                                    aria-expanded={pickerOpen}
                                    onKeyDown={(e) => {
                                        if (
                                            e.key === 'ArrowDown' ||
                                            e.key === 'ArrowUp'
                                        ) {
                                            e.preventDefault();
                                            openPicker();
                                        }
                                    }}
                                    className={cn(
                                        'flex min-h-11 max-w-full min-w-72 items-center justify-between gap-2 rounded-lg border bg-muted px-2.5 text-xs font-semibold text-foreground md:min-h-8',
                                        focusRing,
                                    )}
                                >
                                    <span className="truncate">
                                        {current
                                            ? memberLabel(current)
                                            : t('cards.studio.no_member')}
                                    </span>
                                    <ChevronDown
                                        className="size-3.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                </button>
                            </PopoverTrigger>
                            <PopoverContent
                                align="start"
                                className="w-[min(32rem,calc(100vw-2rem))] p-0"
                                onKeyDown={onListKey}
                            >
                                <div className="flex flex-col gap-2 border-b p-2">
                                    <div className="relative">
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <input
                                            ref={searchRef}
                                            type="search"
                                            aria-controls="member-list"
                                            value={table.searchText}
                                            onChange={(e) =>
                                                table.setSearch(e.target.value)
                                            }
                                            placeholder={t(
                                                'cards.studio.search',
                                            )}
                                            aria-label={t(
                                                'cards.studio.search',
                                            )}
                                            className={cn(
                                                'min-h-11 w-full rounded-lg border bg-muted pr-3 pl-8 text-xs md:min-h-8',
                                                focusRing,
                                            )}
                                        />
                                    </div>
                                    <div className="flex flex-wrap gap-2 text-xs">
                                        <select
                                            aria-label={t(
                                                'cards.studio.group_filter',
                                            )}
                                            value={
                                                table.state.filters['group'] ??
                                                ''
                                            }
                                            onChange={(e) =>
                                                table.setFilter(
                                                    'group',
                                                    e.target.value,
                                                )
                                            }
                                            className={cn(
                                                'min-h-11 rounded-lg border bg-muted px-2 md:min-h-8',
                                                focusRing,
                                            )}
                                        >
                                            <option value="">
                                                {t('cards.studio.all_groups', {
                                                    count: digits.n(
                                                        totals.members,
                                                    ),
                                                })}
                                            </option>
                                            {groups.map((g) => (
                                                <option
                                                    key={g.id}
                                                    value={String(g.id)}
                                                >{`${g.name} (${digits.n(g.count)})`}</option>
                                            ))}
                                        </select>
                                        <select
                                            aria-label={t(
                                                'cards.studio.status_filter',
                                            )}
                                            value={
                                                table.state.filters['status'] ??
                                                ''
                                            }
                                            onChange={(e) =>
                                                table.setFilter(
                                                    'status',
                                                    e.target.value,
                                                )
                                            }
                                            className={cn(
                                                'min-h-11 rounded-lg border bg-muted px-2 md:min-h-8',
                                                focusRing,
                                            )}
                                        >
                                            <option value="">
                                                {t('cards.studio.any_status')}
                                            </option>
                                            {STATUSES.map((s) => (
                                                <option
                                                    key={s}
                                                    value={s}
                                                >{`${t(`cards.studio.status.${s}`)} (${digits.n(totals[s])})`}</option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                                <ul
                                    id="member-list"
                                    ref={listRef}
                                    aria-label={t('cards.studio.members')}
                                    aria-busy={table.loading}
                                    className="max-h-80 overflow-y-auto p-1"
                                >
                                    {members.length === 0 ? (
                                        <li
                                            role="presentation"
                                            className="px-3 py-6 text-center text-xs text-muted-foreground"
                                        >
                                            {t('cards.studio.empty')}
                                        </li>
                                    ) : (
                                        members.map((m, i) => (
                                            <li
                                                key={m.uuid}
                                                data-active={
                                                    i === cursor
                                                        ? ''
                                                        : undefined
                                                }
                                                className="flex min-h-11 items-center gap-2 rounded-md px-2 text-xs focus-within:bg-accent hover:bg-accent md:min-h-8"
                                            >
                                                <button
                                                    type="button"
                                                    tabIndex={-1}
                                                    aria-pressed={queued(m)}
                                                    aria-label={t(
                                                        'cards.studio.queue_named',
                                                        { name: m.name },
                                                    )}
                                                    onClick={() =>
                                                        setQueue((q) =>
                                                            toggleQueued(q, m),
                                                        )
                                                    }
                                                    className={cn(
                                                        'flex size-5 shrink-0 items-center justify-center rounded border',
                                                        queued(m)
                                                            ? 'border-primary bg-primary text-primary-foreground'
                                                            : 'bg-background',
                                                    )}
                                                >
                                                    {queued(m) ? (
                                                        <Check
                                                            className="size-3"
                                                            aria-hidden="true"
                                                        />
                                                    ) : null}
                                                </button>
                                                <button
                                                    type="button"
                                                    data-member={i}
                                                    aria-current={
                                                        current?.uuid === m.uuid
                                                            ? 'true'
                                                            : undefined
                                                    }
                                                    onFocus={() => setCursor(i)}
                                                    onClick={() => pick(m)}
                                                    className="flex min-h-9 min-w-0 flex-1 items-center justify-between gap-2 rounded text-left outline-none focus-visible:ring-2 focus-visible:ring-ring md:min-h-7"
                                                >
                                                    <span className="truncate font-medium">
                                                        {memberLabel(m)}
                                                    </span>
                                                    {m.shop_name ? (
                                                        <span className="truncate text-muted-foreground">
                                                            {m.shop_name}
                                                        </span>
                                                    ) : null}
                                                </button>
                                            </li>
                                        ))
                                    )}
                                </ul>
                                <div className="flex items-center justify-between gap-2 border-t p-2 text-xs">
                                    <button
                                        type="button"
                                        className={toolButton}
                                        onClick={() =>
                                            setQueue((q) =>
                                                queueAll(q, members),
                                            )
                                        }
                                        disabled={members.length === 0}
                                    >
                                        {t('cards.studio.queue_page', {
                                            count: digits.n(members.length),
                                        })}
                                    </button>
                                    <div className="flex items-center gap-1">
                                        <span className="text-muted-foreground">
                                            {t('cards.studio.page_of', {
                                                page: digits.n(meta.page),
                                                last: digits.n(
                                                    Math.max(1, meta.lastPage),
                                                ),
                                                total: digits.n(meta.total),
                                            })}
                                        </span>
                                        <button
                                            type="button"
                                            aria-label={t('table.prev')}
                                            disabled={meta.page <= 1}
                                            onClick={() =>
                                                table.setPage(meta.page - 1)
                                            }
                                            className={cn(toolButton, 'px-2')}
                                        >
                                            <ChevronLeft
                                                className="size-3.5"
                                                aria-hidden="true"
                                            />
                                        </button>
                                        <button
                                            type="button"
                                            aria-label={t('table.next')}
                                            disabled={
                                                meta.page >= meta.lastPage
                                            }
                                            onClick={() =>
                                                table.setPage(meta.page + 1)
                                            }
                                            className={cn(toolButton, 'px-2')}
                                        >
                                            <ChevronRight
                                                className="size-3.5"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </div>
                                </div>
                                <p className="border-t px-2 py-1.5 text-[10px] text-muted-foreground">
                                    {t('cards.studio.picker_keys')}
                                </p>
                            </PopoverContent>
                        </Popover>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={flip}
                            className={cn(
                                toolButton,
                                'px-3.5 font-bold text-primary',
                            )}
                        >
                            <RefreshCw
                                className="size-3.5"
                                aria-hidden="true"
                            />
                            <span>
                                {t('cards.studio.flip_showing', {
                                    side: sideLabel,
                                })}
                            </span>
                        </button>
                    </div>
                </div>

                {/* CR80: the interactive preview and the bulk print queue */}
                {layout === 'cr80' ? (
                    <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                        <div className="flex flex-col items-center justify-center gap-4 rounded-2xl border bg-card p-6 shadow-xs">
                            <div className="flex w-full items-center justify-between text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                <span>{t('cards.studio.simulation')}</span>
                                <span className="font-mono text-[10px]">
                                    ISO/IEC 7810 ID-1 (85.6 × 54mm)
                                </span>
                            </div>
                            {current ? (
                                <RowContextMenu
                                    entity="member_card"
                                    row={current}
                                    handlers={handlers}
                                >
                                    <button
                                        type="button"
                                        onClick={flip}
                                        aria-label={t(
                                            'cards.studio.card_preview',
                                            {
                                                name: current.name,
                                                side: sideLabel,
                                            },
                                        )}
                                        className={cn(
                                            'max-w-full rounded-2xl',
                                            focusRing,
                                        )}
                                    >
                                        <MembershipCard
                                            member={current}
                                            brand={brand}
                                            side={side}
                                        />
                                    </button>
                                </RowContextMenu>
                            ) : (
                                <p className="py-16 text-xs text-muted-foreground">
                                    {t('cards.studio.empty')}
                                </p>
                            )}
                            <button
                                type="button"
                                onClick={flip}
                                className={cn(
                                    'flex items-center gap-1 rounded text-xs font-semibold text-primary hover:underline',
                                    focusRing,
                                )}
                            >
                                <RefreshCw
                                    className="size-3"
                                    aria-hidden="true"
                                />
                                <span>
                                    {t('cards.studio.click_flip', {
                                        side: sideLabel,
                                    })}
                                </span>
                            </button>
                        </div>

                        <section
                            aria-labelledby="queue-title"
                            className="flex flex-col gap-3 rounded-2xl border bg-card p-4 shadow-xs"
                        >
                            <div className="flex items-center justify-between">
                                <h2
                                    id="queue-title"
                                    className="text-xs font-bold tracking-wider text-foreground uppercase"
                                >
                                    {t('cards.studio.queue_title')}
                                </h2>
                                <span className="rounded-full bg-primary-soft px-2 py-0.5 text-[10px] font-bold text-primary">
                                    {t('cards.studio.queued', {
                                        count: digits.n(queue.length),
                                    })}
                                </span>
                            </div>
                            <div className="overflow-x-auto rounded-xl border">
                                <table className="w-full text-left text-xs">
                                    <caption className="sr-only">
                                        {t('cards.studio.queue_title')}
                                    </caption>
                                    <thead className="border-b bg-muted font-semibold text-muted-foreground">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-2.5 py-2"
                                            >
                                                {t('cards.studio.col_no')}
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-2.5 py-2"
                                            >
                                                {t('cards.studio.col_name')}
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-2.5 py-2"
                                            >
                                                {t('cards.studio.col_tier')}
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-2.5 py-2 text-center"
                                            >
                                                {t('cards.studio.col_status')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {queue.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={4}
                                                    className="px-2.5 py-6 text-center text-muted-foreground"
                                                >
                                                    {t(
                                                        'cards.studio.queue_empty',
                                                    )}
                                                </td>
                                            </tr>
                                        ) : (
                                            queue.map((m) => {
                                                const status = printStatus(m);

                                                return (
                                                    <RowContextMenu
                                                        key={m.uuid}
                                                        entity="member_card"
                                                        row={m}
                                                        handlers={handlers}
                                                    >
                                                        <tr
                                                            tabIndex={0}
                                                            data-selected={
                                                                current?.uuid ===
                                                                m.uuid
                                                                    ? ''
                                                                    : undefined
                                                            }
                                                            onClick={() =>
                                                                setCurrent(m)
                                                            }
                                                            onKeyDown={(e) => {
                                                                if (
                                                                    e.key ===
                                                                    'Enter'
                                                                ) {
                                                                    setCurrent(
                                                                        m,
                                                                    );
                                                                } else if (
                                                                    e.key ===
                                                                    'Delete'
                                                                ) {
                                                                    setQueue(
                                                                        (q) =>
                                                                            toggleQueued(
                                                                                q,
                                                                                m,
                                                                            ),
                                                                    );
                                                                }
                                                            }}
                                                            className={cn(
                                                                'cursor-pointer hover:bg-muted/50 data-[selected]:bg-primary-soft',
                                                                focusRing,
                                                            )}
                                                        >
                                                            <td className="px-2.5 py-2 font-mono font-bold text-primary">
                                                                {digits.n(
                                                                    formatNumber(
                                                                        m.customer_no,
                                                                    ),
                                                                )}
                                                            </td>
                                                            <td className="max-w-30 truncate px-2.5 py-2 font-medium text-foreground">
                                                                {m.name}
                                                            </td>
                                                            <td className="px-2.5 py-2">
                                                                <span className="rounded bg-muted px-1.5 text-[10px] font-bold">
                                                                    {tierShort(
                                                                        m,
                                                                    )}
                                                                </span>
                                                            </td>
                                                            <td className="px-2.5 py-2 text-center">
                                                                <span
                                                                    className={cn(
                                                                        'rounded-full px-2 py-0.5 text-[10px] font-bold',
                                                                        status.cls,
                                                                    )}
                                                                >
                                                                    {
                                                                        status.label
                                                                    }
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    </RowContextMenu>
                                                );
                                            })
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            <div className="flex gap-2 pt-2">
                                {can.issue ? (
                                    <button
                                        type="button"
                                        className={cn(
                                            primaryButton,
                                            'flex-1 gap-1',
                                        )}
                                        onClick={() =>
                                            press(
                                                queue.map((m) => m.uuid),
                                                'cr80',
                                                false,
                                            )
                                        }
                                        disabled={queue.length === 0}
                                    >
                                        <Printer
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                        <span>
                                            {t('cards.studio.print_queued')}
                                        </span>
                                    </button>
                                ) : null}
                                <button
                                    type="button"
                                    className={cn(
                                        toolButton,
                                        'font-medium text-muted-foreground',
                                    )}
                                    onClick={() => setQueue([])}
                                    disabled={queue.length === 0}
                                >
                                    {t('cards.studio.clear')}
                                </button>
                            </div>
                        </section>
                    </div>
                ) : (
                    /* A4 sheet, 10-up */
                    <section
                        aria-labelledby="a4-title"
                        className="flex flex-col gap-4 rounded-xl border bg-card p-6"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2 text-xs font-semibold text-muted-foreground">
                            <h2 id="a4-title">{t('cards.studio.a4_title')}</h2>
                            <span className="font-mono text-[10px]">
                                {t('cards.studio.a4_scale')}
                            </span>
                        </div>
                        <div className="mc-paper mx-auto grid w-full max-w-2xl grid-cols-2 gap-4 border border-dashed border-input p-4 shadow-sm">
                            {sheetRows.map((m, n) => (
                                <div
                                    key={m.uuid}
                                    className="mc-paper relative flex h-28 flex-col justify-between rounded-lg border p-3 text-xs"
                                >
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <div className="text-[11px] font-extrabold tracking-wider text-primary-hover">
                                                {brand.name}
                                            </div>
                                            <div className="text-[9px] text-muted-foreground">
                                                {t(
                                                    'cards.studio.privilege_card',
                                                    {
                                                        n: digits.n(
                                                            shownSheet *
                                                                PER_SHEET +
                                                                n +
                                                                1,
                                                        ),
                                                    },
                                                )}
                                            </div>
                                        </div>
                                        <span className="rounded bg-muted px-1 font-mono text-[9px] font-bold">
                                            {tierOf(m.group) === 'vip'
                                                ? 'VIP'
                                                : t('cards.studio.member_tag')}
                                        </span>
                                    </div>
                                    <div className="font-mono text-sm font-bold tracking-wider">
                                        {digits.n(formatNumber(m.customer_no))}
                                    </div>
                                    <div className="flex items-end justify-between border-t pt-1 text-[10px]">
                                        <span className="truncate font-bold">
                                            {m.name}
                                        </span>
                                        <span className="font-mono text-muted-foreground">
                                            {cardCode(m)}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <nav
                            aria-label={t('cards.studio.sheet_pager')}
                            className="flex items-center justify-center gap-2 text-xs"
                        >
                            <button
                                type="button"
                                className={cn(toolButton, 'px-2')}
                                aria-label={t('table.prev')}
                                disabled={shownSheet === 0}
                                onClick={() => setSheet(shownSheet - 1)}
                            >
                                <ChevronLeft
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                            </button>
                            <span className="text-muted-foreground">
                                {t(
                                    queue.length > 0
                                        ? 'cards.studio.sheet_of_queue'
                                        : 'cards.studio.sheet_of_page',
                                    {
                                        n: digits.n(shownSheet + 1),
                                        total: digits.n(sheets),
                                    },
                                )}
                            </span>
                            <button
                                type="button"
                                className={cn(toolButton, 'px-2')}
                                aria-label={t('table.next')}
                                disabled={shownSheet >= sheets - 1}
                                onClick={() => setSheet(shownSheet + 1)}
                            >
                                <ChevronRight
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                            </button>
                        </nav>
                    </section>
                )}
            </div>

            <ConfirmDialog
                open={reissuing !== null}
                onOpenChange={(open) => !open && setReissuing(null)}
                title={t('cards.studio.reissue_title')}
                description={t(
                    reissuing?.card?.status === 'lost'
                        ? 'cards.studio.reissue_body_lost'
                        : 'cards.studio.reissue_body',
                    {
                        name: reissuing?.name ?? '',
                        code: reissuing?.card?.code ?? '',
                    },
                )}
                confirmLabel={t('cards.studio.lost_reissue')}
                destructive
                onConfirm={() => (reissuing ? reissue(reissuing) : undefined)}
            />
        </>
    );
}
