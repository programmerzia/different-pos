import { Head, router } from '@inertiajs/react';
import { Banknote, HandCoins, Send, Wallet } from 'lucide-react';
import { toast } from 'sonner';
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
import { WhatsAppReminderDialog } from '@/components/messaging/whatsapp-reminder-dialog';
import { creditUse, rowOpenHandlers } from '@/components/sales/list-tools';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { actions } from '@/command/actions';
import { useCan } from '@/hooks/use-can';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { BUCKETS, type Bucket } from '@/lib/dues';
import { DownloadError, downloadFile } from '@/lib/download';
import { exportItems, exportUrl, type ExportFormat } from '@/lib/export-items';
import { salesUrl, type CustomerSummary, type MoneyMethod } from '@/lib/sales';
import type { DirectRecipient } from '@/lib/whatsapp';
import type { MessagingProps } from '@/types/messaging';
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
    messaging: MessagingProps;
    collect?: CollectData;
};

/** The dialog's recipient for a dues row: the open due of the row (not the whole balance). */
function recipientOf(row: Row): DirectRecipient | null {
    return row.customer && !row.customer.walk_in
        ? {
              uuid: row.customer.uuid,
              name: row.customer.name,
              phone: row.customer.phone ?? null,
              due: row.total_due,
          }
        : null;
}

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
    actions.register<Row>({
        id: 'due.whatsapp',
        entity: 'due',
        labelKey: 'dues.whatsapp_reminder',
        icon: Send,
        keys: 'w',
        permission: 'sms.send',
        requires: 'handler',
        visible: (row) => !!row.customer?.phone && !row.customer.walk_in,
        run: ({ row, handlers }) => handlers?.['due.whatsapp']?.(row),
    });
}
registerDueActions();

export default function DuesIndex({
    rows,
    meta,
    filters,
    totals,
    groups,
    messaging,
    collect,
}: Props) {
    const { t } = useT();
    const digits = useDigits();
    const can = useCan();
    const table = useServerTable();
    const [moreOpen, setMoreOpen] = useState(false);
    const [collectFor, setCollectFor] = useState<string | null>(null);
    const [reminder, setReminder] = useState<{
        recipient: DirectRecipient;
        bulk: DirectRecipient[];
    } | null>(null);

    /** Opens the WhatsApp dialog for one row; Bulk reaches the selected rows, else every debtor on this page. */
    const startReminder = (
        row: Row | undefined,
        selection?: readonly Row[],
    ) => {
        const recipient = row ? recipientOf(row) : null;
        if (!recipient || !messaging.can_send) {
            return;
        }
        const pool = selection && selection.length > 1 ? selection : rows;
        const bulk = pool
            .map(recipientOf)
            .filter((r): r is DirectRecipient => r !== null && !!r.phone);
        setReminder({ recipient, bulk });
    };

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

    const exportAs = (format: ExportFormat) => {
        downloadFile(
            exportUrl('/app/dues', format, window.location.search),
        ).catch((error: unknown) =>
            toast.error(
                error instanceof DownloadError && error.status === 422
                    ? error.message
                    : t('table.export_failed'),
            ),
        );
    };

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
        ...(messaging.can_send
            ? [
                  {
                      id: 'dues.whatsapp',
                      keys: 'w',
                      labelKey: 'dues.whatsapp_reminder',
                      handler: (e: KeyboardEvent) => {
                          e.preventDefault();
                          startReminder(focusedRow());
                      },
                  },
              ]
            : []),
        {
            id: 'dues.export',
            keys: 'shift+e',
            labelKey: 'table.export_xlsx',
            handler: () => exportAs('xlsx'),
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
                    r.customer && !r.customer.walk_in ? (
                        <span className="flex justify-end gap-1.5">
                            {can('payments.collect') ? (
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
                            ) : null}
                            {messaging.can_send && r.customer.phone ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    className="bg-surface-sunken px-2 hover:border-primary"
                                    title={t('dues.whatsapp_reminder')}
                                    aria-label={`${t('dues.whatsapp_reminder')}: ${r.customer.name}`}
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        startReminder(r);
                                    }}
                                >
                                    <Send aria-hidden="true" />
                                </Button>
                            ) : null}
                        </span>
                    ) : null,
            },
        ],
        // startCollect / startReminder read the latest rows through the closure of this render.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [digits, t, can, messaging.can_send],
    );
    const columnState = useColumnVisibility(columns);

    const handlers = {
        ...(can('payments.collect')
            ? { 'due.collect': (r: Row) => startCollect(r) }
            : {}),
        ...(messaging.can_send
            ? {
                  'due.whatsapp': (r: Row, selection?: readonly Row[]) =>
                      startReminder(r, selection),
              }
            : {}),
    };

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
                            <ExportMenu items={exportItems(t, exportAs)} />
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
                        bulkActions={
                            messaging.can_send
                                ? [
                                      {
                                          id: 'due.whatsapp_bulk',
                                          labelKey: 'dues.whatsapp_bulk',
                                          icon: Send,
                                          run: (selected) =>
                                              startReminder(
                                                  selected[0],
                                                  selected,
                                              ),
                                      },
                                  ]
                                : []
                        }
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
            {reminder ? (
                <WhatsAppReminderDialog
                    key={reminder.recipient.uuid}
                    open
                    onOpenChange={(open) => !open && setReminder(null)}
                    messaging={messaging}
                    recipient={reminder.recipient}
                    bulk={reminder.bulk}
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
