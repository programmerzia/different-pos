import { type RowSelectionState } from '@tanstack/react-table';
import {
    ChevronLeft,
    ChevronRight,
    Columns3,
    type LucideIcon,
    SearchX,
    X,
} from 'lucide-react';
import { type KeyboardEvent, type ReactNode, useRef, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { RowContextMenu } from '@/components/row-context-menu';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import type { Handlers } from '@/command/actions';
import { navigateTo } from '@/command/navigate';
import { useHotkeys, type HotkeySpec } from '@/hotkeys/use-hotkeys';
import { useIsMobile } from '@/hooks/use-mobile';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';
import { focusListEntryRow, isInListRows } from './row-focus';
import { INITIAL_NAV, reduceNavKey, type NavState } from './table-nav';

export type DataColumn<Row> = {
    id: string;
    /** i18n key for the header. */
    headerKey: string;
    /** Literal header text (data-driven labels such as a branch name); wins over `headerKey`. */
    header?: string;
    /** Mockup quantity columns are centred; default is left (numeric columns right). */
    align?: 'center';
    cell: (row: Row) => ReactNode;
    /** Numbers/money: right-aligned, tabular. */
    numeric?: boolean;
    /** Server sort key; omit = not sortable. */
    sortKey?: string;
    /** Cannot be hidden from the column chooser. */
    required?: boolean;
    /** Hidden by default. */
    hidden?: boolean;
    /** Phone card layout: `title` = card heading, `hidden` = omitted, default = label/value line. */
    mobile?: 'title' | 'hidden';
};

export type BulkAction<Row> = {
    id: string;
    labelKey: string;
    icon?: LucideIcon;
    destructive?: boolean;
    run: (rows: Row[]) => void;
};

export type TableMeta = {
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
};

type Props<Row> = {
    /** Entity key for the shared action registry (context menu). */
    entity: string;
    rows: Row[];
    columns: DataColumn<Row>[];
    meta: TableMeta;
    getRowId: (row: Row) => string;
    /** Where a row opens; `undefined` for a row that cannot be opened here. */
    rowHref?: (row: Row) => string | undefined;
    handlers?: Handlers<Row>;
    bulkActions?: BulkAction<Row>[];
    sort: string | null;
    onSort: (sortKey: string) => void;
    onPage: (page: number) => void;
    loading?: boolean;
    /** Shown instead of the table when there are no rows. */
    empty?: ReactNode;
    selectable?: boolean;
    caption: string;
    /** Opt-in totals row (mockup `tfoot`): cells keyed by column id; the first visible column shows `label` (default "Total (n)"). */
    totals?: TableTotals;
    /** Opt-in "Rows per page" select in the footer (pair with `useServerTable().setPerPage`). */
    onPerPage?: (perPage: number) => void;
    perPageOptions?: number[];
    /** Where the column chooser lives: above the table (default) or nowhere (render `<ColumnChooser>` in the page header). */
    chooser?: 'inline' | 'none';
    /** Controlled column visibility (from `useColumnVisibility`), so a header `<ColumnChooser>` drives this table. */
    visibility?: ColumnVisibility;
    onVisibilityChange?: (next: ColumnVisibility) => void;
    /** Fill the remaining page height and scroll inside the card (the mockup list layout; use inside `<Page fill>`). */
    fill?: boolean;
};

export type ColumnVisibility = Record<string, boolean>;

export type TableTotals = {
    label?: ReactNode;
    cells: Record<string, ReactNode>;
};

const SKELETON_ROWS = 8;
const PER_PAGE_OPTIONS = [25, 50, 100];

const initialVisibility = <Row,>(
    columns: DataColumn<Row>[],
): ColumnVisibility =>
    Object.fromEntries(
        columns.filter((c) => c.hidden).map((c) => [c.id, false]),
    );

/** Column visibility state for a table whose chooser sits in the page header. */
export function useColumnVisibility<Row>(columns: DataColumn<Row>[]) {
    const [visibility, setVisibility] = useState<ColumnVisibility>(() =>
        initialVisibility(columns),
    );

    return { visibility, setVisibility };
}

/** The mockup "Columns" button + checklist. Use with `useColumnVisibility` and `<DataTable chooser="none" …>`. */
export function ColumnChooser<Row>({
    columns,
    visibility,
    onChange,
}: {
    columns: DataColumn<Row>[];
    visibility: ColumnVisibility;
    onChange: (next: ColumnVisibility) => void;
}) {
    const { t } = useT();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    className="touch-target"
                >
                    <Columns3 aria-hidden="true" />
                    <span className="max-sm:sr-only">{t('table.columns')}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-52 rounded-xl p-2 text-xs"
            >
                <div className="mb-1 flex items-center justify-between border-b px-1.5 py-0.5 text-[11px] font-semibold text-muted-foreground">
                    <span>{t('table.visible_columns')}</span>
                    <button
                        type="button"
                        className="text-[10px] text-primary hover:underline"
                        onClick={() => onChange({})}
                    >
                        {t('table.reset_columns')}
                    </button>
                </div>
                {columns
                    .filter((c) => !c.required)
                    .map((c) => (
                        <DropdownMenuCheckboxItem
                            key={c.id}
                            className="rounded-md py-1 text-[11px]"
                            checked={visibility[c.id] !== false}
                            onCheckedChange={(checked) =>
                                onChange({ ...visibility, [c.id]: checked })
                            }
                            onSelect={(e) => e.preventDefault()}
                        >
                            {c.header ?? t(c.headerKey)}
                        </DropdownMenuCheckboxItem>
                    ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

const noop = () => undefined;
/**
 * `J`/`K` with no row focused enter the list on its first (roving) row; once a row has focus the
 * table's own onKeyDown handles them (and marks the event handled, so this does nothing).
 */
const enterRows = (event: globalThis.KeyboardEvent) => {
    if (!event.defaultPrevented && !isInListRows(document.activeElement)) {
        focusListEntryRow();
    }
};
/** Listed in the `?` overlay (the table handles most keys itself, on the focused row). */
const LIST_KEYS: HotkeySpec[] = [
    {
        id: 'list.down',
        keys: 'j',
        labelKey: 'hotkeys.move',
        handler: enterRows,
    },
    {
        id: 'list.up',
        keys: 'k',
        labelKey: 'hotkeys.move',
        handler: enterRows,
    },
    {
        id: 'list.focus_rows',
        keys: 'alt+arrowdown',
        labelKey: 'hotkeys.focus_rows',
        allowInInput: true,
        handler: () => {
            focusListEntryRow();
        },
    },
    {
        id: 'list.open',
        keys: 'enter',
        labelKey: 'hotkeys.open',
        display: true,
        handler: noop,
    },
    {
        id: 'list.select',
        keys: 'space',
        labelKey: 'hotkeys.select',
        display: true,
        handler: noop,
    },
    {
        id: 'list.extend',
        keys: 'shift+arrowdown',
        labelKey: 'hotkeys.extend',
        display: true,
        handler: noop,
    },
    {
        id: 'list.prev',
        keys: '[',
        labelKey: 'hotkeys.prev_page',
        display: true,
        handler: noop,
    },
    {
        id: 'list.next',
        keys: ']',
        labelKey: 'hotkeys.next_page',
        display: true,
        handler: noop,
    },
    {
        id: 'list.clear',
        keys: 'escape',
        labelKey: 'hotkeys.clear',
        display: true,
        handler: noop,
    },
];

export function DataTable<Row extends Record<string, unknown>>({
    entity,
    rows,
    columns,
    meta,
    getRowId,
    rowHref,
    handlers,
    bulkActions = [],
    sort,
    onSort,
    onPage,
    loading = false,
    empty,
    selectable = true,
    caption,
    totals,
    onPerPage,
    perPageOptions = PER_PAGE_OPTIONS,
    chooser = 'inline',
    visibility: controlledVisibility,
    onVisibilityChange,
    fill = false,
}: Props<Row>) {
    const { t } = useT();
    const digits = useDigits();
    const mobile = useIsMobile();
    const [nav, setNav] = useState<NavState>(INITIAL_NAV);
    const [ownVisibility, setOwnVisibility] = useState<ColumnVisibility>(() =>
        initialVisibility(columns),
    );
    const visibility = controlledVisibility ?? ownVisibility;
    const setVisibility = (
        next: ColumnVisibility | ((old: ColumnVisibility) => ColumnVisibility),
    ) => {
        const value = typeof next === 'function' ? next(visibility) : next;
        if (onVisibilityChange) {
            onVisibilityChange(value);
        } else {
            setOwnVisibility(value);
        }
    };
    const [selection, setSelection] = useState<RowSelectionState>({});
    const rowRefs = useRef<(HTMLElement | null)[]>([]);
    useHotkeys('list', LIST_KEYS);

    // A new page / sort / filter yields new rows: keyboard focus and selection restart.
    const rowKey = rows.map(getRowId).join('|');
    const [seenRowKey, setSeenRowKey] = useState(rowKey);
    if (seenRowKey !== rowKey) {
        setSeenRowKey(rowKey);
        setNav(INITIAL_NAV);
        setSelection({});
    }

    const visibleColumns = columns.filter((c) => visibility[c.id] !== false);
    const selectedRows = rows.filter((r) => selection[getRowId(r)] === true);

    const syncSelection = (indexes: ReadonlySet<number>) => {
        const next: RowSelectionState = {};
        indexes.forEach((i) => {
            const row = rows[i];
            if (row) {
                next[getRowId(row)] = true;
            }
        });
        setSelection(next);
    };

    const focusRow = (index: number) => rowRefs.current[index]?.focus();

    const onKeyDown = (event: KeyboardEvent<HTMLElement>) => {
        const target = event.target as HTMLElement;
        // Never steal keys from controls inside a row (checkbox, inputs, buttons).
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) {
            return;
        }
        const result = reduceNavKey(nav, event, rows.length);
        if (!result.handled && result.effect === null) {
            return;
        }
        event.preventDefault();
        setNav(result.state);
        if (result.state.selected !== nav.selected) {
            syncSelection(result.state.selected);
        }
        if (result.state.active !== nav.active) {
            focusRow(result.state.active);
        }
        const activeRow = rows[result.state.active];
        switch (result.effect) {
            case 'open':
            case 'open_new_tab':
                {
                    const href = activeRow ? rowHref?.(activeRow) : undefined;
                    if (href) {
                        navigateTo(href, {
                            newTab: result.effect === 'open_new_tab',
                        });
                    }
                }
                break;
            case 'prev_page':
                if (meta.page > 1) {
                    onPage(meta.page - 1);
                }
                break;
            case 'next_page':
                if (meta.page < meta.lastPage) {
                    onPage(meta.page + 1);
                }
                break;
            default:
        }
    };

    const from = meta.total === 0 ? 0 : (meta.page - 1) * meta.perPage + 1;
    const to = Math.min(meta.total, meta.page * meta.perPage);

    const sortState = (
        c: DataColumn<Row>,
    ): 'ascending' | 'descending' | 'none' | undefined => {
        if (!c.sortKey) {
            return undefined;
        }
        if (sort === c.sortKey) {
            return 'ascending';
        }

        return sort === `-${c.sortKey}` ? 'descending' : 'none';
    };

    const rowProps = (row: Row, index: number) => ({
        ref: (el: HTMLElement | null) => {
            rowRefs.current[index] = el;
        },
        tabIndex:
            nav.active === index || (nav.active < 0 && index === 0) ? 0 : -1,
        'data-list-row': '',
        'data-selected': selection[getRowId(row)] === true ? '' : undefined,
        onFocus: () =>
            setNav((s) => (s.active === index ? s : { ...s, active: index })),
        onDoubleClick: () => {
            const href = rowHref?.(row);
            if (href) {
                navigateTo(href);
            }
        },
    });

    const clearSelection = () => {
        setSelection({});
        setNav((s) => ({ ...s, selected: new Set() }));
    };

    if (!loading && rows.length === 0) {
        return (
            <div
                className={cn(
                    'rounded-xl border bg-surface shadow-xs',
                    fill && 'min-h-0 flex-1',
                )}
            >
                {empty ?? (
                    <EmptyState
                        icon={SearchX}
                        title={t('table.empty')}
                        description={t('table.empty_hint')}
                    />
                )}
            </div>
        );
    }

    // Mockup bulk bar: raised, primary border, count bubble, primary actions.
    const bulkBar =
        selectedRows.length > 0 ? (
            <div
                role="toolbar"
                aria-label={t('table.bulk')}
                className="flex shrink-0 flex-wrap items-center justify-between gap-3 rounded-xl border border-primary bg-surface-raised px-4 py-2.5 text-xs shadow-2xl"
            >
                <div className="flex items-center gap-3">
                    <span className="flex size-6 items-center justify-center rounded-full bg-primary font-mono text-[11px] font-bold text-primary-foreground">
                        {digits.n(selectedRows.length)}
                    </span>
                    <span className="font-semibold text-foreground">
                        {t('table.selected', {
                            count: selectedRows.length,
                            n: digits.n(selectedRows.length),
                        })}
                    </span>
                    <button
                        type="button"
                        className="ml-1 text-[11px] text-muted-foreground hover:text-destructive hover:underline [@media(pointer:coarse)]:min-h-11"
                        onClick={clearSelection}
                    >
                        <X className="mr-1 inline size-3" aria-hidden="true" />
                        {t('table.clear_selection')}
                    </button>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {bulkActions.map((a) => (
                        <Button
                            key={a.id}
                            type="button"
                            size="sm"
                            variant={a.destructive ? 'destructive' : 'default'}
                            onClick={() => a.run(selectedRows)}
                        >
                            {a.icon ? <a.icon aria-hidden="true" /> : null}
                            {t(a.labelKey)}
                        </Button>
                    ))}
                </div>
            </div>
        ) : null;

    const pageButton =
        'flex size-7 items-center justify-center rounded-lg border bg-surface-sunken text-foreground transition-colors hover:bg-surface disabled:pointer-events-none disabled:opacity-30 [@media(pointer:coarse)]:size-11';

    // Mockup footer: "Rows per page" select left; range, ‹ page / pages › right.
    const pager = (
        <div
            className={cn(
                'flex shrink-0 flex-wrap items-center justify-between gap-2 px-3 py-2.5 text-xs select-none',
                !mobile && 'border-t bg-surface',
            )}
        >
            <div className="flex items-center gap-2">
                {onPerPage ? (
                    <>
                        <label
                            htmlFor={`${entity}-per-page`}
                            className="text-[11px] text-muted-foreground"
                        >
                            {t('table.rows_per_page')}
                        </label>
                        <select
                            id={`${entity}-per-page`}
                            value={meta.perPage}
                            onChange={(e) => onPerPage(Number(e.target.value))}
                            className="h-7 cursor-pointer rounded-lg border bg-surface-sunken px-2 text-xs font-medium text-foreground [@media(pointer:coarse)]:min-h-11"
                        >
                            {perPageOptions.map((n) => (
                                <option key={n} value={n}>
                                    {digits.n(n)}
                                </option>
                            ))}
                        </select>
                    </>
                ) : null}
            </div>
            <div className="flex items-center gap-3">
                <span className="num font-mono text-[11px] text-muted-foreground">
                    {t('table.showing', {
                        from: digits.n(from),
                        to: digits.n(to),
                        total: digits.n(meta.total),
                    })}
                </span>
                <div className="flex items-center gap-1">
                    <button
                        type="button"
                        className={pageButton}
                        aria-label={t('table.prev')}
                        disabled={meta.page <= 1}
                        onClick={() => onPage(meta.page - 1)}
                    >
                        <ChevronLeft className="size-3.5" aria-hidden="true" />
                    </button>
                    <span className="num px-1 font-mono text-[11px] font-semibold text-foreground">
                        {digits.n(meta.page)} /{' '}
                        {digits.n(Math.max(1, meta.lastPage))}
                    </span>
                    <button
                        type="button"
                        className={pageButton}
                        aria-label={t('table.next')}
                        disabled={meta.page >= meta.lastPage}
                        onClick={() => onPage(meta.page + 1)}
                    >
                        <ChevronRight className="size-3.5" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </div>
    );

    if (mobile) {
        const titleColumn =
            columns.find((c) => c.mobile === 'title') ?? columns[0];

        return (
            <div className="flex flex-col gap-3">
                {bulkBar}
                <ul
                    aria-label={caption}
                    data-list-rows=""
                    className="flex flex-col gap-2"
                >
                    {loading
                        ? Array.from({ length: SKELETON_ROWS }, (_, i) => (
                              <li key={i}>
                                  <Skeleton className="h-20 w-full" />
                              </li>
                          ))
                        : rows.map((row, index) => (
                              <li key={getRowId(row)}>
                                  <RowContextMenu
                                      entity={entity}
                                      row={row}
                                      href={rowHref?.(row)}
                                      handlers={handlers}
                                      selection={selectedRows}
                                  >
                                      <article
                                          {...rowProps(row, index)}
                                          className="flex flex-col gap-1 rounded-xl border bg-surface p-3 text-sm data-selected:bg-primary-soft"
                                      >
                                          {titleColumn ? (
                                              <div className="font-semibold">
                                                  {titleColumn.cell(row)}
                                              </div>
                                          ) : null}
                                          {visibleColumns
                                              .filter(
                                                  (c) =>
                                                      c !== titleColumn &&
                                                      c.mobile !== 'hidden',
                                              )
                                              .map((c) => (
                                                  <div
                                                      key={c.id}
                                                      className="flex flex-wrap justify-between gap-x-3"
                                                  >
                                                      <span className="text-muted-foreground">
                                                          {c.header ??
                                                              t(c.headerKey)}
                                                      </span>
                                                      <span
                                                          className={cn(
                                                              'ml-auto max-w-full min-w-0',
                                                              c.numeric &&
                                                                  'num text-right',
                                                          )}
                                                      >
                                                          {c.cell(row)}
                                                      </span>
                                                  </div>
                                              ))}
                                      </article>
                                  </RowContextMenu>
                              </li>
                          ))}
                </ul>
                {pager}
            </div>
        );
    }

    const colSpan = visibleColumns.length + (selectable ? 1 : 0);
    const cell = 'px-3 py-cell-y';

    return (
        <div className={cn('flex flex-col gap-3', fill && 'min-h-0 flex-1')}>
            {chooser === 'inline' ? (
                <div className="flex items-center justify-end">
                    <ColumnChooser
                        columns={columns}
                        visibility={visibility}
                        onChange={setVisibility}
                    />
                </div>
            ) : null}
            {/* Mockup table card: rounded-xl, surface, the body scrolls inside, footer pinned. */}
            <div
                className={cn(
                    'flex flex-col overflow-hidden rounded-xl border bg-surface shadow-xs',
                    fill && 'min-h-0 flex-1',
                )}
            >
                <div
                    className={cn(
                        'overflow-x-auto',
                        fill && 'min-h-0 flex-1 overflow-y-auto',
                    )}
                >
                    {/* Keyboard grid pattern: the rows (tabindex roving) receive focus, key events bubble here. */}
                    {/* oxlint-disable-next-line jsx-a11y/no-noninteractive-element-interactions */}
                    <table
                        aria-label={caption}
                        aria-busy={loading}
                        className="w-full border-collapse text-left"
                        style={{ fontSize: 'var(--table-font)' }}
                        data-list-rows=""
                        onKeyDown={onKeyDown}
                    >
                        <caption className="sr-only">{caption}</caption>
                        <thead className="sticky top-0 z-10 border-b bg-surface-sunken text-[11px] font-semibold text-muted-foreground select-none">
                            <tr>
                                {selectable ? (
                                    <th scope="col" className={cn(cell, 'w-8')}>
                                        <Checkbox
                                            className="align-middle"
                                            aria-label={t('table.select_all')}
                                            checked={
                                                rows.length > 0 &&
                                                selectedRows.length ===
                                                    rows.length
                                                    ? true
                                                    : selectedRows.length > 0
                                                      ? 'indeterminate'
                                                      : false
                                            }
                                            onCheckedChange={(checked) => {
                                                const next: RowSelectionState =
                                                    {};
                                                if (checked === true) {
                                                    rows.forEach(
                                                        (r) =>
                                                            (next[getRowId(r)] =
                                                                true),
                                                    );
                                                }
                                                setSelection(next);
                                            }}
                                        />
                                    </th>
                                ) : null}
                                {visibleColumns.map((c) => (
                                    <th
                                        key={c.id}
                                        scope="col"
                                        aria-sort={sortState(c)}
                                        className={cn(
                                            cell,
                                            'font-semibold',
                                            c.numeric && 'text-right',
                                            // Centred (quantity) headers may wrap, as the mockup's "Stock (Main)".
                                            c.align === 'center'
                                                ? 'text-center'
                                                : 'whitespace-nowrap',
                                            c.sortKey &&
                                                'hover:bg-surface hover:text-foreground',
                                        )}
                                    >
                                        {c.sortKey ? (
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1 rounded-sm [@media(pointer:coarse)]:min-h-11"
                                                onClick={() =>
                                                    onSort(c.sortKey as string)
                                                }
                                            >
                                                <span>
                                                    {c.header ?? t(c.headerKey)}
                                                </span>
                                                <span
                                                    aria-hidden="true"
                                                    className="text-[10px]"
                                                >
                                                    {sort === c.sortKey ? (
                                                        '▲'
                                                    ) : sort ===
                                                      `-${c.sortKey}` ? (
                                                        '▼'
                                                    ) : (
                                                        <span className="font-mono text-muted-foreground/40">
                                                            ↕
                                                        </span>
                                                    )}
                                                </span>
                                            </button>
                                        ) : (
                                            (c.header ?? t(c.headerKey))
                                        )}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {loading
                                ? Array.from(
                                      { length: SKELETON_ROWS },
                                      (_, i) => (
                                          <tr key={i}>
                                              <td
                                                  colSpan={colSpan}
                                                  className="px-3 py-3"
                                              >
                                                  <Skeleton className="h-4 w-full" />
                                              </td>
                                          </tr>
                                      ),
                                  )
                                : rows.map((row, index) => {
                                      const id = getRowId(row);
                                      const selected = selection[id] === true;

                                      return (
                                          <RowContextMenu
                                              key={id}
                                              entity={entity}
                                              row={row}
                                              href={rowHref?.(row)}
                                              handlers={handlers}
                                              selection={selectedRows}
                                          >
                                              <tr
                                                  {...rowProps(row, index)}
                                                  className={cn(
                                                      'transition-colors hover:bg-surface-sunken focus-visible:bg-surface-sunken focus-visible:ring-1 focus-visible:ring-primary focus-visible:outline-none focus-visible:ring-inset',
                                                      selected &&
                                                          'bg-primary-soft/25 hover:bg-primary-soft/40',
                                                  )}
                                                  style={{
                                                      height: 'var(--row-h)',
                                                  }}
                                              >
                                                  {selectable ? (
                                                      <td
                                                          className={cn(
                                                              cell,
                                                              'w-8',
                                                          )}
                                                      >
                                                          <Checkbox
                                                              className="align-middle"
                                                              aria-label={t(
                                                                  'table.select_row',
                                                              )}
                                                              tabIndex={-1}
                                                              checked={selected}
                                                              onCheckedChange={(
                                                                  checked,
                                                              ) =>
                                                                  setSelection(
                                                                      (s) => {
                                                                          const next =
                                                                              {
                                                                                  ...s,
                                                                              };
                                                                          if (
                                                                              checked ===
                                                                              true
                                                                          ) {
                                                                              next[
                                                                                  id
                                                                              ] =
                                                                                  true;
                                                                          } else {
                                                                              delete next[
                                                                                  id
                                                                              ];
                                                                          }

                                                                          return next;
                                                                      },
                                                                  )
                                                              }
                                                          />
                                                      </td>
                                                  ) : null}
                                                  {visibleColumns.map((c) => (
                                                      <td
                                                          key={c.id}
                                                          className={cn(
                                                              cell,
                                                              c.numeric &&
                                                                  'num text-right',
                                                              c.align ===
                                                                  'center' &&
                                                                  'text-center',
                                                          )}
                                                      >
                                                          {c.cell(row)}
                                                      </td>
                                                  ))}
                                              </tr>
                                          </RowContextMenu>
                                      );
                                  })}
                        </tbody>
                        {totals && !loading ? (
                            <tfoot className="sticky bottom-0 z-10 border-t-2 bg-surface-sunken text-xs font-bold select-none">
                                <tr>
                                    {selectable ? (
                                        <td
                                            className={cell}
                                            aria-hidden="true"
                                        />
                                    ) : null}
                                    {visibleColumns.map((c, i) => (
                                        <td
                                            key={c.id}
                                            className={cn(
                                                cell,
                                                c.numeric && 'num text-right',
                                                c.align === 'center' &&
                                                    'text-center',
                                                i === 0 &&
                                                    totals.cells[c.id] ===
                                                        undefined &&
                                                    'font-semibold whitespace-nowrap',
                                            )}
                                        >
                                            {totals.cells[c.id] ??
                                                (i === 0
                                                    ? (totals.label ??
                                                      t('table.total', {
                                                          n: digits.n(
                                                              meta.total,
                                                          ),
                                                      }))
                                                    : null)}
                                        </td>
                                    ))}
                                </tr>
                            </tfoot>
                        ) : null}
                    </table>
                </div>
                {pager}
            </div>
            {bulkBar}
        </div>
    );
}
