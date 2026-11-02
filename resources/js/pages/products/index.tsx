import { Head, Link, router } from '@inertiajs/react';
import { Boxes, Plus, Tags } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { registerProductActions } from '@/command/catalog-actions';
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
    name_bn: string | null;
    sku: string;
    barcode: string | null;
    variant_label: string | null;
    family_id: number | null;
    family_uuid: string | null;
    category: string | null;
    brand: string | null;
    sale_price: number;
    cost_price: number | null;
    on_hand: number | null;
    reorder_level: number;
    low_stock: boolean;
    is_active: boolean;
    image_url: string | null;
};

type Option = { id: number; name: string };

export default function ProductsIndex({
    rows,
    meta,
    categories,
    brands,
    canViewCost,
}: {
    rows: Row[];
    meta: TableMeta;
    categories: Option[];
    brands: Option[];
    canViewCost: boolean;
}) {
    const { t } = useT();
    const digits = useDigits();
    const can = useCan();
    const table = useServerTable();
    const [toDelete, setToDelete] = useState<Row | null>(null);

    useEffect(() => registerProductActions(), []);
    useHotkeys('list', [
        {
            id: 'products.new',
            keys: 'n',
            labelKey: 'hotkeys.new',
            handler: () => can('products.create') && router.visit('/app/products/create'),
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

    const columns = useMemo<DataColumn<Row>[]>(
        () => [
            {
                id: 'name',
                headerKey: 'products.col_name',
                sortKey: 'name',
                required: true,
                mobile: 'title',
                cell: (r) => (
                    <div className="flex items-center gap-3">
                        {r.image_url ? (
                            <img
                                src={r.image_url}
                                alt=""
                                className="size-9 rounded-md object-cover"
                            />
                        ) : (
                            <span className="flex size-9 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                <Boxes className="size-4" aria-hidden="true" />
                            </span>
                        )}
                        <span className="min-w-0">
                            <span className="block truncate font-medium">
                                {r.name}
                                {r.variant_label ? ` · ${r.variant_label}` : ''}
                            </span>
                            {!r.is_active ? (
                                <span className="text-xs text-muted-foreground">
                                    {t('products.inactive')}
                                </span>
                            ) : null}
                        </span>
                    </div>
                ),
            },
            { id: 'sku', headerKey: 'products.col_sku', sortKey: 'sku', cell: (r) => <span className="num">{r.sku}</span> },
            { id: 'barcode', headerKey: 'products.col_barcode', hidden: true, cell: (r) => <span className="num">{r.barcode ?? ''}</span> },
            { id: 'category', headerKey: 'products.col_category', cell: (r) => r.category ?? '' },
            { id: 'brand', headerKey: 'products.col_brand', hidden: true, cell: (r) => r.brand ?? '' },
            { id: 'sale_price', headerKey: 'products.col_price', sortKey: 'sale_price', numeric: true, cell: (r) => digits.money(r.sale_price) },
            ...(canViewCost
                ? [
                      {
                          id: 'cost_price',
                          headerKey: 'products.col_cost',
                          sortKey: 'cost_price',
                          numeric: true,
                          cell: (r: Row) => (r.cost_price === null ? '' : digits.money(r.cost_price)),
                      } satisfies DataColumn<Row>,
                  ]
                : []),
            {
                id: 'on_hand',
                headerKey: 'products.col_stock',
                sortKey: 'on_hand',
                numeric: true,
                cell: (r) =>
                    r.on_hand === null ? (
                        <span className="text-muted-foreground">{t('products.not_tracked')}</span>
                    ) : (
                        <span className="inline-flex items-center justify-end gap-2">
                            {r.low_stock ? <StatusBadge status="low_stock" /> : null}
                            {digits.qty(r.on_hand)}
                        </span>
                    ),
            },
        ],
        [digits, canViewCost, t],
    );

    const toggleActive = (row: Row) =>
        router.patch(`/app/products/${row.uuid}/active`, { is_active: !row.is_active }, { preserveScroll: true });

    const filterOptions = (list: Option[]) => list.map((o) => ({ value: String(o.id), label: o.name }));

    return (
        <>
            <Head title={t('products.title')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-2xl font-bold">{t('products.title')}</h1>
                    <div className="flex gap-2">
                        {can('products.update') ? (
                            <>
                                <Button asChild variant="outline" className="min-h-11">
                                    <Link href="/app/products/categories">
                                        <Tags aria-hidden="true" /> {t('categories.title')}
                                    </Link>
                                </Button>
                                <Button asChild variant="outline" className="min-h-11">
                                    <Link href="/app/products/brands">{t('brands.title')}</Link>
                                </Button>
                            </>
                        ) : null}
                        {can('products.create') ? (
                            <Button asChild className="min-h-11">
                                <Link href="/app/products/create">
                                    <Plus aria-hidden="true" /> {t('products.new')}
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                </div>

                <TableToolbar
                    search={table.searchText}
                    onSearch={table.setSearch}
                    placeholderKey="products.search"
                    filters={[
                        { key: 'category', labelKey: 'products.all_categories', value: table.state.filters['category'] ?? '', options: filterOptions(categories), onChange: (v) => table.setFilter('category', v) },
                        { key: 'brand', labelKey: 'products.all_brands', value: table.state.filters['brand'] ?? '', options: filterOptions(brands), onChange: (v) => table.setFilter('brand', v) },
                        {
                            key: 'status',
                            labelKey: 'products.all_statuses',
                            value: table.state.filters['status'] ?? '',
                            options: [
                                { value: 'active', label: t('products.status_active') },
                                { value: 'inactive', label: t('products.inactive') },
                                { value: 'low', label: t('status.low_stock') },
                            ],
                            onChange: (v) => table.setFilter('status', v),
                        },
                    ]}
                />

                <DataTable<Row>
                    entity="product"
                    caption={t('products.title')}
                    rows={rows}
                    columns={columns}
                    meta={meta}
                    getRowId={(r) => r.uuid}
                    rowHref={(r) => `/app/products/${r.uuid}/edit`}
                    handlers={{
                        'row.edit': (r) => router.visit(`/app/products/${r.uuid}/edit`),
                        'row.delete': (r) => setToDelete(r),
                        'product.toggle_active': toggleActive,
                    }}
                    sort={table.state.sort}
                    onSort={table.toggleSort}
                    onPage={table.setPage}
                    loading={table.loading}
                    selectable={false}
                    empty={
                        <EmptyState
                            icon={Boxes}
                            title={t('products.empty')}
                            description={t('products.empty_hint')}
                            action={
                                can('products.create') ? (
                                    <Button asChild>
                                        <Link href="/app/products/create">{t('products.new')}</Link>
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>

            <ConfirmDialog
                open={toDelete !== null}
                onOpenChange={(open) => !open && setToDelete(null)}
                title={t('products.delete_title')}
                description={t('products.delete_hint')}
                destructive
                recordName={toDelete?.name ?? ''}
                onConfirm={() => {
                    if (toDelete) {
                        router.delete(`/app/products/${toDelete.uuid}`, { preserveScroll: true });
                    }
                }}
            />
        </>
    );
}
