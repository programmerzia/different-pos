import { Head, Link, router } from '@inertiajs/react';
import { Boxes, SlidersHorizontal } from 'lucide-react';
import { useMemo } from 'react';
import { DataTable, type DataColumn, type TableMeta } from '@/components/data-table/data-table';
import { useServerTable } from '@/components/data-table/server-table';
import { EmptyState } from '@/components/empty-state';
import { StatusBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';

type Row = { id: number; uuid: string; name: string; variant_label: string | null; sku: string; on_hand: number; reorder_level: number; low_stock: boolean; value: number | null };

export default function StockIndex({
    rows,
    meta,
    categories,
    valuation,
    branch,
    canAdjust,
}: {
    rows: Row[];
    meta: TableMeta;
    categories: { id: number; name: string }[];
    valuation: number | null;
    branch: { id: number; name: string };
    canAdjust: boolean;
}) {
    const { t } = useT();
    const digits = useDigits();
    const table = useServerTable();

    useHotkeys('list', [
        { id: 'stock.search', keys: '/', labelKey: 'hotkeys.search', handler: (e) => { e.preventDefault(); document.getElementById('page-search')?.focus(); } },
    ]);

    const columns = useMemo<DataColumn<Row>[]>(
        () => [
            { id: 'name', headerKey: 'products.col_name', sortKey: 'name', required: true, mobile: 'title', cell: (r) => <span className="font-medium">{r.name}{r.variant_label ? ` · ${r.variant_label}` : ''}</span> },
            { id: 'sku', headerKey: 'products.col_sku', sortKey: 'sku', cell: (r) => <span className="num">{r.sku}</span> },
            {
                id: 'on_hand',
                headerKey: 'stock.on_hand',
                sortKey: 'on_hand',
                numeric: true,
                cell: (r) => (
                    <span className="inline-flex items-center justify-end gap-2">
                        {r.on_hand <= 0 ? <StatusBadge status="out_of_stock" /> : r.low_stock ? <StatusBadge status="low_stock" /> : null}
                        {digits.qty(r.on_hand)}
                    </span>
                ),
            },
            { id: 'reorder', headerKey: 'products.reorder_level', numeric: true, hidden: true, cell: (r) => digits.qty(r.reorder_level) },
            ...(valuation === null
                ? []
                : [{ id: 'value', headerKey: 'stock.value', numeric: true, cell: (r: Row) => digits.money(r.value ?? 0) } satisfies DataColumn<Row>]),
        ],
        [digits, valuation],
    );

    return (
        <>
            <Head title={t('stock.title')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h1 className="text-2xl font-bold">{t('stock.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('stock.branch', { name: branch.name })}</p>
                    </div>
                    <div className="flex items-center gap-4">
                        {valuation !== null ? (
                            <div className="text-right">
                                <p className="text-xs text-muted-foreground">{t('stock.valuation')}</p>
                                <p className="num text-lg font-bold">{digits.money(valuation)}</p>
                            </div>
                        ) : null}
                        {canAdjust ? <Button asChild className="min-h-11"><Link href="/app/adjustments/create"><SlidersHorizontal aria-hidden="true" /> {t('adjustments.new')}</Link></Button> : null}
                    </div>
                </div>
                <TableToolbar
                    search={table.searchText}
                    onSearch={table.setSearch}
                    placeholderKey="products.search"
                    filters={[
                        { key: 'category', labelKey: 'products.all_categories', value: table.state.filters['category'] ?? '', options: categories.map((c) => ({ value: String(c.id), label: c.name })), onChange: (v) => table.setFilter('category', v) },
                        { key: 'status', labelKey: 'stock.all', value: table.state.filters['status'] ?? '', options: [{ value: 'low', label: t('status.low_stock') }, { value: 'out', label: t('status.out_of_stock') }], onChange: (v) => table.setFilter('status', v) },
                    ]}
                />
                <DataTable<Row>
                    entity="stock"
                    caption={t('stock.title')}
                    rows={rows}
                    columns={columns}
                    meta={meta}
                    getRowId={(r) => r.uuid}
                    rowHref={(r) => `/app/stock/${r.uuid}/movements`}
                    handlers={{ 'row.edit': (r) => router.visit(`/app/products/${r.uuid}/edit`) }}
                    sort={table.state.sort}
                    onSort={table.toggleSort}
                    onPage={table.setPage}
                    loading={table.loading}
                    selectable={false}
                    empty={<EmptyState icon={Boxes} title={t('stock.empty')} description={t('table.empty_hint')} />}
                />
            </div>
        </>
    );
}
