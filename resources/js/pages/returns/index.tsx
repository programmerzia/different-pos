import { Head, Link } from '@inertiajs/react';
import { Page, PageHeader } from '@/components/page-header';
import { Plus, Undo2 } from 'lucide-react';
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
import { navigateTo } from '@/command/navigate';
import { useCan } from '@/hooks/use-can';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { salesUrl, type CustomerSummary } from '@/lib/sales';

type Row = {
    id: number;
    number: string;
    returned_at: string | null;
    sale: { id: number; number: string };
    customer: CustomerSummary | null;
    items: number;
    total: number;
    refunded: number;
    credited: number;
    refund_mode: string;
    reason: string;
    exchange_sale: { id: number; number: string } | null;
};

export default function ReturnsIndex({
    rows,
    meta,
    filters,
}: {
    rows: Row[];
    meta: TableMeta;
    filters: Record<string, string>;
}) {
    const { t } = useT();
    const digits = useDigits();
    const can = useCan();
    const table = useServerTable();

    useHotkeys('list', [
        {
            id: 'returns.search',
            keys: '/',
            labelKey: 'hotkeys.search',
            handler: (e) => {
                e.preventDefault();
                document.getElementById('page-search')?.focus();
            },
        },
        {
            id: 'returns.new',
            keys: 'n',
            labelKey: 'returns.new',
            handler: () =>
                can('returns.create') && navigateTo(salesUrl.returnCreate()),
        },
    ]);

    const columns = useMemo<DataColumn<Row>[]>(
        () => [
            {
                id: 'number',
                headerKey: 'returns.col_number',
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
                headerKey: 'returns.col_date',
                sortKey: 'returned_at',
                cell: (r) => (
                    <span className="num text-muted-foreground">
                        {digits.n(r.returned_at ?? '')}
                    </span>
                ),
            },
            {
                id: 'sale',
                headerKey: 'returns.col_sale',
                cell: (r) => (
                    <Link
                        href={salesUrl.show(r.sale.id)}
                        className="num hover:underline"
                    >
                        {r.sale.number}
                    </Link>
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
                id: 'items',
                headerKey: 'sales.col_items',
                numeric: true,
                cell: (r) => digits.n(r.items),
            },
            {
                id: 'mode',
                headerKey: 'returns.col_mode',
                cell: (r) => (
                    <span className="flex flex-col gap-0.5">
                        <StatusBadge
                            status={r.refund_mode}
                            tone={
                                r.refund_mode === 'exchange'
                                    ? 'accent'
                                    : r.refund_mode === 'credit'
                                      ? 'info'
                                      : 'neutral'
                            }
                            label={t(`returns.mode_${r.refund_mode}`)}
                        />
                        {r.exchange_sale ? (
                            <Link
                                href={salesUrl.show(r.exchange_sale.id)}
                                className="num text-xs text-primary hover:underline"
                            >
                                {r.exchange_sale.number}
                            </Link>
                        ) : null}
                    </span>
                ),
            },
            {
                id: 'reason',
                headerKey: 'returns.col_reason',
                hidden: true,
                cell: (r) =>
                    t(`returns.reason_${r.reason}`, { defaultValue: r.reason }),
            },
            {
                id: 'total',
                headerKey: 'returns.col_total',
                sortKey: 'total',
                numeric: true,
                cell: (r) => digits.money(r.total),
            },
            {
                id: 'refunded',
                headerKey: 'returns.col_refunded',
                numeric: true,
                cell: (r) => (r.refunded ? digits.money(r.refunded) : '—'),
            },
            {
                id: 'credited',
                headerKey: 'returns.col_credited',
                numeric: true,
                cell: (r) => (r.credited ? digits.money(r.credited) : '—'),
            },
        ],
        [digits, t],
    );

    return (
        <>
            <Head title={t('returns.title')} />
            <Page>
                <PageHeader
                    title={t('returns.title')}
                    count={meta.total}
                    subtitle={t('returns.subtitle')}
                    actions={
                        can('returns.create') ? (
                            <Button asChild>
                                <Link href={salesUrl.returnCreate()}>
                                    <Plus aria-hidden="true" />{' '}
                                    {t('returns.new')}
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <TableToolbar
                    search={table.searchText}
                    onSearch={table.setSearch}
                    placeholderKey="returns.search"
                    filters={[
                        {
                            key: 'mode',
                            labelKey: 'returns.all_modes',
                            value: filters['mode'] ?? '',
                            options: ['refund', 'credit', 'exchange'].map(
                                (m) => ({
                                    value: m,
                                    label: t(`returns.mode_${m}`),
                                }),
                            ),
                            onChange: (v) => table.setFilter('mode', v),
                        },
                    ]}
                />
                <DataTable<Row>
                    entity="sale_return"
                    caption={t('returns.title')}
                    rows={rows}
                    columns={columns}
                    meta={meta}
                    getRowId={(r) => String(r.id)}
                    rowHref={(r) => salesUrl.returnShow(r.id)}
                    sort={table.state.sort}
                    onSort={table.toggleSort}
                    onPage={table.setPage}
                    loading={table.loading}
                    selectable={false}
                    empty={
                        <EmptyState
                            icon={Undo2}
                            title={t('returns.empty')}
                            description={t('returns.empty_hint')}
                        />
                    }
                />
            </Page>
        </>
    );
}
