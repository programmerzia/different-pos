import { Head, Link } from '@inertiajs/react';
import { Page, PageHeader } from '@/components/page-header';
import { HandCoins, Wallet } from 'lucide-react';
import { useMemo } from 'react';
import {
    DataTable,
    type DataColumn,
    type TableMeta,
} from '@/components/data-table/data-table';
import { useServerTable } from '@/components/data-table/server-table';
import { EmptyState } from '@/components/empty-state';
import { KpiTile } from '@/components/kpi-tile';
import { TableToolbar } from '@/components/table-toolbar';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { BUCKETS, type Bucket } from '@/lib/dues';
import { salesUrl, type CustomerSummary } from '@/lib/sales';
import { cn } from '@/lib/utils';

type Row = {
    customer:
        | (CustomerSummary & { balance: number; credit_limit: number | null })
        | null;
    invoices: number;
    total_due: number;
    oldest_at: string | null;
    buckets: Record<Bucket, number>;
};

type Props = {
    rows: Row[];
    meta: TableMeta;
    filters: Record<string, string>;
    totals: {
        customers: number;
        invoices: number;
        total_due: number;
        buckets: Record<Bucket, number>;
    };
};

const BUCKET_TONE: Record<Bucket, string> = {
    d0_30: '',
    d31_60: 'text-warning',
    d61_90: 'text-warning font-semibold',
    d90_plus: 'text-destructive font-semibold',
};

export default function DuesIndex({ rows, meta, filters, totals }: Props) {
    const { t } = useT();
    const digits = useDigits();
    const table = useServerTable();

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
    ]);

    const columns = useMemo<DataColumn<Row>[]>(
        () => [
            {
                id: 'customer',
                headerKey: 'sales.col_customer',
                required: true,
                mobile: 'title',
                cell: (r) => (
                    <div>
                        <div className="font-medium">
                            {r.customer?.name ?? '—'}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {r.customer?.shop_name ?? ''}
                            {r.customer?.customer_no ? (
                                <span className="num ml-1 text-primary">
                                    #{digits.n(r.customer.customer_no)}
                                </span>
                            ) : null}
                        </div>
                    </div>
                ),
            },
            {
                id: 'phone',
                headerKey: 'dues.col_phone',
                cell: (r) => (
                    <span className="num">
                        {digits.n(r.customer?.phone ?? '')}
                    </span>
                ),
            },
            {
                id: 'invoices',
                headerKey: 'dues.col_invoices',
                sortKey: 'invoices',
                numeric: true,
                cell: (r) => digits.n(r.invoices),
            },
            ...BUCKETS.map((bucket): DataColumn<Row> => ({
                id: bucket,
                headerKey: `dues.bucket_${bucket}`,
                numeric: true,
                cell: (r) =>
                    r.buckets[bucket] ? (
                        <span className={BUCKET_TONE[bucket]}>
                            {digits.money(r.buckets[bucket])}
                        </span>
                    ) : (
                        '—'
                    ),
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
                id: 'oldest',
                headerKey: 'dues.col_oldest',
                sortKey: 'oldest_at',
                cell: (r) => (
                    <span className="num text-muted-foreground">
                        {digits.n((r.oldest_at ?? '').slice(0, 10))}
                    </span>
                ),
            },
        ],
        [digits],
    );

    return (
        <>
            <Head title={t('dues.title')} />
            <Page>
                <PageHeader
                    title={t('dues.title')}
                    count={meta.total}
                    subtitle={t('dues.subtitle')}
                />
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <KpiTile
                        label={t('dues.kpi_total', {
                            n: digits.n(totals.customers),
                        })}
                        value={digits.money(totals.total_due)}
                    />
                    {BUCKETS.map((bucket) => (
                        <button
                            key={bucket}
                            type="button"
                            className={cn(
                                'rounded-lg text-left focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                filters['bucket'] === bucket &&
                                    'ring-2 ring-primary',
                            )}
                            aria-pressed={filters['bucket'] === bucket}
                            onClick={() =>
                                table.setFilter(
                                    'bucket',
                                    filters['bucket'] === bucket ? '' : bucket,
                                )
                            }
                        >
                            <KpiTile
                                label={t(`dues.bucket_${bucket}`)}
                                value={
                                    <span className={BUCKET_TONE[bucket]}>
                                        {digits.money(totals.buckets[bucket])}
                                    </span>
                                }
                            />
                        </button>
                    ))}
                </div>
                <TableToolbar
                    search={table.searchText}
                    onSearch={table.setSearch}
                    placeholderKey="dues.search"
                    filters={[
                        {
                            key: 'bucket',
                            labelKey: 'dues.all_buckets',
                            value: filters['bucket'] ?? '',
                            options: BUCKETS.map((b) => ({
                                value: b,
                                label: t(`dues.bucket_${b}`),
                            })),
                            onChange: (v) => table.setFilter('bucket', v),
                        },
                    ]}
                />
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
                    sort={table.state.sort}
                    onSort={table.toggleSort}
                    onPage={table.setPage}
                    loading={table.loading}
                    selectable={false}
                    empty={
                        <EmptyState
                            icon={Wallet}
                            title={t('dues.empty')}
                            description={t('dues.empty_hint')}
                        />
                    }
                />
                <p className="flex items-center gap-2 text-xs text-muted-foreground">
                    <HandCoins className="size-4" aria-hidden="true" />
                    {t('dues.open_hint')}
                    <Link
                        href={
                            salesUrl.index + '?filter[payment_status]=partial'
                        }
                        className="text-primary hover:underline"
                    >
                        {t('dues.see_partial')}
                    </Link>
                </p>
            </Page>
        </>
    );
}
