import { Head, Link, router } from '@inertiajs/react';
import { CreditCard, Eye, IdCard, Plus, Users } from 'lucide-react';
import { toast } from 'sonner';
import { useMemo, useRef, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { CustomerDialog } from '@/components/customers/customer-dialog';
import { MergeDialog } from '@/components/customers/merge-dialog';
import {
    ColumnChooser,
    DataTable,
    useColumnVisibility,
    type BulkAction,
    type DataColumn,
} from '@/components/data-table/data-table';
import {
    buildTableQuery,
    useServerTable,
} from '@/components/data-table/server-table';
import { EmptyState } from '@/components/empty-state';
import { NativeSelect } from '@/components/native-select';
import {
    ExportMenu,
    Page,
    PageHeader,
    PrintButton,
} from '@/components/page-header';
import { rowOpenHandlers } from '@/components/sales/list-tools';
import { StatusBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeyScope, useHotkeys } from '@/hotkeys/use-hotkeys';
import { useCan } from '@/hooks/use-can';
import { useT } from '@/i18n';
import { cardPrintUrl, customersExportUrl } from '@/command/customer-actions';
import { DownloadError, downloadFile } from '@/lib/download';
import { exportItems, type ExportFormat } from '@/lib/export-items';
import { displayPhone } from '@/lib/phone';
import { geoLabel, geoName, type GeoPlace } from '@/lib/geo';
import { cn } from '@/lib/utils';
import type {
    CustomerRow,
    CustomerTotals,
    GroupOption,
    TableMetaProp,
} from '@/types/customers';

type Props = {
    customers: CustomerRow[];
    meta: TableMetaProp;
    totals: CustomerTotals;
    groups: GroupOption[];
    districts: GeoPlace[];
    can: Record<string, boolean>;
    /** `create` = opened as /app/customers/create (deep link). */
    dialog: 'create' | null;
};

/** Up to this many customer groups show as chips (mockup); more fall back to a select. */
const GROUP_CHIPS_MAX = 6;

/** "More filters" keys (the drawer); View, Date and group chips sit in the toolbar. */
const MORE_FILTERS = ['balance', 'card', 'district', 'pin'] as const;

/**
 * Customers & Members (baseline mockup, render_customers_messaging.py "SCREEN 6"): the 10 mockup
 * columns, View / Date / group chips / More filters, Columns · Export · Print, totals row and rows per
 * page. Add and edit open the customer dialog in place.
 */
export default function CustomersIndex({
    customers,
    meta,
    totals,
    groups,
    districts,
    can,
    dialog,
}: Props) {
    const { t, locale } = useT();
    const permitted = useCan();
    const digits = useDigits();
    const table = useServerTable({ only: ['customers', 'meta', 'totals'] });
    const searchRef = useRef<HTMLInputElement>(null);
    const [deactivating, setDeactivating] = useState<CustomerRow | null>(null);
    const [merging, setMerging] = useState<CustomerRow | null>(null);
    const [adding, setAdding] = useState(dialog === 'create');
    const [moreOpen, setMoreOpen] = useState(false);
    const filters = table.state.filters;
    const discountByGroup = useMemo(
        () => new Map(groups.map((g) => [g.name, g.discount_bp])),
        [groups],
    );

    const openAdd = () => {
        if (can['create']) {
            setAdding(true);
        }
    };
    const closeAdd = () => {
        setAdding(false);
        if (dialog === 'create') {
            // Leave the deep link so a reload does not reopen the dialog.
            router.get('/app/customers', buildTableQuery(table.state), {
                replace: true,
                preserveState: true,
                preserveScroll: true,
            });
        }
    };
    // The drawer is a modal surface: the list keys pause while it is open.
    useHotkeyScope('dialog', moreOpen);

    const canExport = permitted('customers.export');
    const exportAs = (format: ExportFormat) => {
        if (!canExport) {
            return;
        }
        downloadFile(
            customersExportUrl(buildTableQuery(table.state), format),
        ).catch((error: unknown) =>
            toast.error(
                error instanceof DownloadError && error.status === 422
                    ? error.message
                    : t('table.export_failed'),
            ),
        );
    };

    useHotkeys('list', [
        {
            id: 'list.new',
            keys: 'n',
            labelKey: 'hotkeys.new',
            handler: openAdd,
        },
        {
            id: 'list.search',
            keys: '/',
            labelKey: 'hotkeys.search',
            handler: (event) => {
                event.preventDefault();
                searchRef.current?.focus();
            },
        },
        {
            id: 'customers.export',
            keys: 'shift+e',
            labelKey: 'table.export_xlsx',
            handler: () => exportAs('xlsx'),
        },
        {
            id: 'customers.more_filters',
            keys: 'shift+f',
            labelKey: 'table.more_filters',
            handler: () => setMoreOpen(true),
        },
    ]);

    const issueCards = (rows: CustomerRow[]) =>
        router.post(
            '/app/membership-cards/issue',
            { customers: rows.map((r) => r.uuid) },
            { preserveScroll: true },
        );

    const handlers = useMemo(
        () => ({
            ...(can['update']
                ? {
                      'row.edit': (row: CustomerRow) =>
                          router.visit(`/app/customers/${row.uuid}/edit`),
                  }
                : {}),
            ...(can['issue_card']
                ? {
                      'customer.issue_card': (row: CustomerRow) =>
                          issueCards([row]),
                  }
                : {}),
            ...(can['delete']
                ? {
                      'customer.deactivate': setDeactivating,
                      'customer.merge': setMerging,
                  }
                : {}),
        }),
        [can],
    );

    const columns: DataColumn<CustomerRow>[] = [
        {
            id: 'customer_no',
            headerKey: 'customers.col.no',
            sortKey: 'customer_no',
            cell: (r) => (
                <span className="num font-mono font-semibold text-primary">
                    {r.customer_no ? digits.n(r.customer_no) : '-'}
                </span>
            ),
        },
        {
            id: 'name',
            headerKey: 'customers.col.name',
            sortKey: 'name',
            required: true,
            mobile: 'title',
            cell: (r) => (
                <div className="min-w-0">
                    <Link
                        href={`/app/customers/${r.uuid}`}
                        className="touch-link font-semibold text-foreground hover:underline"
                    >
                        {r.name}
                    </Link>
                    {r.name_bn ? (
                        <div
                            lang="bn"
                            className="text-[10px] text-muted-foreground"
                        >
                            {r.name_bn}
                        </div>
                    ) : null}
                    {r.shop_name ? (
                        <div className="max-w-56 truncate text-[10px] text-muted-foreground">
                            {r.shop_name}
                        </div>
                    ) : null}
                </div>
            ),
        },
        {
            id: 'phone',
            headerKey: 'customers.col.phone',
            sortKey: 'phone',
            cell: (r) => (
                <span className="num font-mono text-muted-foreground">
                    {r.phone ? digits.n(displayPhone(r.phone)) : '-'}
                </span>
            ),
        },
        {
            id: 'location',
            headerKey: 'customers.col.location',
            sortKey: 'area',
            cell: (r) => {
                const place = geoLabel(r.location, locale);
                const line = place || r.address_line || r.area;
                const sub = [
                    place ? r.area || r.address_line : null,
                    !place && r.address_line ? r.area : null,
                    r.post_code ? digits.n(r.post_code) : null,
                ]
                    .filter(Boolean)
                    .join(' · ');

                return line || sub ? (
                    <div className="min-w-0">
                        <div className="max-w-50 truncate font-medium text-foreground">
                            {line || '-'}
                        </div>
                        {sub ? (
                            <div className="max-w-50 truncate text-[10px] text-muted-foreground">
                                {sub}
                            </div>
                        ) : null}
                    </div>
                ) : (
                    <span className="text-muted-foreground">-</span>
                );
            },
        },
        {
            id: 'group',
            headerKey: 'customers.col.group',
            cell: (r) =>
                r.group ? (
                    <StatusBadge
                        status="group"
                        label={r.group}
                        tone={
                            (discountByGroup.get(r.group) ?? 0) > 0
                                ? 'accent'
                                : 'neutral'
                        }
                    />
                ) : (
                    <span className="text-muted-foreground">-</span>
                ),
        },
        {
            id: 'balance',
            headerKey: 'customers.col.balance',
            sortKey: 'balance',
            numeric: true,
            cell: (r) => (
                <span
                    className={cn(
                        'font-bold',
                        r.balance > 0 ? 'text-destructive' : 'text-success',
                    )}
                >
                    {digits.money(r.balance)}
                </span>
            ),
        },
        {
            id: 'credit_limit',
            headerKey: 'customers.col.credit_limit',
            sortKey: 'credit_limit',
            numeric: true,
            cell: (r) => (
                <span className="font-semibold text-muted-foreground">
                    {r.credit_limit === null
                        ? '-'
                        : digits.money(r.credit_limit)}
                </span>
            ),
        },
        {
            id: 'card',
            headerKey: 'customers.col.card',
            cell: (r) => (
                <StatusBadge
                    shape="tag"
                    status={
                        r.card_status === 'active'
                            ? 'active'
                            : r.card_status === 'lost'
                              ? 'lost'
                              : 'none'
                    }
                    tone={r.card_status === 'active' ? 'success' : 'neutral'}
                    label={
                        r.card_status
                            ? t(`cards.status.${r.card_status}`)
                            : t('status.none')
                    }
                />
            ),
        },
        {
            id: 'pin',
            headerKey: 'customers.col.pin',
            cell: (r) => (
                <StatusBadge
                    shape="tag"
                    status={
                        r.pin_status === 'pending' ? 'not_set' : r.pin_status
                    }
                    tone={
                        r.pin_status === 'set'
                            ? 'success'
                            : r.pin_status === 'locked'
                              ? 'danger'
                              : 'warning'
                    }
                    label={t(`customers.pin_status.${r.pin_status}`)}
                />
            ),
        },
        {
            id: 'last_purchase_at',
            headerKey: 'customers.col.last_purchase',
            sortKey: 'last_purchase_at',
            hidden: true,
            cell: (r) =>
                r.last_purchase_at
                    ? digits.n(r.last_purchase_at.slice(0, 10))
                    : t('customers.never'),
        },
        {
            id: 'actions',
            headerKey: 'common.actions',
            required: true,
            mobile: 'hidden',
            cell: (r) => (
                <div className="flex items-center justify-end gap-1">
                    {can['issue_card'] && r.card_status === 'active' ? (
                        <a
                            href={cardPrintUrl(r.uuid)}
                            target="_blank"
                            rel="noopener"
                            title={t('customers.actions.print_card')}
                            aria-label={`${t('customers.actions.print_card')}: ${r.name}`}
                            className="flex size-6 items-center justify-center rounded-sm text-muted-foreground hover:bg-surface-sunken hover:text-primary [@media(pointer:coarse)]:size-11"
                        >
                            <IdCard className="size-3.5" aria-hidden="true" />
                        </a>
                    ) : null}
                    <Link
                        href={`/app/customers/${r.uuid}`}
                        title={t('customers.actions.view_profile')}
                        aria-label={`${t('customers.actions.view_profile')}: ${r.name}`}
                        className="flex size-6 items-center justify-center rounded-sm text-muted-foreground hover:bg-surface-sunken hover:text-foreground [@media(pointer:coarse)]:size-11"
                    >
                        <Eye className="size-3.5" aria-hidden="true" />
                    </Link>
                </div>
            ),
        },
    ];

    const columnState = useColumnVisibility(columns);

    const bulkActions: BulkAction<CustomerRow>[] = can['issue_card']
        ? [
              {
                  id: 'issue_cards',
                  labelKey: 'customers.actions.issue_card',
                  icon: CreditCard,
                  run: issueCards,
              },
          ]
        : [];

    const view =
        filters['view'] ??
        (filters['status'] === 'inactive'
            ? 'inactive'
            : filters['due']
              ? 'overdue'
              : '');
    const moreCount = MORE_FILTERS.filter((k) => filters[k]).length;
    const groupOptions = groups.map((g) => ({
        value: String(g.id),
        label: g.name,
    }));

    const addButton = can['create'] ? (
        <Button type="button" onClick={openAdd}>
            <Plus aria-hidden="true" />
            {t('customers.new')}
        </Button>
    ) : null;

    return (
        <>
            <Head title={t('customers.page_title')} />
            <Page fill>
                <PageHeader
                    title={t('customers.page_title')}
                    count={meta.total}
                    subtitle={t('customers.subtitle')}
                    actions={
                        <>
                            {addButton}
                            <ColumnChooser
                                columns={columns}
                                visibility={columnState.visibility}
                                onChange={columnState.setVisibility}
                            />
                            {canExport ? (
                                <ExportMenu items={exportItems(t, exportAs)} />
                            ) : null}
                            <PrintButton />
                        </>
                    }
                />

                <TableToolbar
                    search={table.searchText}
                    onSearch={table.setSearch}
                    placeholderKey="customers.search"
                    shortPlaceholderKey="customers.search_short"
                    inputRef={searchRef}
                    view={{
                        value: view,
                        options: [
                            { value: '', label: t('customers.view.default') },
                            {
                                value: 'overdue',
                                label: t('customers.view.overdue'),
                            },
                            {
                                value: 'inactive',
                                label: t('customers.view.inactive'),
                            },
                        ],
                        onChange: (v) => table.setFilter('view', v),
                    }}
                    date={{
                        value: filters['date'] ?? '',
                        options: [
                            { value: '', label: t('customers.date.all') },
                            {
                                value: 'today',
                                label: t('customers.date.today'),
                            },
                            { value: '7d', label: t('customers.date.7d') },
                            { value: '30d', label: t('customers.date.30d') },
                            { value: '90d', label: t('customers.date.90d') },
                            { value: 'year', label: t('customers.date.year') },
                        ],
                        onChange: (v) => table.setFilter('date', v),
                    }}
                    chips={
                        groups.length > 0 && groups.length <= GROUP_CHIPS_MAX
                            ? [
                                  {
                                      key: 'group',
                                      label: t('customers.filter.group'),
                                      value: filters['group'] ?? '',
                                      options: [
                                          {
                                              value: '',
                                              label: t(
                                                  'customers.filter.all_groups',
                                              ),
                                          },
                                          ...groupOptions,
                                      ],
                                      onChange: (v) =>
                                          table.setFilter('group', v),
                                  },
                              ]
                            : []
                    }
                    filters={
                        groups.length > GROUP_CHIPS_MAX
                            ? [
                                  {
                                      key: 'group',
                                      labelKey: 'customers.filter.all_groups',
                                      value: filters['group'] ?? '',
                                      options: groupOptions,
                                      onChange: (v: string) =>
                                          table.setFilter('group', v),
                                  },
                              ]
                            : []
                    }
                    moreFilters={{
                        count: moreCount,
                        onOpen: () => setMoreOpen(true),
                    }}
                />

                <div
                    className="flex min-h-0 flex-1 flex-col"
                    {...rowOpenHandlers(customers, (r) =>
                        router.visit(`/app/customers/${r.uuid}`),
                    )}
                >
                    <DataTable<CustomerRow>
                        entity="customer"
                        caption={t('customers.page_title')}
                        rows={customers}
                        columns={columns}
                        meta={meta}
                        getRowId={(r) => r.uuid}
                        rowHref={(r) => `/app/customers/${r.uuid}`}
                        handlers={handlers}
                        bulkActions={bulkActions}
                        sort={table.state.sort}
                        onSort={table.toggleSort}
                        onPage={table.setPage}
                        loading={table.loading}
                        chooser="none"
                        visibility={columnState.visibility}
                        onVisibilityChange={columnState.setVisibility}
                        onPerPage={table.setPerPage}
                        totals={{
                            label: t('table.total', {
                                n: digits.n(totals.count),
                            }),
                            cells: {
                                balance: (
                                    <span
                                        className={cn(
                                            totals.balance > 0
                                                ? 'text-destructive'
                                                : 'text-success',
                                        )}
                                    >
                                        {digits.money(totals.balance)}
                                    </span>
                                ),
                                credit_limit: digits.money(totals.credit_limit),
                            },
                        }}
                        fill
                        empty={
                            <EmptyState
                                icon={Users}
                                title={t('customers.empty')}
                                description={t('customers.empty_hint')}
                                action={addButton ?? undefined}
                            />
                        }
                    />
                </div>
            </Page>

            <Sheet open={moreOpen} onOpenChange={setMoreOpen}>
                <SheetContent
                    side="right"
                    className="flex flex-col gap-0 p-0 text-xs"
                >
                    <SheetHeader className="border-b bg-surface-sunken p-3.5">
                        <SheetTitle className="text-sm font-bold">
                            {t('customers.more.title')}
                        </SheetTitle>
                        <SheetDescription className="text-[11px]">
                            {t('customers.more.subtitle')}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex-1 space-y-4 overflow-y-auto p-4">
                        <MoreSelect
                            id="more-balance"
                            label={t('customers.more.balance')}
                            value={filters['balance'] ?? ''}
                            onChange={(v) => table.setFilter('balance', v)}
                            options={[
                                {
                                    value: '',
                                    label: t('customers.more.balance_all'),
                                },
                                {
                                    value: 'due',
                                    label: t('customers.more.balance_due'),
                                },
                                {
                                    value: 'zero',
                                    label: t('customers.more.balance_zero'),
                                },
                                {
                                    value: 'advance',
                                    label: t('customers.more.balance_advance'),
                                },
                            ]}
                        />
                        <MoreSelect
                            id="more-district"
                            label={t('customers.more.district')}
                            value={filters['district'] ?? ''}
                            onChange={(v) => table.setFilter('district', v)}
                            options={[
                                {
                                    value: '',
                                    label: t('customers.more.district_all'),
                                },
                                ...districts.map((d) => ({
                                    value: String(d.id),
                                    label: geoName(d, locale),
                                })),
                            ]}
                        />
                        <MoreSelect
                            id="more-card"
                            label={t('customers.more.card')}
                            value={filters['card'] ?? ''}
                            onChange={(v) => table.setFilter('card', v)}
                            options={[
                                {
                                    value: '',
                                    label: t('customers.more.card_all'),
                                },
                                {
                                    value: 'yes',
                                    label: t('customers.filter.card_yes'),
                                },
                                {
                                    value: 'no',
                                    label: t('customers.filter.card_no'),
                                },
                            ]}
                        />
                        <MoreSelect
                            id="more-pin"
                            label={t('customers.more.pin')}
                            value={filters['pin'] ?? ''}
                            onChange={(v) => table.setFilter('pin', v)}
                            options={[
                                {
                                    value: '',
                                    label: t('customers.more.pin_all'),
                                },
                                {
                                    value: 'set',
                                    label: t('customers.pin_status.set'),
                                },
                                {
                                    value: 'pending',
                                    label: t('customers.pin_status.pending'),
                                },
                                {
                                    value: 'locked',
                                    label: t('customers.pin_status.locked'),
                                },
                            ]}
                        />
                    </div>
                    <SheetFooter className="flex-row items-center justify-between border-t p-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.get(
                                    '/app/customers',
                                    buildTableQuery({
                                        ...table.state,
                                        page: 1,
                                        filters: {
                                            ...filters,
                                            balance: '',
                                            card: '',
                                            district: '',
                                            pin: '',
                                        },
                                    }),
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        replace: true,
                                        only: ['customers', 'meta', 'totals'],
                                    },
                                )
                            }
                        >
                            {t('customers.more.clear')}
                        </Button>
                        <Button
                            type="button"
                            onClick={() => setMoreOpen(false)}
                        >
                            {t('customers.more.apply')}
                        </Button>
                    </SheetFooter>
                </SheetContent>
            </Sheet>

            <CustomerDialog
                open={adding}
                customer={null}
                groups={groups}
                onClose={closeAdd}
            />
            <ConfirmDialog
                open={deactivating !== null}
                onOpenChange={(open) => !open && setDeactivating(null)}
                title={t('customers.deactivate.title')}
                description={t('customers.deactivate.body')}
                destructive
                confirmLabel={t('customers.actions.deactivate')}
                onConfirm={() =>
                    deactivating
                        ? router.post(
                              `/app/customers/${deactivating.uuid}/deactivate`,
                          )
                        : undefined
                }
            />
            <MergeDialog customer={merging} onClose={() => setMerging(null)} />
        </>
    );
}

function MoreSelect({
    id,
    label,
    value,
    options,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
}) {
    return (
        <div className="space-y-1.5">
            <label
                htmlFor={id}
                className="block text-xs font-semibold text-foreground"
            >
                {label}
            </label>
            <NativeSelect
                id={id}
                value={value}
                onChange={(e) => onChange(e.target.value)}
            >
                {options.map((o) => (
                    <option key={o.value} value={o.value}>
                        {o.label}
                    </option>
                ))}
            </NativeSelect>
        </div>
    );
}
