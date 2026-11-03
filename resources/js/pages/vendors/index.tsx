import { Head, Link, router } from '@inertiajs/react';
import { Page, PageHeader } from '@/components/page-header';
import { Plus, Truck } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
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
    name: string;
    company: string | null;
    phone: string | null;
    balance: number;
    is_active: boolean;
};

export default function VendorsIndex({
    rows,
    meta,
}: {
    rows: Row[];
    meta: TableMeta;
}) {
    const { t } = useT();
    const digits = useDigits();
    const can = useCan();
    const table = useServerTable();
    const [toDelete, setToDelete] = useState<Row | null>(null);

    useHotkeys('list', [
        {
            id: 'vendors.new',
            keys: 'n',
            labelKey: 'hotkeys.new',
            handler: () =>
                can('vendors.create') && router.visit('/app/vendors/create'),
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
                    <span className="font-medium">
                        {r.name}
                        {r.is_active ? '' : ` · ${t('products.inactive')}`}
                    </span>
                ),
            },
            {
                id: 'company',
                headerKey: 'vendors.col_company',
                sortKey: 'company',
                cell: (r) => r.company ?? '',
            },
            {
                id: 'phone',
                headerKey: 'vendors.col_phone',
                cell: (r) => <span className="num">{r.phone ?? ''}</span>,
            },
            {
                id: 'balance',
                headerKey: 'vendors.col_balance',
                sortKey: 'balance',
                numeric: true,
                cell: (r) =>
                    r.balance > 0 ? (
                        <span className="inline-flex items-center gap-2">
                            <StatusBadge status="due" />
                            {digits.money(r.balance)}
                        </span>
                    ) : (
                        digits.money(r.balance)
                    ),
            },
        ],
        [digits, t],
    );

    return (
        <>
            <Head title={t('vendors.title')} />
            <Page>
                <PageHeader
                    title={t('vendors.title')}
                    count={meta.total}
                    actions={
                        can('vendors.create') ? (
                            <Button asChild>
                                <Link href="/app/vendors/create">
                                    <Plus aria-hidden="true" />{' '}
                                    {t('vendors.new')}
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <TableToolbar
                    search={table.searchText}
                    onSearch={table.setSearch}
                    placeholderKey="vendors.search"
                    filters={[
                        {
                            key: 'status',
                            labelKey: 'products.all_statuses',
                            value: table.state.filters['status'] ?? '',
                            options: [
                                {
                                    value: 'active',
                                    label: t('products.status_active'),
                                },
                                {
                                    value: 'inactive',
                                    label: t('products.inactive'),
                                },
                                { value: 'owing', label: t('vendors.owing') },
                            ],
                            onChange: (v) => table.setFilter('status', v),
                        },
                    ]}
                />
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
                    sort={table.state.sort}
                    onSort={table.toggleSort}
                    onPage={table.setPage}
                    loading={table.loading}
                    selectable={false}
                    empty={
                        <EmptyState
                            icon={Truck}
                            title={t('vendors.empty')}
                            description={t('vendors.empty_hint')}
                        />
                    }
                />
            </Page>
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
