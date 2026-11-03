import { Head, Link, router } from '@inertiajs/react';
import { Boxes, Check, FileText, ImageIcon, Pencil, Truck } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { registerStockActions } from '@/command/catalog-actions';
import {
    ColumnChooser,
    DataTable,
    useColumnVisibility,
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
import { FiltersDialog } from '@/components/products/filters-dialog';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { moneyToDecimalString } from '@/engine/money';
import { qtyToDecimalString } from '@/engine/quantity';
import { useCan } from '@/hooks/use-can';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { DownloadError, downloadFile } from '@/lib/download';
import { exportItems, exportUrl, type ExportFormat } from '@/lib/export-items';
import { downloadRowsCsv, type CsvColumn } from '@/lib/rows-csv';
import { cn } from '@/lib/utils';

type Row = {
    id: number;
    uuid: string;
    name: string;
    name_bn: string | null;
    variant_label: string | null;
    sku: string;
    barcode: string | null;
    category: string | null;
    image_url: string | null;
    /** Current branch, milli-units. */
    on_hand: number;
    /** Aligned with `branches`. */
    stock: number[];
    total_stock: number;
    reorder_level: number;
    low_stock: boolean;
    cost_price: number | null;
    /** True when cost_price is the weighted average of the batches (the product has no cost price). */
    cost_is_average?: boolean;
    value: number | null;
};

type BranchColumn = {
    id: number;
    code: string;
    name: string;
    current: boolean;
};

const LIST = '/app/stock';
const adjustUrl = (uuid?: string) =>
    uuid
        ? `/app/adjustments/create?product=${uuid}`
        : '/app/adjustments/create';
const transferUrl = (uuid?: string) =>
    uuid ? `/app/transfers/create?product=${uuid}` : '/app/transfers/create';

/** "Date:" presets, the last stock movement at this branch (server filter `moved`). */
const RECENCY = ['', 'today', '7d', '30d', '90d', 'idle_90d'] as const;

export default function StockIndex({
    rows,
    meta,
    totals,
    branches,
    categories,
    valuation,
    canAdjust,
}: {
    rows: Row[];
    meta: TableMeta;
    totals: {
        stock: number[];
        total_stock: number;
        cost_price: number | null;
        value: number | null;
    };
    branches: BranchColumn[];
    categories: { id: number; name: string }[];
    valuation: number | null;
    branch: { id: number; name: string };
    canAdjust: boolean;
}) {
    const { t } = useT();
    const digits = useDigits();
    const can = useCan();
    const table = useServerTable();
    const filters = table.state.filters;
    const [moreOpen, setMoreOpen] = useState(false);
    const canCost = valuation !== null;
    const canTransfer = can('stock.transfer');

    useEffect(() => registerStockActions(), []);
    useHotkeys('list', [
        {
            id: 'stock.search',
            keys: '/',
            labelKey: 'hotkeys.search',
            handler: (e) => {
                e.preventDefault();
                document.getElementById('page-search')?.focus();
            },
        },
        {
            id: 'stock.new_adjustment',
            keys: 'n',
            labelKey: 'stock.adjustment',
            handler: () => canAdjust && router.visit(adjustUrl()),
        },
        {
            id: 'stock.new_transfer',
            keys: 't',
            labelKey: 'stock.transfer',
            handler: () => canTransfer && router.visit(transferUrl()),
        },
    ]);

    const nextPhase = () => toast.info(t('stock.next_phase'));

    const applyFilters = (next: Record<string, string>) =>
        router.get(
            LIST,
            buildTableQuery({
                ...table.state,
                filters: { ...filters, ...next },
                page: 1,
            }),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const columns = useMemo<DataColumn<Row>[]>(() => {
        const branchColumns: DataColumn<Row>[] = branches.map((b, i) => ({
            id: `stock_${b.id}`,
            headerKey: 'stock.on_hand',
            header: t('stock.col_branch_stock', { name: b.name }),
            align: 'center',
            ...(b.current ? { sortKey: 'on_hand' } : {}),
            cell: (r: Row) => {
                const qty = r.stock[i] ?? 0;

                return (
                    <span
                        className={cn(
                            'num font-mono',
                            b.current
                                ? r.low_stock || qty <= 0
                                    ? 'font-bold text-destructive'
                                    : 'font-bold text-foreground'
                                : 'text-muted-foreground',
                        )}
                    >
                        {digits.qty(qty)}
                    </span>
                );
            },
        }));

        return [
            {
                id: 'image',
                headerKey: 'products.col_photo',
                mobile: 'hidden',
                cell: (r) =>
                    r.image_url ? (
                        <img
                            src={r.image_url}
                            alt=""
                            loading="lazy"
                            className="size-8 rounded-md border object-cover"
                        />
                    ) : (
                        <span className="flex size-8 items-center justify-center rounded-md border bg-surface-sunken text-muted-foreground">
                            <ImageIcon
                                className="size-3.5"
                                aria-hidden="true"
                            />
                        </span>
                    ),
            },
            {
                id: 'sku',
                headerKey: 'products.col_sku',
                sortKey: 'sku',
                cell: (r) => (
                    <span className="num font-mono font-medium text-primary">
                        {r.sku}
                    </span>
                ),
            },
            {
                id: 'name',
                headerKey: 'products.col_product_name',
                sortKey: 'name',
                required: true,
                mobile: 'title',
                cell: (r) => (
                    <span className="font-semibold text-foreground">
                        {r.name}
                        {r.variant_label ? ` · ${r.variant_label}` : ''}
                    </span>
                ),
            },
            {
                id: 'category',
                headerKey: 'products.col_category',
                hidden: true,
                cell: (r) => (
                    <span className="text-muted-foreground">
                        {r.category ?? ''}
                    </span>
                ),
            },
            ...branchColumns,
            ...(branches.length > 1
                ? [
                      {
                          id: 'total_stock',
                          headerKey: 'stock.col_total',
                          align: 'center',
                          cell: (r: Row) => (
                              <span className="num font-mono font-bold text-primary">
                                  {digits.qty(r.total_stock)}
                              </span>
                          ),
                      } satisfies DataColumn<Row>,
                  ]
                : []),
            {
                id: 'reorder',
                headerKey: 'products.reorder_level',
                align: 'center',
                hidden: true,
                cell: (r) => (
                    <span className="num font-mono">
                        {digits.qty(r.reorder_level)}
                    </span>
                ),
            },
            ...(canCost
                ? [
                      {
                          id: 'cost_price',
                          headerKey: 'stock.col_unit_cost',
                          numeric: true,
                          cell: (r: Row) => (
                              <span
                                  className="font-medium text-muted-foreground"
                                  title={
                                      r.cost_is_average
                                          ? t('stock.avg_cost_hint')
                                          : undefined
                                  }
                              >
                                  {digits.money(r.cost_price ?? 0)}
                                  {r.cost_is_average ? (
                                      <sup className="ml-0.5 text-[9px] font-semibold">
                                          {t('stock.avg_cost')}
                                      </sup>
                                  ) : null}
                              </span>
                          ),
                      } satisfies DataColumn<Row>,
                      {
                          id: 'value',
                          headerKey: 'stock.col_value',
                          numeric: true,
                          cell: (r: Row) => (
                              <span className="font-bold text-foreground">
                                  {digits.money(r.value ?? 0)}
                              </span>
                          ),
                      } satisfies DataColumn<Row>,
                  ]
                : []),
            {
                id: 'actions',
                headerKey: 'common.actions',
                required: true,
                mobile: 'hidden',
                cell: (r) => (
                    <div className="flex items-center justify-end gap-1">
                        {canTransfer ? (
                            <Link
                                href={transferUrl(r.uuid)}
                                tabIndex={-1}
                                aria-label={`${t('stock.transfer_short')}: ${r.name}`}
                                onClick={(e) => e.stopPropagation()}
                                className="inline-flex h-6 items-center rounded-sm border bg-surface-sunken px-2.5 text-[10px] font-semibold hover:bg-muted [@media(pointer:coarse)]:min-h-11"
                            >
                                {t('stock.transfer_short')}
                            </Link>
                        ) : null}
                        {canAdjust ? (
                            <Link
                                href={adjustUrl(r.uuid)}
                                tabIndex={-1}
                                aria-label={`${t('stock.adjust_short')}: ${r.name}`}
                                onClick={(e) => e.stopPropagation()}
                                className="flex h-6 items-center rounded-sm border bg-surface-sunken px-2.5 text-[10px] font-semibold hover:border-warning [@media(pointer:coarse)]:min-h-11"
                            >
                                {t('stock.adjust_short')}
                            </Link>
                        ) : null}
                    </div>
                ),
            },
        ];
    }, [digits, branches, canCost, canAdjust, canTransfer, t]);
    const columnState = useColumnVisibility(columns);

    const csvColumns: CsvColumn<Row>[] = [
        { header: t('products.col_sku'), value: (r) => r.sku },
        { header: t('products.col_product_name'), value: (r) => r.name },
        ...branches.map((b, i) => ({
            header: t('stock.col_branch_stock', { name: b.name }),
            value: (r: Row) => qtyToDecimalString(r.stock[i] ?? 0),
        })),
        {
            header: t('stock.col_total'),
            value: (r) => qtyToDecimalString(r.total_stock),
        },
        ...(canCost
            ? [
                  {
                      header: t('stock.col_unit_cost'),
                      value: (r: Row) =>
                          moneyToDecimalString(r.cost_price ?? 0),
                  },
                  {
                      header: t('stock.col_value'),
                      value: (r: Row) => moneyToDecimalString(r.value ?? 0),
                  },
              ]
            : []),
    ];
    const exportRows = (list: Row[]) =>
        downloadRowsCsv('stock.csv', csvColumns, list);

    const exportAs = (format: ExportFormat) => {
        downloadFile(
            exportUrl('/app/stock', format, window.location.search),
        ).catch((error: unknown) =>
            toast.error(
                error instanceof DownloadError && error.status === 422
                    ? error.message
                    : t('table.export_failed'),
            ),
        );
    };
    const option = (value: string, key: string) => ({ value, label: t(key) });
    const healthOptions = [
        option('', 'stock.all'),
        option('low', 'stock.health_low'),
        option('out', 'status.out_of_stock'),
        option('ok', 'stock.health_ok'),
    ];

    return (
        <>
            <Head title={t('stock.title')} />
            <Page fill>
                <PageHeader
                    title={t('stock.hub_title')}
                    count={meta.total}
                    subtitle={t('stock.hub_subtitle')}
                    actions={
                        <>
                            {canAdjust ? (
                                <Button asChild variant="outline">
                                    <Link href={adjustUrl()}>
                                        <Pencil aria-hidden="true" />
                                        {t('stock.adjustment')}
                                    </Link>
                                </Button>
                            ) : null}
                            {canTransfer ? (
                                <Button asChild variant="outline">
                                    <Link href={transferUrl()}>
                                        <Truck aria-hidden="true" />
                                        {t('stock.transfer')}
                                    </Link>
                                </Button>
                            ) : null}
                            {can('stock.count') ? (
                                <Button
                                    type="button"
                                    aria-disabled="true"
                                    title={t('stock.next_phase')}
                                    className="cursor-not-allowed opacity-60"
                                    onClick={nextPhase}
                                >
                                    <Check aria-hidden="true" />
                                    {t('stock.stocktake')}
                                </Button>
                            ) : null}
                            <ColumnChooser
                                columns={columns}
                                visibility={columnState.visibility}
                                onChange={columnState.setVisibility}
                            />
                            {can('reports.export') ? (
                                <ExportMenu items={exportItems(t, exportAs)} />
                            ) : null}
                            <PrintButton />
                        </>
                    }
                />
                <TableToolbar
                    search={table.searchText}
                    onSearch={table.setSearch}
                    placeholderKey="stock.search"
                    view={{
                        value:
                            filters['status'] === 'low' ||
                            filters['status'] === 'out'
                                ? filters['status']
                                : '',
                        onChange: (v) => table.setFilter('status', v),
                        options: [
                            option('', 'stock.view_default'),
                            option('low', 'stock.view_low'),
                            option('out', 'status.out_of_stock'),
                        ],
                    }}
                    date={{
                        value: filters['moved'] ?? '',
                        onChange: (v) => table.setFilter('moved', v),
                        options: RECENCY.map((key) => ({
                            value: key,
                            label: t(`stock.moved_${key || 'all'}`),
                        })),
                    }}
                    chips={[
                        {
                            key: 'category',
                            scroll: true,
                            label: t('products.col_category'),
                            value: filters['category'] ?? '',
                            options: [
                                {
                                    value: '',
                                    label: t('products.all_categories'),
                                },
                                ...categories.map((c) => ({
                                    value: String(c.id),
                                    label: c.name,
                                })),
                            ],
                            onChange: (v) => table.setFilter('category', v),
                        },
                    ]}
                    moreFilters={{
                        count: (filters['status'] ?? '') !== '' ? 1 : 0,
                        onOpen: () => setMoreOpen(true),
                    }}
                />
                <DataTable<Row>
                    entity="stock"
                    caption={t('stock.hub_title')}
                    rows={rows}
                    columns={columns}
                    meta={meta}
                    getRowId={(r) => r.uuid}
                    rowHref={(r) => `${LIST}/${r.uuid}/movements`}
                    handlers={{
                        'row.edit': (r) =>
                            router.visit(`/app/products/${r.uuid}/edit`),
                    }}
                    bulkActions={
                        can('reports.export')
                            ? [
                                  {
                                      id: 'export',
                                      labelKey: 'stock.bulk_export',
                                      icon: FileText,
                                      run: exportRows,
                                  },
                              ]
                            : []
                    }
                    sort={table.state.sort}
                    onSort={table.toggleSort}
                    onPage={table.setPage}
                    onPerPage={table.setPerPage}
                    loading={table.loading}
                    chooser="none"
                    visibility={columnState.visibility}
                    onVisibilityChange={columnState.setVisibility}
                    fill
                    totals={{
                        cells: {
                            image: '',
                            sku: t('table.total', { n: digits.n(meta.total) }),
                            ...Object.fromEntries(
                                branches.map((b, i) => [
                                    `stock_${b.id}`,
                                    digits.qty(totals.stock[i] ?? 0),
                                ]),
                            ),
                            total_stock: digits.qty(totals.total_stock),
                            ...(totals.cost_price === null
                                ? {}
                                : {
                                      cost_price: digits.money(
                                          totals.cost_price,
                                      ),
                                  }),
                            ...(totals.value === null
                                ? {}
                                : { value: digits.money(totals.value) }),
                        },
                    }}
                    empty={
                        <EmptyState
                            icon={Boxes}
                            title={t('stock.empty')}
                            description={t('table.empty_hint')}
                        />
                    }
                />
            </Page>

            <FiltersDialog
                open={moreOpen}
                onOpenChange={setMoreOpen}
                values={filters}
                onApply={applyFilters}
                fields={[
                    {
                        key: 'status',
                        label: t('stock.health'),
                        options: healthOptions,
                    },
                ]}
            />
        </>
    );
}
