import { Head, Link, router, usePage } from '@inertiajs/react';
import { Receipt, Undo2, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    DataTable,
    type DataColumn,
    type TableMeta,
} from '@/components/data-table/data-table';
import {
    buildTableQuery,
    useServerTable,
} from '@/components/data-table/server-table';
import { DateRangePicker } from '@/components/date-range-picker';
import { EmptyState } from '@/components/empty-state';
import { CollectDialog } from '@/components/sales/collect-dialog';
import { VoidSaleDialog } from '@/components/sales/void-dialog';
import { StatusBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { navigateTo } from '@/command/navigate';
import { useCan } from '@/hooks/use-can';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { salesUrl, type CustomerSummary, type MoneyMethod } from '@/lib/sales';
import { registerSaleActions } from '@/lib/sales-actions';

registerSaleActions();

type Row = {
    id: number;
    number: string;
    sold_at: string | null;
    customer: CustomerSummary | null;
    cashier: string | null;
    items: number;
    total: number;
    paid: number;
    due: number;
    returned: number;
    status: string;
    payment_status: string;
    voidable: boolean;
    exchange: boolean;
};

type Props = {
    rows: Row[];
    meta: TableMeta;
    filters: Record<string, string>;
    cashiers: { id: number; name: string }[];
    customer: CustomerSummary | null;
    methods: MoneyMethod[];
};

export default function SalesIndex({
    rows,
    meta,
    filters,
    cashiers,
    customer,
    methods,
}: Props) {
    const { t } = useT();
    const digits = useDigits();
    const can = useCan();
    const table = useServerTable();
    const url = usePage().url;
    const [voiding, setVoiding] = useState<Row | null>(null);
    const [collecting, setCollecting] = useState<Row | null>(null);

    const setFilters = (next: Record<string, string>) => {
        const path = url.split('?')[0] ?? url;
        router.get(
            path,
            buildTableQuery({
                ...table.state,
                filters: { ...table.state.filters, ...next },
                page: 1,
            }),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    useHotkeys('list', [
        {
            id: 'sales.search',
            keys: '/',
            labelKey: 'hotkeys.search',
            handler: (e) => {
                e.preventDefault();
                document.getElementById('page-search')?.focus();
            },
        },
        {
            id: 'sales.new_return',
            keys: 'shift+r',
            labelKey: 'returns.new',
            handler: () =>
                can('returns.create') && navigateTo(salesUrl.returnCreate()),
        },
    ]);

    const columns = useMemo<DataColumn<Row>[]>(
        () => [
            {
                id: 'number',
                headerKey: 'sales.col_number',
                sortKey: 'number',
                required: true,
                mobile: 'title',
                cell: (r) => (
                    <span className="num font-semibold text-primary">
                        {r.number}
                    </span>
                ),
            },
            {
                id: 'date',
                headerKey: 'sales.col_date',
                sortKey: 'sold_at',
                cell: (r) => (
                    <span className="num text-muted-foreground">
                        {digits.n(r.sold_at ?? '')}
                    </span>
                ),
            },
            {
                id: 'customer',
                headerKey: 'sales.col_customer',
                cell: (r) =>
                    r.customer && !r.customer.walk_in ? (
                        <div>
                            <div className="font-medium">{r.customer.name}</div>
                            {r.customer.shop_name ? (
                                <div className="text-xs text-muted-foreground">
                                    {r.customer.shop_name}
                                </div>
                            ) : null}
                        </div>
                    ) : (
                        <span className="text-muted-foreground">
                            {t('sales.walk_in')}
                        </span>
                    ),
            },
            {
                id: 'cashier',
                headerKey: 'sales.col_cashier',
                hidden: true,
                cell: (r) => r.cashier ?? '—',
            },
            {
                id: 'items',
                headerKey: 'sales.col_items',
                numeric: true,
                cell: (r) => digits.n(r.items),
            },
            {
                id: 'total',
                headerKey: 'sales.col_total',
                sortKey: 'total',
                numeric: true,
                cell: (r) => digits.money(r.total),
            },
            {
                id: 'paid',
                headerKey: 'sales.col_paid',
                sortKey: 'paid',
                numeric: true,
                cell: (r) => digits.money(r.paid),
            },
            {
                id: 'due',
                headerKey: 'sales.col_due',
                sortKey: 'due',
                numeric: true,
                cell: (r) =>
                    r.due > 0 ? (
                        <span className="font-semibold text-destructive">
                            {digits.money(r.due)}
                        </span>
                    ) : (
                        '—'
                    ),
            },
            {
                id: 'status',
                headerKey: 'common.status',
                cell: (r) => (
                    <span className="flex flex-wrap gap-1">
                        <StatusBadge
                            status={
                                r.status === 'completed'
                                    ? r.payment_status
                                    : r.status
                            }
                        />
                        {r.returned > 0 ? (
                            <StatusBadge
                                status="returned"
                                tone="info"
                                label={t('sales.has_returns')}
                            />
                        ) : null}
                        {r.exchange ? (
                            <StatusBadge
                                status="exchange"
                                tone="accent"
                                label={t('sales.exchange_sale')}
                            />
                        ) : null}
                    </span>
                ),
            },
        ],
        [digits, t],
    );

    const handlers = {
        ...(can('sales.void')
            ? { 'sale.void': (r: Row) => setVoiding(r) }
            : {}),
        ...(can('payments.collect')
            ? { 'sale.collect': (r: Row) => setCollecting(r) }
            : {}),
        ...(can('returns.create') && can('pos.sell')
            ? {
                  'sale.exchange': (r: Row) =>
                      navigateTo(salesUrl.exchangeCreate(r.id)),
              }
            : {}),
    };

    const statusOptions = [
        { value: 'completed', label: t('status.completed') },
        { value: 'voided', label: t('status.voided') },
    ];
    const paymentOptions = ['paid', 'partial', 'unpaid'].map((s) => ({
        value: s,
        label: t(`status.${s}`),
    }));

    return (
        <>
            <Head title={t('sales.title')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-2xl font-bold">
                            {t('sales.title')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('sales.subtitle')}
                        </p>
                    </div>
                    {can('returns.create') ? (
                        <Button asChild variant="outline" className="min-h-11">
                            <Link href={salesUrl.returnCreate()}>
                                <Undo2 aria-hidden="true" /> {t('returns.new')}
                            </Link>
                        </Button>
                    ) : null}
                </div>
                <TableToolbar
                    search={table.searchText}
                    onSearch={table.setSearch}
                    placeholderKey="sales.search"
                    filters={[
                        {
                            key: 'status',
                            labelKey: 'sales.all_statuses',
                            value: filters['status'] ?? '',
                            options: statusOptions,
                            onChange: (v) => table.setFilter('status', v),
                        },
                        {
                            key: 'payment_status',
                            labelKey: 'sales.all_payments',
                            value: filters['payment_status'] ?? '',
                            options: paymentOptions,
                            onChange: (v) =>
                                table.setFilter('payment_status', v),
                        },
                        {
                            key: 'cashier',
                            labelKey: 'sales.all_cashiers',
                            value: filters['cashier'] ?? '',
                            options: cashiers.map((c) => ({
                                value: String(c.id),
                                label: c.name,
                            })),
                            onChange: (v) => table.setFilter('cashier', v),
                        },
                    ]}
                >
                    <DateRangePicker
                        value={{
                            from: filters['from'] ?? '',
                            to: filters['to'] ?? '',
                        }}
                        onChange={(range) =>
                            setFilters({ from: range.from, to: range.to })
                        }
                    />
                    {filters['from'] || filters['to'] ? (
                        <Button
                            type="button"
                            variant="ghost"
                            className="min-h-11 md:min-h-9"
                            onClick={() => setFilters({ from: '', to: '' })}
                        >
                            <X aria-hidden="true" /> {t('sales.clear_dates')}
                        </Button>
                    ) : null}
                    {customer ? (
                        <Button
                            type="button"
                            variant="secondary"
                            className="min-h-11 md:min-h-9"
                            aria-label={t('sales.clear_customer', {
                                name: customer.name,
                            })}
                            onClick={() => table.setFilter('customer', '')}
                        >
                            {customer.name}
                            <X aria-hidden="true" />
                        </Button>
                    ) : null}
                </TableToolbar>
                <DataTable<Row>
                    entity="sale"
                    caption={t('sales.title')}
                    rows={rows}
                    columns={columns}
                    meta={meta}
                    getRowId={(r) => String(r.id)}
                    rowHref={(r) => salesUrl.show(r.id)}
                    handlers={handlers}
                    sort={table.state.sort}
                    onSort={table.toggleSort}
                    onPage={table.setPage}
                    loading={table.loading}
                    selectable={false}
                    empty={
                        <EmptyState
                            icon={Receipt}
                            title={t('sales.empty')}
                            description={t('sales.empty_hint')}
                        />
                    }
                />
            </div>
            <VoidSaleDialog
                sale={voiding}
                onOpenChange={(open) => !open && setVoiding(null)}
            />
            {collecting ? (
                <CollectDialog
                    open
                    onOpenChange={(open) => !open && setCollecting(null)}
                    action={salesUrl.payments(collecting.id)}
                    title={t('sales.collect_title', {
                        number: collecting.number,
                    })}
                    invoices={[
                        {
                            id: collecting.id,
                            number: collecting.number,
                            sold_at: collecting.sold_at,
                            due: collecting.due,
                        },
                    ]}
                    methods={methods}
                />
            ) : null}
        </>
    );
}
