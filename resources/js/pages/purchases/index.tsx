import { Head, Link, router } from '@inertiajs/react';
import { Page, PageHeader } from '@/components/page-header';
import { Plus, Truck } from 'lucide-react';
import { useMemo } from 'react';
import {
    DataTable,
    type DataColumn,
    type TableMeta,
} from '@/components/data-table/data-table';
import { useServerTable } from '@/components/data-table/server-table';
import { EmptyState } from '@/components/empty-state';
import { StatusBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';

type Row = {
    id: number;
    uuid: string;
    number: string;
    vendor: string;
    vendor_ref: string | null;
    status: string;
    payment_status: string;
    purchased_at: string;
    total: number;
    due: number;
};

export default function PurchasesIndex({
    rows,
    meta,
    vendors,
}: {
    rows: Row[];
    meta: TableMeta;
    vendors: { id: number; name: string }[];
}) {
    const { t } = useT();
    const digits = useDigits();
    const can = useCan();
    const table = useServerTable();

    useHotkeys('list', [
        {
            id: 'purchases.new',
            keys: 'n',
            labelKey: 'hotkeys.new',
            handler: () =>
                can('purchases.create') &&
                router.visit('/app/purchases/create'),
        },
        {
            id: 'purchases.search',
            keys: '/',
            labelKey: 'hotkeys.search',
            handler: (e) => {
                e.preventDefault();
                document.getElementById('page-search')?.focus();
            },
        },
    ]);

    const columns = useMemo<DataColumn<Row>[]>(
        () => [
            {
                id: 'number',
                headerKey: 'purchases.col_number',
                sortKey: 'number',
                required: true,
                mobile: 'title',
                cell: (r) => (
                    <span className="num font-medium">{r.number}</span>
                ),
            },
            {
                id: 'vendor',
                headerKey: 'purchases.vendor',
                cell: (r) => r.vendor,
            },
            {
                id: 'date',
                headerKey: 'purchases.col_date',
                sortKey: 'purchased_at',
                cell: (r) => (
                    <span className="num">{digits.n(r.purchased_at)}</span>
                ),
            },
            {
                id: 'status',
                headerKey: 'common.status',
                cell: (r) => (
                    <StatusBadge
                        status={
                            r.status === 'received'
                                ? r.payment_status
                                : r.status
                        }
                    />
                ),
            },
            {
                id: 'total',
                headerKey: 'purchases.col_total',
                sortKey: 'total',
                numeric: true,
                cell: (r) => digits.money(r.total),
            },
            {
                id: 'due',
                headerKey: 'purchases.col_due',
                sortKey: 'due',
                numeric: true,
                cell: (r) => digits.money(r.due),
            },
        ],
        [digits],
    );

    return (
        <>
            <Head title={t('purchases.title')} />
            <Page>
                <PageHeader
                    title={t('purchases.title')}
                    count={meta.total}
                    actions={
                        can('purchases.create') ? (
                            <Button asChild>
                                <Link href="/app/purchases/create">
                                    <Plus aria-hidden="true" />{' '}
                                    {t('purchases.new')}
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <TableToolbar
                    search={table.searchText}
                    onSearch={table.setSearch}
                    placeholderKey="purchases.search"
                    filters={[
                        {
                            key: 'vendor',
                            labelKey: 'purchases.all_vendors',
                            value: table.state.filters['vendor'] ?? '',
                            options: vendors.map((v) => ({
                                value: String(v.id),
                                label: v.name,
                            })),
                            onChange: (v) => table.setFilter('vendor', v),
                        },
                        {
                            key: 'status',
                            labelKey: 'purchases.all_statuses',
                            value: table.state.filters['status'] ?? '',
                            options: [
                                {
                                    value: 'received',
                                    label: t('status.received'),
                                },
                                {
                                    value: 'cancelled',
                                    label: t('status.cancelled'),
                                },
                            ],
                            onChange: (v) => table.setFilter('status', v),
                        },
                        {
                            key: 'payment',
                            labelKey: 'purchases.all_payments',
                            value: table.state.filters['payment'] ?? '',
                            options: [
                                { value: 'unpaid', label: t('status.unpaid') },
                                {
                                    value: 'partial',
                                    label: t('status.partial'),
                                },
                                { value: 'paid', label: t('status.paid') },
                            ],
                            onChange: (v) => table.setFilter('payment', v),
                        },
                    ]}
                />
                <DataTable<Row>
                    entity="purchase"
                    caption={t('purchases.title')}
                    rows={rows}
                    columns={columns}
                    meta={meta}
                    getRowId={(r) => r.uuid}
                    rowHref={(r) => `/app/purchases/${r.uuid}`}
                    sort={table.state.sort}
                    onSort={table.toggleSort}
                    onPage={table.setPage}
                    loading={table.loading}
                    selectable={false}
                    empty={
                        <EmptyState
                            icon={Truck}
                            title={t('purchases.empty')}
                            description={t('purchases.empty_hint')}
                        />
                    }
                />
            </Page>
        </>
    );
}
