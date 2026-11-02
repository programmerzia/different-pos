import {
    columnVisibilityFeature,
    rowSelectionFeature,
    tableFeatures,
    useTable,
    type ColumnDef,
    type RowSelectionState,
} from '@tanstack/react-table';
import {
    ArrowDown,
    ArrowUp,
    ChevronLeft,
    ChevronRight,
    Columns3,
    type LucideIcon,
    SearchX,
    X,
} from 'lucide-react';
import {
    type KeyboardEvent,
    type ReactNode,
    useMemo,
    useRef,
    useState,
} from 'react';
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
import { INITIAL_NAV, reduceNavKey, type NavState } from './table-nav';

const features = tableFeatures({
    columnVisibilityFeature,
    rowSelectionFeature,
});

export type DataColumn<Row> = {
    id: string;
    /** i18n key for the header. */
    headerKey: string;
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
    rowHref?: (row: Row) => string;
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
};

const SKELETON_ROWS = 8;

const noop = () => undefined;
/** Listed in the `?` overlay (the table handles the keys itself, on the focused row). */
const LIST_KEYS: HotkeySpec[] = [
    {
        id: 'list.down',
        keys: 'j',
        labelKey: 'hotkeys.move',
        display: true,
        handler: noop,
    },
    {
        id: 'list.up',
        keys: 'k',
        labelKey: 'hotkeys.move',
        display: true,
        handler: noop,
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
}: Props<Row>) {
    const { t } = useT();
    const digits = useDigits();
    const mobile = useIsMobile();
    const [nav, setNav] = useState<NavState>(INITIAL_NAV);
    const [visibility, setVisibility] = useState<Record<string, boolean>>(() =>
        Object.fromEntries(
            columns.filter((c) => c.hidden).map((c) => [c.id, false]),
        ),
    );
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

    const columnDefs = useMemo<ColumnDef<typeof features, Row>[]>(
        () =>
            columns.map((c) => ({
                id: c.id,
                header: c.headerKey,
                accessorFn: () => null,
                enableHiding: !c.required,
            })),
        [columns],
    );

    const table = useTable({
        features,
        columns: columnDefs,
        data: rows,
        getRowId,
        state: { columnVisibility: visibility, rowSelection: selection },
        onColumnVisibilityChange: setVisibility,
        onRowSelectionChange: setSelection,
        enableRowSelection: selectable,
    });

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
                if (activeRow && rowHref) {
                    navigateTo(rowHref(activeRow), {
                        newTab: result.effect === 'open_new_tab',
                    });
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
        'data-selected': selection[getRowId(row)] === true ? '' : undefined,
        onFocus: () =>
            setNav((s) => (s.active === index ? s : { ...s, active: index })),
        onDoubleClick: () => rowHref && navigateTo(rowHref(row)),
    });

    const clearSelection = () => {
        setSelection({});
        setNav((s) => ({ ...s, selected: new Set() }));
    };

    if (!loading && rows.length === 0) {
        return (
            <div className="rounded-lg border bg-card">
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

    const bulkBar =
        selectedRows.length > 0 ? (
            <div
                role="toolbar"
                aria-label={t('table.bulk')}
                className="flex flex-wrap items-center gap-2 rounded-lg border bg-primary-soft px-3 py-2 text-sm"
            >
                <span className="font-medium">
                    {t('table.selected', {
                        count: selectedRows.length,
                        n: digits.n(selectedRows.length),
                    })}
                </span>
                {bulkActions.map((a) => (
                    <Button
                        key={a.id}
                        type="button"
                        size="sm"
                        variant={a.destructive ? 'destructive' : 'outline'}
                        className="min-h-11 md:min-h-8"
                        onClick={() => a.run(selectedRows)}
                    >
                        {a.icon ? <a.icon aria-hidden="true" /> : null}
                        {t(a.labelKey)}
                    </Button>
                ))}
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    className="ml-auto min-h-11 md:min-h-8"
                    onClick={clearSelection}
                >
                    <X aria-hidden="true" />
                    {t('table.clear_selection')}
                </Button>
            </div>
        ) : null;

    const pager = (
        <div className="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground">
            <span className="num">
                {t('table.showing', {
                    from: digits.n(from),
                    to: digits.n(to),
                    total: digits.n(meta.total),
                })}
            </span>
            <div className="flex items-center gap-1">
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-11 md:size-9"
                    aria-label={t('table.prev')}
                    disabled={meta.page <= 1}
                    onClick={() => onPage(meta.page - 1)}
                >
                    <ChevronLeft aria-hidden="true" />
                </Button>
                <span className="num px-2">
                    {digits.n(meta.page)} /{' '}
                    {digits.n(Math.max(1, meta.lastPage))}
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-11 md:size-9"
                    aria-label={t('table.next')}
                    disabled={meta.page >= meta.lastPage}
                    onClick={() => onPage(meta.page + 1)}
                >
                    <ChevronRight aria-hidden="true" />
                </Button>
            </div>
        </div>
    );

    const chooser = (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="min-h-11 md:min-h-8"
                >
                    <Columns3 aria-hidden="true" />
                    {t('table.columns')}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {columns
                    .filter((c) => !c.required)
                    .map((c) => (
                        <DropdownMenuCheckboxItem
                            key={c.id}
                            checked={visibility[c.id] !== false}
                            onCheckedChange={(checked) =>
                                table.getColumn(c.id)?.toggleVisibility(checked)
                            }
                            onSelect={(e) => e.preventDefault()}
                        >
                            {t(c.headerKey)}
                        </DropdownMenuCheckboxItem>
                    ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );

    if (mobile) {
        const titleColumn =
            columns.find((c) => c.mobile === 'title') ?? columns[0];

        return (
            <div className="flex flex-col gap-3">
                {bulkBar}
                <ul aria-label={caption} className="flex flex-col gap-2">
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
                                          className="flex flex-col gap-1 rounded-lg border bg-card p-3 data-selected:bg-primary-soft"
                                      >
                                          {titleColumn ? (
                                              <div className="font-medium">
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
                                                      className="flex justify-between gap-3 text-sm"
                                                  >
                                                      <span className="text-muted-foreground">
                                                          {t(c.headerKey)}
                                                      </span>
                                                      <span
                                                          className={cn(
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

    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-center justify-end">{chooser}</div>
            {bulkBar}
            <div className="overflow-x-auto rounded-lg border bg-card">
                {/* Keyboard grid pattern: the rows (tabindex roving) receive focus, key events bubble here. */}
                {/* oxlint-disable-next-line jsx-a11y/no-noninteractive-element-interactions */}
                <table
                    aria-label={caption}
                    aria-busy={loading}
                    className="w-full border-collapse text-left"
                    style={{ fontSize: 'var(--table-font)' }}
                    onKeyDown={onKeyDown}
                >
                    <caption className="sr-only">{caption}</caption>
                    <thead className="sticky top-0 bg-muted text-muted-foreground">
                        <tr>
                            {selectable ? (
                                <th scope="col" className="w-10 px-3">
                                    <Checkbox
                                        aria-label={t('table.select_all')}
                                        checked={
                                            rows.length > 0 &&
                                            selectedRows.length === rows.length
                                                ? true
                                                : selectedRows.length > 0
                                                  ? 'indeterminate'
                                                  : false
                                        }
                                        onCheckedChange={(checked) => {
                                            const next: RowSelectionState = {};
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
                                        'px-3 font-medium',
                                        c.numeric && 'text-right',
                                    )}
                                    style={{ height: 'var(--row-h)' }}
                                >
                                    {c.sortKey ? (
                                        <button
                                            type="button"
                                            className="inline-flex min-h-11 items-center gap-1 rounded-sm hover:text-foreground md:min-h-0"
                                            onClick={() =>
                                                onSort(c.sortKey as string)
                                            }
                                        >
                                            {t(c.headerKey)}
                                            {sort === c.sortKey ? (
                                                <ArrowUp
                                                    className="size-3.5"
                                                    aria-hidden="true"
                                                />
                                            ) : sort === `-${c.sortKey}` ? (
                                                <ArrowDown
                                                    className="size-3.5"
                                                    aria-hidden="true"
                                                />
                                            ) : null}
                                        </button>
                                    ) : (
                                        t(c.headerKey)
                                    )}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {loading
                            ? Array.from({ length: SKELETON_ROWS }, (_, i) => (
                                  <tr key={i} className="border-t">
                                      <td
                                          colSpan={
                                              visibleColumns.length +
                                              (selectable ? 1 : 0)
                                          }
                                          className="px-3 py-1.5"
                                      >
                                          <Skeleton className="h-5 w-full" />
                                      </td>
                                  </tr>
                              ))
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
                                                  'border-t hover:bg-muted focus-visible:outline-offset-[-2px] data-selected:bg-primary-soft',
                                                  selected && 'bg-primary-soft',
                                              )}
                                              style={{ height: 'var(--row-h)' }}
                                          >
                                              {selectable ? (
                                                  <td className="px-3">
                                                      <Checkbox
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
                                                          'px-3',
                                                          c.numeric &&
                                                              'num text-right',
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
                </table>
            </div>
            {pager}
        </div>
    );
}
