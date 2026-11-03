import { Head, router } from '@inertiajs/react';
import { FileText, Plus, Truck } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import {
    ColumnChooser,
    DataTable,
    useColumnVisibility,
    type BulkAction,
    type DataColumn,
    type TableMeta,
} from '@/components/data-table/data-table';
import {
    buildTableQuery,
    useServerTable,
} from '@/components/data-table/server-table';
import { EmptyState } from '@/components/empty-state';
import {
    ExportMenu,
    Page,
    PageHeader,
    PrintButton,
} from '@/components/page-header';
import { PayDialog, type PaymentMethodOption } from '@/components/pay-dialog';
import { FiltersDialog } from '@/components/sales/filters-dialog';
import { rowOpenHandlers } from '@/components/sales/list-tools';
import { StatusBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { VendorForm } from '@/components/vendors/vendor-form';
import { moneyToDecimalString } from '@/engine/money';
import { useCan } from '@/hooks/use-can';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeyScope, useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { downloadRowsCsv, type CsvColumn } from '@/lib/rows-csv';

type Row = {
    id: number;
    uuid: string;
    name: string;
    company: string | null;
    phone: string | null;
    address: string | null;
    hub: string | null;
    balance: number;
    is_active: boolean;
    open_bills: number;
    last_order: string | null;
};

/** "Date:" presets, the last received order (server filter `ordered`). */
const RECENCY = ['', 'today', '7d', '30d', '90d', 'idle_90d'] as const;

export default function VendorsIndex({
    rows,
    meta,
    totals,
    hubs,
    paymentMethods,
    canPay,
}: {
    rows: Row[];
    meta: TableMeta;
    totals: { payable: number; open_bills: number };
    hubs: string[];
    paymentMethods: PaymentMethodOption[];
    canPay: boolean;
}) {
    const { t } = useT();
    const digits = useDigits();
    const can = useCan();
    const table = useServerTable();
    const filters = table.state.filters;
    const [toDelete, setToDelete] = useState<Row | null>(null);
    const [adding, setAdding] = useState(false);
    const [paying, setPaying] = useState<Row | null>(null);
    const [moreOpen, setMoreOpen] = useState(false);
    useHotkeyScope('dialog', adding);

    const setFilters = (next: Record<string, string>) => {
        router.get(
            '/app/vendors',
            buildTableQuery({
                ...table.state,
                filters: { ...table.state.filters, ...next },
                page: 1,
            }),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const csvColumns: CsvColumn<Row>[] = [
        { header: t('vendors.col_name'), value: (r) => r.name },
        { header: t('vendors.col_company'), value: (r) => r.company ?? '' },
        { header: t('vendors.col_phone'), value: (r) => r.phone ?? '' },
        { header: t('vendors.col_hub'), value: (r) => r.address ?? '' },
        {
            header: t('vendors.col_open_bills'),
            value: (r) => String(r.open_bills),
        },
        {
            header: t('vendors.col_balance'),
            value: (r) => moneyToDecimalString(r.balance),
        },
    ];
    const exportRows = (list: Row[]) =>
        downloadRowsCsv('vendors.csv', csvColumns, list);

    useHotkeys('list', [
        {
            id: 'vendors.new',
            keys: 'n',
            labelKey: 'hotkeys.new',
            handler: () => can('vendors.create') && setAdding(true),
        },
        {
            id: 'vendors.search',
            keys: '/',
            labelKey: 'hotkeys.search',
            handler: (e) => {
                e.preventDefault();
                document.getElementById('page-search')?.focus();
            },
        },
        {
            id: 'vendors.export',
            keys: 'shift+e',
            labelKey: 'vendors.export_csv',
            handler: () => exportRows(rows),
        },
        {
            id: 'vendors.more_filters',
            keys: 'shift+f',
            labelKey: 'table.more_filters',
            handler: () => setMoreOpen(true),
        },
    ]);

    const columns = useMemo<DataColumn<Row>[]>(
        () => [
            {
                id: 'name',
                headerKey: 'vendors.col_name',
                sortKey: 'name',
                required: true,
                mobile: 'title',
                cell: (r) => (
                    <span className="font-semibold text-foreground">
                        {r.name}
                        {r.is_active ? null : (
                            <span className="ml-2">
                                <StatusBadge status="inactive" />
                            </span>
                        )}
                    </span>
                ),
            },
            {
                id: 'company',
                headerKey: 'vendors.col_contact',
                sortKey: 'company',
                cell: (r) => (
                    <span className="text-muted-foreground">
                        {r.company ?? r.hub ?? '—'}
                    </span>
                ),
            },
            {
                id: 'phone',
                headerKey: 'vendors.col_phone',
                cell: (r) => (
                    <span className="num font-mono text-muted-foreground">
                        {r.phone ? digits.n(r.phone) : '—'}
                    </span>
                ),
            },
            {
                id: 'hub',
                headerKey: 'vendors.col_hub_order',
                cell: (r) => (
                    <span className="text-muted-foreground">
                        {r.hub ?? '—'}
                        {r.last_order ? (
                            <span className="num ml-2 text-[10px]">
                                {digits.n(r.last_order)}
                            </span>
                        ) : null}
                    </span>
                ),
            },
            {
                id: 'open_bills',
                headerKey: 'vendors.col_open_bills',
                align: 'center',
                cell: (r) => (
                    <span className="num">{digits.n(r.open_bills)}</span>
                ),
            },
            {
                id: 'balance',
                headerKey: 'vendors.col_payable',
                sortKey: 'balance',
                numeric: true,
                cell: (r) => (
                    <span
                        className={
                            r.balance > 0
                                ? 'font-bold text-destructive'
                                : 'font-bold text-foreground'
                        }
                    >
                        {digits.money(r.balance)}
                    </span>
                ),
            },
            {
                id: 'actions',
                headerKey: 'common.actions',
                required: true,
                mobile: 'hidden',
                cell: (r) =>
                    canPay ? (
                        <span className="flex justify-end">
                            {r.balance > 0 ? (
                                <button
                                    type="button"
                                    className="rounded-sm bg-primary-soft px-2.5 py-1 text-[11px] font-semibold whitespace-nowrap text-primary transition-colors hover:bg-primary hover:text-primary-foreground [@media(pointer:coarse)]:min-h-11"
                                    onClick={() => setPaying(r)}
                                >
                                    {t('vendors.pay')}
                                </button>
                            ) : (
                                // aria-disabled (not disabled) keeps the tooltip and stops the row click.
                                <button
                                    type="button"
                                    aria-disabled="true"
                                    title={t('vendors.nothing_payable')}
                                    className="cursor-not-allowed rounded-sm bg-surface-sunken px-2.5 py-1 text-[11px] font-semibold whitespace-nowrap text-muted-foreground opacity-60 [@media(pointer:coarse)]:min-h-11"
                                >
                                    {t('vendors.pay')}
                                </button>
                            )}
                        </span>
                    ) : null,
            },
        ],
        [digits, t, canPay],
    );
    const columnState = useColumnVisibility(columns);

    const bulkActions: BulkAction<Row>[] = [
        {
            id: 'export',
            labelKey: 'vendors.export_selected',
            icon: FileText,
            run: exportRows,
        },
    ];

    const statusFilter = filters['status'] ?? '';
    const hub = filters['hub'] ?? '';
    const view = statusFilter === 'owing' ? 'owing' : '';
    const moreCount = [filters['status']].filter(
        (v) => v === 'cleared' || v === 'inactive' || v === 'active',
    ).length;

    return (
        <>
            <Head title={t('vendors.title')} />
            <Page fill>
                <PageHeader
                    title={t('vendors.list_title')}
                    count={meta.total}
                    subtitle={t('vendors.subtitle')}
                    actions={
                        <>
                            {can('vendors.create') ? (
                                <Button
                                    type="button"
                                    onClick={() => setAdding(true)}
                                >
                                    <Plus aria-hidden="true" />
                                    {t('vendors.new')}
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
                                        label: t('vendors.export_csv'),
                                        onSelect: () => exportRows(rows),
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
                    placeholderKey="vendors.search"
                    view={{
                        value: view,
                        options: [
                            { value: '', label: t('vendors.view_default') },
                            { value: 'owing', label: t('vendors.view_owing') },
                        ],
                        onChange: (v) =>
                            setFilters({
                                status: v === 'owing' ? 'owing' : '',
                            }),
                    }}
                    date={{
                        value: filters['ordered'] ?? '',
                        onChange: (v) => table.setFilter('ordered', v),
                        options: RECENCY.map((key) => ({
                            value: key,
                            label: t(`vendors.ordered_${key || 'all'}`),
                        })),
                    }}
                    chips={
                        hubs.length > 0
                            ? [
                                  {
                                      key: 'hub',
                                      label: t('vendors.hub'),
                                      value: hub,
                                      options: [
                                          {
                                              value: '',
                                              label: t('vendors.all_hubs'),
                                          },
                                          ...hubs.map((h) => ({
                                              value: h,
                                              label: h,
                                          })),
                                      ],
                                      onChange: (v) => setFilters({ hub: v }),
                                  },
                              ]
                            : []
                    }
                    moreFilters={{
                        count: moreCount,
                        onOpen: () => setMoreOpen(true),
                    }}
                />
                <div
                    className="flex min-h-0 flex-1 flex-col"
                    {...rowOpenHandlers(rows, (r) =>
                        router.visit(`/app/vendors/${r.uuid}`),
                    )}
                >
                    <DataTable<Row>
                        entity="vendor"
                        caption={t('vendors.title')}
                        rows={rows}
                        columns={columns}
                        meta={meta}
                        getRowId={(r) => r.uuid}
                        rowHref={(r) => `/app/vendors/${r.uuid}`}
                        handlers={{
                            'row.edit': (r) =>
                                router.visit(`/app/vendors/${r.uuid}/edit`),
                            ...(can('vendors.delete')
                                ? { 'row.delete': (r: Row) => setToDelete(r) }
                                : {}),
                        }}
                        bulkActions={bulkActions}
                        sort={table.state.sort}
                        onSort={table.toggleSort}
                        onPage={table.setPage}
                        loading={table.loading}
                        chooser="none"
                        visibility={columnState.visibility}
                        onVisibilityChange={columnState.setVisibility}
                        onPerPage={table.setPerPage}
                        fill
                        totals={{
                            cells: {
                                open_bills: (
                                    <span className="num">
                                        {digits.n(totals.open_bills)}
                                    </span>
                                ),
                                balance: digits.money(totals.payable),
                            },
                        }}
                        empty={
                            <EmptyState
                                icon={Truck}
                                title={t('vendors.empty')}
                                description={t('vendors.empty_hint')}
                            />
                        }
                    />
                </div>
            </Page>
            <Dialog open={adding} onOpenChange={setAdding}>
                <DialogContent layout="panel" size="lg">
                    {adding ? (
                        <VendorForm
                            vendor={null}
                            variant="dialog"
                            onCancel={() => setAdding(false)}
                        />
                    ) : null}
                </DialogContent>
            </Dialog>
            <FiltersDialog
                open={moreOpen}
                onOpenChange={setMoreOpen}
                values={filters}
                onApply={setFilters}
                fields={[
                    {
                        key: 'status',
                        label: t('vendors.payable_status'),
                        options: [
                            { value: '', label: t('vendors.status_all') },
                            { value: 'owing', label: t('vendors.owing') },
                            {
                                value: 'cleared',
                                label: t('vendors.status_cleared'),
                            },
                            {
                                value: 'active',
                                label: t('products.status_active'),
                            },
                            {
                                value: 'inactive',
                                label: t('products.inactive'),
                            },
                        ],
                    },
                ]}
            />
            {canPay && paying ? (
                <PayDialog
                    open
                    onOpenChange={(o) => !o && setPaying(null)}
                    url={`/app/vendors/${paying.uuid}/payments`}
                    due={Math.max(paying.balance, 0)}
                    methods={paymentMethods}
                />
            ) : null}
            <ConfirmDialog
                open={toDelete !== null}
                onOpenChange={(o) => !o && setToDelete(null)}
                title={t('vendors.deactivate_title')}
                description={t('vendors.deactivate_hint')}
                destructive
                confirmLabel={t('vendors.deactivate_confirm', {
                    name: toDelete?.name ?? '',
                })}
                onConfirm={() => {
                    if (toDelete)
                        router.delete(`/app/vendors/${toDelete.uuid}`);
                }}
            />
        </>
    );
}
