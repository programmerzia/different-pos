import { Head, router } from '@inertiajs/react';
import { Banknote, HandCoins, Wallet } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    ColumnChooser,
    DataTable,
    useColumnVisibility,
    type DataColumn,
    type TableMeta,
} from '@/components/data-table/data-table';
import {
    buildTableQuery as buildQuery,
    useServerTable,
} from '@/components/data-table/server-table';
import { EmptyState } from '@/components/empty-state';
import { KpiTile, type KpiTone } from '@/components/kpi-tile';
import { Kbd } from '@/components/kbd';
import {
    ExportMenu,
    Page,
    PageHeader,
    PrintButton,
} from '@/components/page-header';
import {
    CollectDialog,
    type CollectInvoice,
} from '@/components/sales/collect-dialog';
import { FiltersDialog } from '@/components/sales/filters-dialog';
import { creditUse, rowOpenHandlers } from '@/components/sales/list-tools';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { actions } from '@/command/actions';
import { moneyToDecimalString } from '@/engine/money';
import { useCan } from '@/hooks/use-can';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { BUCKETS, type Bucket } from '@/lib/dues';
import { downloadRowsCsv, type CsvColumn } from '@/lib/rows-csv';
import { salesUrl, type CustomerSummary, type MoneyMethod } from '@/lib/sales';
import { cn } from '@/lib/utils';

type DueCustomer = CustomerSummary & {
    name_bn?: string | null;
    balance: number;
    credit_limit: number | null;
};

type Row = {
    customer: DueCustomer | null;
    invoices: number;
    total_due: number;
    oldest_at: string | null;
    last_sale_at: string | null;
    buckets: Record<Bucket, number>;
};

type CollectData = {
    customer: DueCustomer;
    invoices: CollectInvoice[];
    methods: MoneyMethod[];
    can: { collect: boolean };
} | null;

type Props = {
    rows: Row[];
    meta: TableMeta;
    filters: Record<string, string>;
    totals: {
        customers: number;
        invoices: number;
        total_due: number;
        buckets: Record<Bucket, number>;
        debtors: Record<Bucket, number>;
    };
    groups: { id: number; name: string }[];
    collect?: CollectData;
};

/** Mockup aging strip: 0–30 success, 31–60 warning, 61–90 and 90+ danger (coloured border). */
const BUCKET_TONE: Record<Bucket, KpiTone> = {
    d0_30: 'success',
    d31_60: 'warning',
    d61_90: 'danger',
    d90_plus: 'danger',
};
const GROUP_CHIPS_MAX = 4;
const MORE_FILTERS = ['bucket', 'credit'] as const;

let dueActionsRegistered = false;
function registerDueActions(): void {
    if (dueActionsRegistered) {
        return;
    }
    dueActionsRegistered = true;
    actions.register<Row>({
        id: 'due.collect',
        entity: 'due',
        labelKey: 'dues.collect',
        icon: HandCoins,
        keys: 'mod+d',
        permission: 'payments.collect',
        requires: 'handler',
        run: ({ row, handlers }) => handlers?.['due.collect']?.(row),
    });
}
registerDueActions();

export default function DuesIndex({
    rows,
    meta,
    filters,
    totals,
    groups,
    collect,
}: Props) {
    const { t } = useT();
    const digits = useDigits();
    const can = useCan();
    const table = useServerTable();
    const [moreOpen, setMoreOpen] = useState(false);
    const [collectFor, setCollectFor] = useState<string | null>(null);

    const startCollect = (row: Row | undefined) => {
        const uuid = row?.customer?.uuid;
        if (!uuid || !can('payments.collect')) {
            return;
        }
        router.reload({
            only: ['collect'],
            data: { collect: uuid },
            onSuccess: () => setCollectFor(uuid),
        });
    };
    const focusedRow = (): Row | undefined => {
        const tr = document.activeElement?.closest('tbody > tr');
        const index =
            tr?.parentElement !== null && tr?.parentElement !== undefined
                ? Array.from(tr.parentElement.children).indexOf(tr)
                : -1;

        return rows[index] ?? rows[0];
    };

    const csvColumns: CsvColumn<Row>[] = [
        {
            header: t('dues.col_customer_no'),
            value: (r) => r.customer?.customer_no ?? '',
        },
        { header: t('dues.col_name'), value: (r) => r.customer?.name ?? '' },
        { header: t('dues.col_phone'), value: (r) => r.customer?.phone ?? '' },
        {
            header: t('dues.col_total'),
            value: (r) => moneyToDecimalString(r.total_due),
        },
        {
            header: t('dues.col_limit'),
            value: (r) =>
                r.customer?.credit_limit !== null &&
                r.customer?.credit_limit !== undefined
                    ? moneyToDecimalString(r.customer.credit_limit)
                    : '',
        },
        { header: t('dues.col_last'), value: (r) => r.last_sale_at ?? '' },
    ];
    const exportRows = () => downloadRowsCsv('dues.csv', csvColumns, rows);

    useHotkeys('list', [
        {
            id: 'dues.search',
            keys: '/',
            labelKey: 'hotkeys.search',
            handler: (e) => {
                e.preventDefault();
                document.getElementById('page-search')?.focus();
            },
        },
        {
            id: 'dues.collect',
            keys: 'mod+d',
            labelKey: 'dues.collect',
            handler: (e) => {
                e.preventDefault();
                startCollect(focusedRow());
            },
        },
        {
            id: 'dues.export',
            keys: 'shift+e',
            labelKey: 'dues.export_csv',
            handler: exportRows,
        },
        {
            id: 'dues.more_filters',
            keys: 'shift+f',
            labelKey: 'table.more_filters',
            handler: () => setMoreOpen(true),
        },
    ]);

    const columns = useMemo<DataColumn<Row>[]>(
        () => [
            {
                id: 'customer_no',
                headerKey: 'dues.col_customer_no',
                cell: (r) => (
                    <span className="num font-mono font-medium text-primary">
                        {digits.n(r.customer?.customer_no ?? '—')}
                    </span>
                ),
            },
            {
                id: 'customer',
                headerKey: 'dues.col_name',
                required: true,
                mobile: 'title',
                cell: (r) => (
                    <div>
                        <span className="font-semibold text-foreground">
                            {r.customer?.name ?? '—'}
                        </span>
                        {r.customer?.name_bn || r.customer?.shop_name ? (
                            <span className="text-muted-foreground">
                                {' '}
                                ({r.customer.name_bn || r.customer.shop_name})
                            </span>
                        ) : null}
                    </div>
                ),
            },
            {
                id: 'phone',
                headerKey: 'dues.col_phone',
                cell: (r) => (
                    <span className="num font-mono text-muted-foreground">
                        {digits.n(r.customer?.phone ?? '')}
                    </span>
                ),
            },
            {
                id: 'meter',
                headerKey: 'dues.col_meter',
                cell: (r) => {
                    const balance = Math.max(0, r.customer?.balance ?? 0);
                    const limit = r.customer?.credit_limit ?? null;
                    const pct = creditUse(balance, limit);

                    return (
                        <div className="w-44 space-y-1">
                            <div className="num flex justify-between gap-2 font-mono text-[10px] text-muted-foreground">
                                <span>
                                    {t('dues.meter_used', {
                                        amount: digits.money(balance),
                                    })}
                                </span>
                                <span>
                                    {limit !== null
                                        ? t('dues.meter_limit', {
                                              amount: digits.money(limit),
                                          })
                                        : t('dues.no_limit')}
                                </span>
                            </div>
                            <div
                                aria-hidden="true"
                                className="h-1.5 w-full overflow-hidden rounded-full bg-surface-sunken"
                            >
                                <div
                                    style={{ width: `${pct ?? 0}%` }}
                                    className={cn(
                                        'h-full',
                                        pct !== null && pct > 80
                                            ? 'bg-destructive'
                                            : pct !== null && pct > 50
                                              ? 'bg-warning'
                                              : 'bg-success',
                                    )}
                                />
                            </div>
                        </div>
                    );
                },
            },
            {
                id: 'invoices',
                headerKey: 'dues.col_invoices',
                sortKey: 'invoices',
                numeric: true,
                hidden: true,
                cell: (r) => digits.n(r.invoices),
            },
            ...BUCKETS.map((bucket): DataColumn<Row> => ({
                id: bucket,
                headerKey: `dues.bucket_${bucket}`,
                numeric: true,
                hidden: true,
                cell: (r) =>
                    r.buckets[bucket] ? digits.money(r.buckets[bucket]) : '—',
            })),
            {
                id: 'total_due',
                headerKey: 'dues.col_total',
                sortKey: 'total_due',
                numeric: true,
                cell: (r) => (
                    <span className="font-bold text-destructive">
                        {digits.money(r.total_due)}
                    </span>
                ),
            },
            {
                id: 'last',
                headerKey: 'dues.col_last',
                cell: (r) => (
                    <span className="num text-muted-foreground">
                        {digits.n((r.last_sale_at ?? '').slice(0, 10))}
                    </span>
                ),
            },
            {
                id: 'oldest',
                headerKey: 'dues.col_oldest',
                sortKey: 'oldest_at',
                hidden: true,
                cell: (r) => (
                    <span className="num text-muted-foreground">
                        {digits.n((r.oldest_at ?? '').slice(0, 10))}
                    </span>
                ),
            },
            {
                id: 'actions',
                headerKey: 'common.actions',
                required: true,
                mobile: 'hidden',
                cell: (r) =>
                    can('payments.collect') &&
                    r.customer &&
                    !r.customer.walk_in ? (
                        <span className="flex justify-end">
                            <Button
                                type="button"
                                size="sm"
                                className="font-semibold"
                                aria-label={t('dues.collect_from', {
                                    name: r.customer.name,
                                })}
                                onClick={() => startCollect(r)}
                            >
                                {t('dues.collect_row')}
                            </Button>
                        </span>
                    ) : null,
            },
        ],
        // startCollect reads the latest rows through the closure of this render.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [digits, t, can],
    );
    const columnState = useColumnVisibility(columns);

    const handlers = can('payments.collect')
        ? { 'due.collect': (r: Row) => startCollect(r) }
        : {};

    const groupOptions = groups.map((g) => ({
        value: String(g.id),
        label: g.name,
    }));
    const view =
        filters['bucket'] === 'd90_plus' && !filters['credit']
            ? 'critical'
            : filters['credit'] === 'over_limit' && !filters['bucket']
              ? 'over_limit'
              : '';
    const setView = (value: string) => {
        const next = {
            ...table.state,
            filters: {
                ...table.state.filters,
                bucket: value === 'critical' ? 'd90_plus' : '',
                credit: value === 'over_limit' ? 'over_limit' : '',
            },
            page: 1,
        };
        router.get(salesUrl.dues, buildQuery(next), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };
    const moreCount = MORE_FILTERS.filter((k) => filters[k]).length;
    const collectData =
        collect && collectFor === collect.customer.uuid ? collect : null;

    return (
        <>
            <Head title={t('dues.title')} />
            <Page fill>
                <div className="grid shrink-0 grid-cols-2 gap-3 lg:grid-cols-4">
                    {BUCKETS.map((bucket) => (
                        <KpiTile
                            key={bucket}
                            tone={BUCKET_TONE[bucket]}
                            label={t(`dues.tile_${bucket}`)}
                            value={digits.money(totals.buckets[bucket])}
                            footnote={t('dues.tile_debtors', {
                                n: digits.n(totals.debtors[bucket]),
                            })}
                            className={cn(
                                filters['bucket'] === bucket &&
                                    'ring-2 ring-primary',
                            )}
                            onClick={() =>
                                table.setFilter(
                                    'bucket',
                                    filters['bucket'] === bucket ? '' : bucket,
                                )
                            }
                        />
                    ))}
                </div>
                <PageHeader
                    title={t('dues.ledger_title')}
                    count={meta.total}
                    subtitle={t('dues.ledger_subtitle')}
                    actions={
                        <>
                            {can('payments.collect') && rows.length > 0 ? (
                                <Button
                                    type="button"
                                    onClick={() => startCollect(focusedRow())}
                                >
                                    <Banknote aria-hidden="true" />
                                    {t('dues.collect_due')}
                                    <Kbd keys="mod+d" onButton />
                                </Button>
                            ) : null}
                            <ColumnChooser
                                columns={columns}
                                visibility={columnState.visibility}
                                onChange={columnState.setVisibility}
                            />
                            <ExportMenu
                                items={[
                                    {
                                        id: 'csv',
                                        kind: 'csv',
                                        label: t('dues.export_csv'),
                                        onSelect: exportRows,
                                    },
                                ]}
                            />
                            <PrintButton />
                        </>
                    }
                />
                <TableToolbar
                    search={table.searchText}
                    onSearch={table.setSearch}
                    placeholderKey="dues.search"
                    view={{
                        value: view,
                        options: [
                            { value: '', label: t('dues.view_default') },
                            {
                                value: 'critical',
                                label: t('dues.view_critical'),
                            },
                            {
                                value: 'over_limit',
                                label: t('dues.view_over_limit'),
                            },
                        ],
                        onChange: setView,
                    }}
                    chips={
                        groups.length > 0 && groups.length <= GROUP_CHIPS_MAX
                            ? [
                                  {
                                      key: 'group',
                                      label: t('dues.group'),
                                      value: filters['group'] ?? '',
                                      options: [
                                          {
                                              value: '',
                                              label: t('dues.all_groups'),
                                          },
                                          ...groupOptions,
                                      ],
                                      onChange: (v) =>
                                          table.setFilter('group', v),
                                  },
                              ]
                            : []
                    }
                    filters={
                        groups.length > GROUP_CHIPS_MAX
                            ? [
                                  {
                                      key: 'group',
                                      labelKey: 'dues.all_groups',
                                      value: filters['group'] ?? '',
                                      options: groupOptions,
                                      onChange: (v: string) =>
                                          table.setFilter('group', v),
                                  },
                              ]
                            : []
                    }
                    moreFilters={{
                        count: moreCount,
                        onOpen: () => setMoreOpen(true),
                    }}
                />
                {/* Row click = collect (mockup); the table keeps Enter (drill-down) and Ctrl+D. */}
                <div
                    className="flex min-h-0 flex-1 flex-col"
                    {...{
                        onClick: rowOpenHandlers(rows, startCollect).onClick,
                    }}
                >
                    <DataTable<Row>
                        entity="due"
                        caption={t('dues.title')}
                        rows={rows}
                        columns={columns}
                        meta={meta}
                        getRowId={(r) => r.customer?.uuid ?? ''}
                        rowHref={(r) =>
                            r.customer
                                ? salesUrl.dueShow(r.customer.uuid)
                                : salesUrl.dues
                        }
                        handlers={handlers}
                        sort={table.state.sort}
                        onSort={table.toggleSort}
                        onPage={table.setPage}
                        onPerPage={table.setPerPage}
                        loading={table.loading}
                        chooser="none"
                        visibility={columnState.visibility}
                        onVisibilityChange={columnState.setVisibility}
                        fill
                        totals={{
                            cells: {
                                total_due: (
                                    <span className="text-destructive">
                                        {digits.money(totals.total_due)}
                                    </span>
                                ),
                                invoices: digits.n(totals.invoices),
                                ...Object.fromEntries(
                                    BUCKETS.map((b) => [
                                        b,
                                        digits.money(totals.buckets[b]),
                                    ]),
                                ),
                            },
                        }}
                        empty={
                            <EmptyState
                                icon={Wallet}
                                title={t('dues.empty')}
                                description={t('dues.empty_hint')}
                            />
                        }
                    />
                </div>
                <p className="flex shrink-0 items-center gap-2 text-[11px] text-muted-foreground">
                    <HandCoins className="size-3.5" aria-hidden="true" />
                    {t('dues.open_hint')}
                </p>
            </Page>
            {collectData && collectData.can.collect ? (
                <CollectDialog
                    key={collectData.customer.uuid}
                    open
                    onOpenChange={(open) => !open && setCollectFor(null)}
                    action={salesUrl.duePayments(collectData.customer.uuid)}
                    title={t('dues.collect_title')}
                    invoices={collectData.invoices}
                    methods={collectData.methods}
                    customer={{
                        name: collectData.customer.name,
                        customer_no: collectData.customer.customer_no,
                        balance: collectData.customer.balance,
                        credit_limit: collectData.customer.credit_limit,
                    }}
                />
            ) : null}
            <FiltersDialog
                open={moreOpen}
                onOpenChange={setMoreOpen}
                values={filters}
                onApply={(next) =>
                    router.get(
                        salesUrl.dues,
                        buildQuery({
                            ...table.state,
                            filters: { ...table.state.filters, ...next },
                            page: 1,
                        }),
                        {
                            preserveState: true,
                            preserveScroll: true,
                            replace: true,
                        },
                    )
                }
                fields={[
                    {
                        key: 'bucket',
                        label: t('dues.aging'),
                        options: [
                            { value: '', label: t('dues.all_buckets') },
                            ...BUCKETS.map((b) => ({
                                value: b,
                                label: t(`dues.tile_${b}`),
                            })),
                        ],
                    },
                    {
                        key: 'credit',
                        label: t('dues.credit_use'),
                        options: [
                            { value: '', label: t('dues.credit_all') },
                            {
                                value: 'near_limit',
                                label: t('dues.credit_near'),
                            },
                            {
                                value: 'over_limit',
                                label: t('dues.credit_over'),
                            },
                        ],
                    },
                ]}
            />
        </>
    );
}
