import { Head, Link, router } from '@inertiajs/react';
import { CreditCard, FileSpreadsheet, Printer } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import {
    DataTable,
    type BulkAction,
    type DataColumn,
} from '@/components/data-table/data-table';
import { useServerTable } from '@/components/data-table/server-table';
import { EmptyState } from '@/components/empty-state';
import { StatusBadge, type Tone } from '@/components/status-badge';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import type { CardRow, TableMetaProp } from '@/types/customers';

const TONE: Record<string, Tone> = {
    active: 'success',
    lost: 'danger',
    replaced: 'neutral',
    revoked: 'neutral',
};
const ALL = '__all';
const STATUSES = ['active', 'lost', 'replaced', 'revoked'] as const;

const ids = (rows: CardRow[]) => rows.map((r) => r.id);

/** Opens the print-ready HTML (the browser's print dialog saves the PDF) and records the print. */
function press(rows: CardRow[], layout: 'cr80' | 'bleed') {
    const query = `ids=${ids(rows).join(',')}&layout=${layout}`;
    window.open(`/app/membership-cards/print?${query}`, '_blank', 'noopener');
    router.post(
        '/app/membership-cards/printed',
        { ids: ids(rows), layout },
        { preserveScroll: true, preserveState: true },
    );
}

function exportCsv(rows: CardRow[]) {
    window.location.assign(
        `/app/membership-cards/export.csv?ids=${ids(rows).join(',')}`,
    );
}

export default function CardsIndex({
    cards,
    meta,
    can,
}: {
    cards: CardRow[];
    meta: TableMetaProp;
    can: { issue: boolean };
}) {
    const { t } = useT();
    const digits = useDigits();
    const table = useServerTable({ only: ['cards', 'meta'] });
    const [losing, setLosing] = useState<CardRow | null>(null);

    const columns: DataColumn<CardRow>[] = [
        {
            id: 'card_code',
            headerKey: 'cards.col.code',
            sortKey: 'card_code',
            required: true,
            cell: (r) => <span className="num font-mono">{digits.n(r.card_code)}</span>,
        },
        {
            id: 'name',
            headerKey: 'customers.col.name',
            mobile: 'title',
            cell: (r) =>
                r.customer_uuid ? (
                    <Link
                        href={`/app/customers/${r.customer_uuid}`}
                        className="font-medium hover:underline"
                    >
                        {r.name}
                    </Link>
                ) : (
                    '-'
                ),
        },
        {
            id: 'shop_name',
            headerKey: 'customers.col.shop',
            cell: (r) => r.shop_name ?? '-',
        },
        {
            id: 'customer_no',
            headerKey: 'customers.col.no',
            cell: (r) => (
                <span className="num">
                    {r.customer_no ? digits.n(r.customer_no) : '-'}
                </span>
            ),
        },
        {
            id: 'status',
            headerKey: 'cards.col.status',
            cell: (r) => (
                <StatusBadge
                    status={r.status}
                    tone={TONE[r.status] ?? 'neutral'}
                    label={t(`cards.status.${r.status}`)}
                />
            ),
        },
        {
            id: 'issued_at',
            headerKey: 'cards.col.issued',
            sortKey: 'issued_at',
            cell: (r) => digits.n(r.issued_at),
        },
        {
            id: 'print_count',
            headerKey: 'cards.col.prints',
            sortKey: 'print_count',
            numeric: true,
            cell: (r) => digits.n(r.print_count),
        },
        {
            id: 'last_printed_at',
            headerKey: 'cards.col.last_printed',
            sortKey: 'last_printed_at',
            hidden: true,
            cell: (r) =>
                r.last_printed_at ? digits.n(r.last_printed_at.slice(0, 10)) : '-',
        },
    ];

    const bulkActions: BulkAction<CardRow>[] = can.issue
        ? [
              {
                  id: 'press_cr80',
                  labelKey: 'cards.press.cr80',
                  icon: Printer,
                  run: (rows) => press(rows, 'cr80'),
              },
              {
                  id: 'press_bleed',
                  labelKey: 'cards.press.bleed',
                  icon: CreditCard,
                  run: (rows) => press(rows, 'bleed'),
              },
              {
                  id: 'press_csv',
                  labelKey: 'cards.press.csv',
                  icon: FileSpreadsheet,
                  run: exportCsv,
              },
          ]
        : [];

    const handlers = useMemo(
        () =>
            can.issue
                ? {
                      'card.lost': setLosing,
                      'card.reissue': (row: CardRow) =>
                          router.post(
                              `/app/membership-cards/${row.id}/reissue`,
                              {},
                              { preserveScroll: true },
                          ),
                  }
                : {},
        [can.issue],
    );

    return (
        <>
            <Head title={t('cards.title')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <h1 className="text-2xl font-bold">{t('cards.title')}</h1>

                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        type="search"
                        defaultValue={table.state.q}
                        placeholder={t('cards.search')}
                        aria-label={t('cards.search')}
                        className="min-h-11 max-w-sm md:min-h-9"
                        onChange={(e) => table.setSearch(e.target.value)}
                    />
                    <Select
                        value={table.state.filters['status'] ?? ALL}
                        onValueChange={(v) =>
                            table.setFilter('status', v === ALL ? '' : v)
                        }
                    >
                        <SelectTrigger
                            className="min-h-11 w-44 md:min-h-9"
                            aria-label={t('cards.col.status')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                {t('cards.filter.all')}
                            </SelectItem>
                            {STATUSES.map((s) => (
                                <SelectItem key={s} value={s}>
                                    {t(`cards.status.${s}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {can.issue ? (
                    <p className="rounded-md border bg-muted p-3 text-sm text-muted-foreground">
                        {t('cards.press.note')}
                    </p>
                ) : null}

                <DataTable<CardRow>
                    entity="card"
                    caption={t('cards.title')}
                    rows={cards}
                    columns={columns}
                    meta={meta}
                    getRowId={(r) => r.id}
                    {...(can.issue ? { handlers } : {})}
                    bulkActions={bulkActions}
                    selectable={can.issue}
                    sort={table.state.sort}
                    onSort={table.toggleSort}
                    onPage={table.setPage}
                    loading={table.loading}
                    empty={
                        <EmptyState
                            icon={CreditCard}
                            title={t('cards.empty')}
                            description={t('cards.empty_hint')}
                        />
                    }
                />
            </div>

            <ConfirmDialog
                open={losing !== null}
                onOpenChange={(open) => !open && setLosing(null)}
                title={t('cards.lost.title')}
                description={t('cards.lost.body')}
                confirmLabel={t('cards.actions.lost')}
                destructive
                onConfirm={() =>
                    losing
                        ? router.post(
                              `/app/membership-cards/${losing.id}/lost`,
                              {},
                              { preserveScroll: true },
                          )
                        : undefined
                }
            />
        </>
    );
}
