import { Head, Link, router } from '@inertiajs/react';
import { Plus, SlidersHorizontal } from 'lucide-react';
import { useMemo } from 'react';
import { DataTable, type DataColumn, type TableMeta } from '@/components/data-table/data-table';
import { useServerTable } from '@/components/data-table/server-table';
import { EmptyState } from '@/components/empty-state';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';

type Row = { id: number; number: string; reason: string; lines: number; note: string | null; adjusted_at: string };

export default function AdjustmentsIndex({ rows, meta, reasons }: { rows: Row[]; meta: TableMeta; reasons: string[] }) {
    const { t } = useT();
    const digits = useDigits();
    const can = useCan();
    const table = useServerTable();

    useHotkeys('list', [
        { id: 'adjustments.new', keys: 'n', labelKey: 'hotkeys.new', handler: () => can('stock.adjust') && router.visit('/app/adjustments/create') },
        { id: 'adjustments.search', keys: '/', labelKey: 'hotkeys.search', handler: (e) => { e.preventDefault(); document.getElementById('page-search')?.focus(); } },
    ]);

    const columns = useMemo<DataColumn<Row>[]>(
        () => [
            { id: 'number', headerKey: 'purchases.col_number', sortKey: 'number', required: true, mobile: 'title', cell: (r) => <span className="num font-medium">{r.number}</span> },
            { id: 'date', headerKey: 'purchases.col_date', sortKey: 'adjusted_at', cell: (r) => <span className="num">{digits.n(r.adjusted_at)}</span> },
            { id: 'reason', headerKey: 'adjustments.reason', cell: (r) => t(`adjustments.reasons.${r.reason}`) },
            { id: 'lines', headerKey: 'adjustments.lines', numeric: true, cell: (r) => digits.n(r.lines) },
            { id: 'note', headerKey: 'purchases.note', cell: (r) => r.note ?? '' },
        ],
        [digits, t],
    );

    return (
        <>
            <Head title={t('adjustments.title')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-2xl font-bold">{t('adjustments.title')}</h1>
                    {can('stock.adjust') ? <Button asChild className="min-h-11"><Link href="/app/adjustments/create"><Plus aria-hidden="true" /> {t('adjustments.new')}</Link></Button> : null}
                </div>
                <TableToolbar
                    search={table.searchText}
                    onSearch={table.setSearch}
                    placeholderKey="adjustments.search"
                    filters={[{ key: 'reason', labelKey: 'adjustments.all_reasons', value: table.state.filters['reason'] ?? '', options: reasons.map((r) => ({ value: r, label: t(`adjustments.reasons.${r}`) })), onChange: (v) => table.setFilter('reason', v) }]}
                />
                <DataTable<Row>
                    entity="adjustment"
                    caption={t('adjustments.title')}
                    rows={rows}
                    columns={columns}
                    meta={meta}
                    getRowId={(r) => String(r.id)}
                    sort={table.state.sort}
                    onSort={table.toggleSort}
                    onPage={table.setPage}
                    loading={table.loading}
                    selectable={false}
                    empty={<EmptyState icon={SlidersHorizontal} title={t('adjustments.empty')} description={t('adjustments.empty_hint')} />}
                />
            </div>
        </>
    );
}
