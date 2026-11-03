import { Head, Link, router } from '@inertiajs/react';
import { Page, PageHeader } from '@/components/page-header';
import { ArrowRight, Plus, Truck } from 'lucide-react';
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

type BranchRef = { id: number; code: string; name: string };

type Row = {
    id: number;
    uuid: string;
    number: string;
    status: string;
    from: BranchRef;
    to: BranchRef;
    incoming: boolean;
    lines: number;
    note: string | null;
    sent_at: string | null;
    received_at: string | null;
};

const branchLabel = (b: BranchRef) => `${b.name} [${b.code}]`;

/** Stock → Branch Transfers: gate passes sent from this branch and stock arriving at it. */
export default function TransfersIndex({
    rows,
    meta,
    statuses,
    incomingCount,
}: {
    rows: Row[];
    meta: TableMeta;
    statuses: string[];
    incomingCount: number;
}) {
    const { t } = useT();
    const digits = useDigits();
    const can = useCan();
    const table = useServerTable();
    const canCreate = can('stock.transfer');

    useHotkeys('list', [
        {
            id: 'transfers.new',
            keys: 'n',
            labelKey: 'transfers.new',
            handler: () => canCreate && router.visit('/app/transfers/create'),
        },
        {
            id: 'transfers.search',
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
                headerKey: 'transfers.col_number',
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
                headerKey: 'transfers.col_date',
                sortKey: 'sent_at',
                cell: (r) => (
                    <span className="num">{digits.n(r.sent_at ?? '')}</span>
                ),
            },
            {
                id: 'route',
                headerKey: 'transfers.col_route',
                cell: (r) => (
                    <span className="inline-flex items-center gap-1.5">
                        <span
                            className={
                                r.incoming
                                    ? ''
                                    : 'font-semibold text-foreground'
                            }
                        >
                            {branchLabel(r.from)}
                        </span>
                        <ArrowRight
                            className="size-3.5 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span
                            className={
                                r.incoming
                                    ? 'font-semibold text-foreground'
                                    : ''
                            }
                        >
                            {branchLabel(r.to)}
                        </span>
                    </span>
                ),
            },
            {
                id: 'direction',
                headerKey: 'transfers.col_direction',
                cell: (r) => (
                    <StatusBadge
                        status={r.incoming ? 'incoming' : 'outgoing'}
                        tone={r.incoming ? 'info' : 'neutral'}
                        label={t(
                            r.incoming
                                ? 'transfers.incoming'
                                : 'transfers.outgoing',
                        )}
                    />
                ),
            },
            {
                id: 'lines',
                headerKey: 'transfers.col_lines',
                numeric: true,
                cell: (r) => digits.n(r.lines),
            },
            {
                id: 'status',
                headerKey: 'common.status',
                cell: (r) => (
                    <StatusBadge
                        status={r.status}
                        label={t(`transfers.status.${r.status}`)}
                    />
                ),
            },
            {
                id: 'received',
                headerKey: 'transfers.col_received',
                cell: (r) => (
                    <span className="num">
                        {r.received_at ? digits.n(r.received_at) : '—'}
                    </span>
                ),
            },
            {
                id: 'note',
                headerKey: 'purchases.note',
                cell: (r) => r.note ?? '',
            },
        ],
        [digits, t],
    );

    return (
        <>
            <Head title={t('transfers.title')} />
            <Page>
                <PageHeader
                    title={t('transfers.title')}
                    count={meta.total}
                    subtitle={t('transfers.subtitle')}
                    badge={
                        incomingCount > 0 ? (
                            <StatusBadge
                                status="in_transit"
                                label={t('transfers.incoming_count', {
                                    count: digits.n(incomingCount),
                                })}
                            />
                        ) : null
                    }
                    actions={
                        canCreate ? (
                            <Button asChild>
                                <Link href="/app/transfers/create">
                                    <Plus aria-hidden="true" />{' '}
                                    {t('transfers.new')}
                                </Link>
                            </Button>
                        ) : null
                    }
                />
                <TableToolbar
                    search={table.searchText}
                    onSearch={table.setSearch}
                    placeholderKey="transfers.search"
                    filters={[
                        {
                            key: 'direction',
                            labelKey: 'transfers.all_directions',
                            value: table.state.filters['direction'] ?? '',
                            options: [
                                {
                                    value: 'incoming',
                                    label: t('transfers.incoming'),
                                },
                                {
                                    value: 'outgoing',
                                    label: t('transfers.outgoing'),
                                },
                            ],
                            onChange: (v) => table.setFilter('direction', v),
                        },
                        {
                            key: 'status',
                            labelKey: 'transfers.all_statuses',
                            value: table.state.filters['status'] ?? '',
                            options: statuses.map((s) => ({
                                value: s,
                                label: t(`transfers.status.${s}`),
                            })),
                            onChange: (v) => table.setFilter('status', v),
                        },
                    ]}
                />
                <DataTable<Row>
                    entity="transfer"
                    caption={t('transfers.title')}
                    rows={rows}
                    columns={columns}
                    meta={meta}
                    getRowId={(r) => String(r.id)}
                    rowHref={(r) => `/app/transfers/${r.uuid}`}
                    sort={table.state.sort}
                    onSort={table.toggleSort}
                    onPage={table.setPage}
                    loading={table.loading}
                    selectable={false}
                    empty={
                        <EmptyState
                            icon={Truck}
                            title={t('transfers.empty')}
                            description={t('transfers.empty_hint')}
                        />
                    }
                />
            </Page>
        </>
    );
}
