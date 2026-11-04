import { Head, router, usePage } from '@inertiajs/react';
import {
    Barcode,
    Boxes,
    FileText,
    ImageIcon,
    Pencil,
    Plus,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { registerProductActions } from '@/command/catalog-actions';
import { ConfirmDialog } from '@/components/confirm-dialog';
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
import { CatalogTabs } from '@/components/products/catalog-tabs';
import { FiltersDialog } from '@/components/products/filters-dialog';
import type { Family } from '@/components/products/group-dialog';
import {
    ProductDialog,
    type ProductDetail,
    type ProductFormOptions,
} from '@/components/products/product-dialog';
import { StatusBadge } from '@/components/status-badge';
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
    sku: string;
    barcode: string | null;
    variant_label: string | null;
    item_type: 'finished' | 'raw_material';
    unit: string | null;
    family_id: number | null;
    family_uuid: string | null;
    category: string | null;
    brand: string | null;
    sale_price: number;
    cost_price: number | null;
    /** Current branch, milli-units; null = not stock-tracked. */
    on_hand: number | null;
    /** Aligned with `branches`; null = not stock-tracked. */
    stock: number[] | null;
    total_stock: number | null;
    track_stock: boolean;
    reorder_level: number;
    low_stock: boolean;
    is_active: boolean;
    image_url: string | null;
};

type Option = { id: number; name: string };
type BranchColumn = {
    id: number;
    code: string;
    name: string;
    current: boolean;
};

type Props = {
    rows: Row[];
    meta: TableMeta;
    totals: {
        sale_price: number;
        cost_price: number | null;
        stock: number[];
        total_stock: number;
    };
    branches: BranchColumn[];
    categories: Option[];
    brands: Option[];
    canViewCost: boolean;
    /** Set on /app/products/create and /{uuid}/edit: the list renders with the product dialog open. */
    editor: { product: ProductDetail | null; families: Family[] } | null;
    form: ProductFormOptions | null;
};

const LIST = '/app/products';
/** True once the list (without the dialog) has rendered in this tab: closing the dialog then goes Back. */
let listSeen = false;

function stockState(r: Row): string {
    if (!r.is_active) {
        return 'inactive';
    }
    if (r.on_hand === null) {
        return 'none';
    }
    if (r.on_hand <= 0) {
        return 'out_of_stock';
    }

    return r.low_stock ? 'low_stock' : 'in_stock';
}

export default function ProductsIndex({
    rows,
    meta,
    totals,
    branches,
    categories,
    brands,
    canViewCost,
    editor,
    form,
}: Props) {
    const { t } = useT();
    const digits = useDigits();
    const can = useCan();
    const table = useServerTable();
    const url = usePage().url;
    const query = url.includes('?') ? url.slice(url.indexOf('?')) : '';
    const filters = table.state.filters;
    const [toDelete, setToDelete] = useState<Row | null>(null);
    const [moreOpen, setMoreOpen] = useState(false);

    useEffect(() => registerProductActions(), []);
    useEffect(() => {
        if (editor === null) {
            listSeen = true;
        }
    }, [editor]);

    const openDialog = (path: string) =>
        router.visit(`${path}${query}`, {
            preserveState: true,
            preserveScroll: true,
        });
    const openNew = () => openDialog(`${LIST}/create`);
    const editHref = (r: Row) => `${LIST}/${r.uuid}/edit${query}`;
    const closeDialog = () => {
        if (listSeen && window.history.length > 1) {
            window.history.back();

            return;
        }
        router.visit(`${LIST}${query}`, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

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

    useHotkeys('list', [
        {
            id: 'products.new',
            keys: 'n',
            labelKey: 'hotkeys.new',
            handler: () => can('products.create') && openNew(),
        },
        {
            id: 'products.search',
            keys: '/',
            labelKey: 'hotkeys.search',
            handler: (event) => {
                event.preventDefault();
                document.getElementById('page-search')?.focus();
            },
        },
    ]);

    const labelSoon = () => toast.info(t('products.label_soon'));

    const columns = useMemo<DataColumn<Row>[]>(() => {
        const branchColumns: DataColumn<Row>[] = branches.map((b, i) => ({
            id: `stock_${b.id}`,
            headerKey: 'products.col_stock',
            header: t('products.col_stock_branch', { name: b.name }),
            align: 'center',
            ...(b.current ? { sortKey: 'on_hand' } : {}),
            cell: (r: Row) => {
                const qty = r.stock?.[i];
                if (qty === undefined) {
                    return <span className="text-muted-foreground">—</span>;
                }

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
                    <span className="num font-mono font-semibold text-primary">
                        {r.sku}
                    </span>
                ),
            },
            {
                id: 'barcode',
                headerKey: 'products.col_barcode',
                sortKey: 'barcode',
                cell: (r) => (
                    <span className="num font-mono text-muted-foreground">
                        {r.barcode ?? ''}
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
                    <div className="min-w-0">
                        <div className="font-semibold text-foreground">
                            {r.name}
                            {r.variant_label ? ` · ${r.variant_label}` : ''}
                            {r.item_type === 'raw_material' ? (
                                <span className="ml-1.5 rounded-sm bg-info-soft px-1.5 py-0.5 align-middle text-[10px] font-bold text-info">
                                    {t('products.raw_material_chip')}
                                </span>
                            ) : null}
                        </div>
                        {r.name_bn ? (
                            <div
                                lang="bn"
                                className="font-bangla text-[10px] text-muted-foreground"
                            >
                                {r.name_bn}
                            </div>
                        ) : null}
                    </div>
                ),
            },
            {
                id: 'category',
                headerKey: 'products.col_category',
                cell: (r) => (
                    <span
                        title={r.category ?? undefined}
                        className="block max-w-28 truncate text-muted-foreground"
                    >
                        {r.category ?? ''}
                    </span>
                ),
            },
            {
                id: 'brand',
                headerKey: 'products.col_brand',
                hidden: true,
                cell: (r) => r.brand ?? '',
            },
            ...(canViewCost
                ? [
                      {
                          id: 'cost_price',
                          headerKey: 'products.col_cost_price',
                          sortKey: 'cost_price',
                          numeric: true,
                          cell: (r: Row) => (
                              <span className="font-medium text-muted-foreground">
                                  {r.cost_price === null
                                      ? ''
                                      : digits.money(r.cost_price)}
                              </span>
                          ),
                      } satisfies DataColumn<Row>,
                  ]
                : []),
            {
                id: 'sale_price',
                headerKey: 'products.col_retail_price',
                sortKey: 'sale_price',
                numeric: true,
                cell: (r) => (
                    <span className="font-bold text-foreground">
                        {digits.money(r.sale_price)}
                    </span>
                ),
            },
            ...branchColumns,
            ...(branches.length > 1
                ? [
                      {
                          id: 'total_stock',
                          headerKey: 'products.col_total_stock',
                          align: 'center',
                          cell: (r: Row) =>
                              r.total_stock === null ? (
                                  <span className="text-muted-foreground">
                                      —
                                  </span>
                              ) : (
                                  <span className="num font-mono font-bold text-primary">
                                      {digits.qty(r.total_stock)}
                                  </span>
                              ),
                      } satisfies DataColumn<Row>,
                  ]
                : []),
            {
                id: 'status',
                headerKey: 'common.status',
                align: 'center',
                cell: (r) => {
                    const state = stockState(r);

                    return state === 'none' ? (
                        <StatusBadge
                            status="none"
                            label={t('products.not_tracked')}
                        />
                    ) : (
                        <StatusBadge status={state} />
                    );
                },
            },
            {
                id: 'actions',
                headerKey: 'common.actions',
                required: true,
                mobile: 'hidden',
                cell: (r) => (
                    // Buttons only; the row's own keys (Enter = edit) stay on the row.
                    <div className="flex items-center justify-end gap-1">
                        <button
                            type="button"
                            tabIndex={-1}
                            title={t('products.print_label')}
                            aria-label={`${t('products.print_label')}: ${r.name}`}
                            onClick={(e) => {
                                e.stopPropagation();
                                toast.info(t('products.label_soon'));
                            }}
                            className="rounded-sm p-1 text-muted-foreground hover:bg-surface-sunken hover:text-primary [@media(pointer:coarse)]:p-3"
                        >
                            <Barcode className="size-3.5" aria-hidden="true" />
                        </button>
                        {can('products.update') ? (
                            <button
                                type="button"
                                tabIndex={-1}
                                title={t('products.edit')}
                                aria-label={`${t('products.edit')}: ${r.name}`}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    router.visit(
                                        `${LIST}/${r.uuid}/edit${query}`,
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                                className="rounded-sm p-1 text-muted-foreground hover:bg-surface-sunken hover:text-foreground [@media(pointer:coarse)]:p-3"
                            >
                                <Pencil
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                            </button>
                        ) : null}
                    </div>
                ),
            },
        ];
    }, [digits, canViewCost, t, branches, can, query]);
    const columnState = useColumnVisibility(columns);

    const toggleActive = (row: Row) =>
        router.patch(
            `${LIST}/${row.uuid}/active`,
            { is_active: !row.is_active },
            { preserveScroll: true },
        );

    const csvColumns: CsvColumn<Row>[] = [
        { header: t('products.col_sku'), value: (r) => r.sku },
        { header: t('products.col_barcode'), value: (r) => r.barcode ?? '' },
        { header: t('products.col_product_name'), value: (r) => r.name },
        { header: t('products.name_bn'), value: (r) => r.name_bn ?? '' },
        { header: t('products.col_category'), value: (r) => r.category ?? '' },
        ...(canViewCost
            ? [
                  {
                      header: t('products.col_cost_price'),
                      value: (r: Row) =>
                          r.cost_price === null
                              ? ''
                              : moneyToDecimalString(r.cost_price),
                  },
              ]
            : []),
        {
            header: t('products.col_retail_price'),
            value: (r) => moneyToDecimalString(r.sale_price),
        },
        ...branches.map((b, i) => ({
            header: t('products.col_stock_branch', { name: b.name }),
            value: (r: Row) => {
                const qty = r.stock?.[i];

                return qty === undefined ? '' : qtyToDecimalString(qty);
            },
        })),
    ];
    const exportRows = (list: Row[]) =>
        downloadRowsCsv('products.csv', csvColumns, list);

    const exportAs = (format: ExportFormat) => {
        downloadFile(
            exportUrl('/app/products', format, window.location.search),
        ).catch((error: unknown) =>
            toast.error(
                error instanceof DownloadError && error.status === 422
                    ? error.message
                    : t('table.export_failed'),
            ),
        );
    };
    const viewValue =
        filters['status'] === 'low'
            ? 'low'
            : filters['status'] === 'inactive'
              ? 'inactive'
              : filters['stock'] === 'out'
                ? 'out'
                : '';
    const setView = (value: string) =>
        applyFilters({
            status: value === 'low' || value === 'inactive' ? value : '',
            stock: value === 'out' ? 'out' : '',
        });
    const moreCount = ['brand', 'stock', 'status'].filter(
        (k) => (filters[k] ?? '') !== '',
    ).length;

    const option = (value: string, key: string) => ({ value, label: t(key) });

    return (
        <>
            <Head title={t('products.title')} />
            <Page fill>
                <PageHeader
                    title={t('products.hub_title')}
                    subtitle={t('products.hub_subtitle')}
                    actions={
                        can('products.create') ? (
                            <Button type="button" onClick={openNew}>
                                <Plus aria-hidden="true" />
                                {t('products.add')}
                            </Button>
                        ) : null
                    }
                />
                <CatalogTabs active="products" />

                <section
                    aria-labelledby="catalog-list-title"
                    className="flex min-h-0 flex-1 flex-col gap-3 md:px-4 md:pt-4"
                >
                    <PageHeader
                        as="h2"
                        title={
                            <span id="catalog-list-title">
                                {t('products.list_title')}
                            </span>
                        }
                        count={meta.total}
                        subtitle={t('products.list_subtitle')}
                        actions={
                            <>
                                <ColumnChooser
                                    columns={columns}
                                    visibility={columnState.visibility}
                                    onChange={columnState.setVisibility}
                                />
                                {can('reports.export') ? (
                                    <ExportMenu
                                        items={exportItems(t, exportAs)}
                                    />
                                ) : null}
                                <PrintButton />
                            </>
                        }
                    />

                    <TableToolbar
                        search={table.searchText}
                        onSearch={table.setSearch}
                        placeholderKey="products.search_hub"
                        view={{
                            value: viewValue,
                            onChange: setView,
                            options: [
                                option('', 'products.view_default'),
                                option('low', 'products.view_low'),
                                option('out', 'products.view_out'),
                                option('inactive', 'products.view_inactive'),
                            ],
                        }}
                        date={{
                            value: filters['added'] ?? '',
                            onChange: (v) => table.setFilter('added', v),
                            options: [
                                option('', 'products.added_all'),
                                option('today', 'products.added_today'),
                                option('7d', 'products.added_7d'),
                                option('30d', 'products.added_30d'),
                                option('90d', 'products.added_90d'),
                            ],
                        }}
                        chips={[
                            {
                                key: 'category',
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
                            {
                                key: 'type',
                                label: t('products.item_type'),
                                value: filters['type'] ?? '',
                                options: [
                                    option('', 'products.type_all'),
                                    option(
                                        'finished',
                                        'products.item_finished',
                                    ),
                                    option(
                                        'raw_material',
                                        'products.item_raw_material',
                                    ),
                                ],
                                onChange: (v) => table.setFilter('type', v),
                            },
                        ]}
                        moreFilters={{
                            count: moreCount,
                            onOpen: () => setMoreOpen(true),
                        }}
                    />

                    <DataTable<Row>
                        entity="product"
                        caption={t('products.list_title')}
                        rows={rows}
                        columns={columns}
                        meta={meta}
                        getRowId={(r) => r.uuid}
                        {...(can('products.update')
                            ? { rowHref: editHref }
                            : {})}
                        handlers={{
                            'row.edit': (r) =>
                                openDialog(`${LIST}/${r.uuid}/edit`),
                            'row.delete': (r) => setToDelete(r),
                            'product.toggle_active': toggleActive,
                        }}
                        bulkActions={[
                            {
                                id: 'labels',
                                labelKey: 'products.bulk_labels',
                                icon: Barcode,
                                run: labelSoon,
                            },
                            ...(can('reports.export')
                                ? [
                                      {
                                          id: 'export',
                                          labelKey: 'products.bulk_export',
                                          icon: FileText,
                                          run: exportRows,
                                      },
                                  ]
                                : []),
                        ]}
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
                                sku: t('table.total', {
                                    n: digits.n(meta.total),
                                }),
                                ...(totals.cost_price === null
                                    ? {}
                                    : {
                                          cost_price: digits.money(
                                              totals.cost_price,
                                          ),
                                      }),
                                sale_price: digits.money(totals.sale_price),
                                ...Object.fromEntries(
                                    branches.map((b, i) => [
                                        `stock_${b.id}`,
                                        digits.qty(totals.stock[i] ?? 0),
                                    ]),
                                ),
                                total_stock: digits.qty(totals.total_stock),
                            },
                        }}
                        empty={
                            <EmptyState
                                icon={Boxes}
                                title={t('products.empty')}
                                description={t('products.empty_hint')}
                                action={
                                    can('products.create') ? (
                                        <Button type="button" onClick={openNew}>
                                            {t('products.add')}
                                        </Button>
                                    ) : undefined
                                }
                            />
                        }
                    />
                </section>
            </Page>

            {editor !== null && form !== null ? (
                <ProductDialog
                    key={editor.product?.uuid ?? 'new'}
                    product={editor.product}
                    families={editor.families}
                    options={form}
                    canViewCost={canViewCost}
                    canCreateBrand={can('products.create')}
                    listQuery={query}
                    onClose={closeDialog}
                />
            ) : null}

            <FiltersDialog
                open={moreOpen}
                onOpenChange={setMoreOpen}
                values={filters}
                onApply={applyFilters}
                fields={[
                    {
                        key: 'brand',
                        label: t('products.col_brand'),
                        options: [
                            { value: '', label: t('products.all_brands') },
                            ...brands.map((b) => ({
                                value: String(b.id),
                                label: b.name,
                            })),
                        ],
                    },
                    {
                        key: 'stock',
                        label: t('products.stock_level'),
                        options: [
                            option('', 'products.stock_all'),
                            option('in', 'status.in_stock'),
                            option('low', 'status.low_stock'),
                            option('out', 'status.out_of_stock'),
                        ],
                    },
                    {
                        key: 'status',
                        label: t('common.status'),
                        options: [
                            option('', 'products.all_statuses'),
                            option('active', 'products.status_active'),
                            option('inactive', 'products.inactive'),
                            option('low', 'products.view_low'),
                        ],
                    },
                ]}
            />

            <ConfirmDialog
                open={toDelete !== null}
                onOpenChange={(open) => !open && setToDelete(null)}
                title={t('products.delete_title')}
                description={t('products.delete_hint')}
                destructive
                recordName={toDelete?.name ?? ''}
                onConfirm={() => {
                    if (toDelete) {
                        router.delete(`${LIST}/${toDelete.uuid}${query}`, {
                            preserveScroll: true,
                        });
                    }
                }}
            />
        </>
    );
}
