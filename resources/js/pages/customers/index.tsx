import { Head, Link, router } from '@inertiajs/react';
import { CreditCard, Plus, ScrollText, Users } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { MergeDialog } from '@/components/customers/merge-dialog';
import {
    ColumnChooser,
    DataTable,
    useColumnVisibility,
    type BulkAction,
    type DataColumn,
} from '@/components/data-table/data-table';
import { useServerTable } from '@/components/data-table/server-table';
import { EmptyState } from '@/components/empty-state';
import { Page, PageHeader, PrintButton } from '@/components/page-header';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import type {
    CustomerRow,
    GroupOption,
    TableMetaProp,
} from '@/types/customers';

type Props = {
    customers: CustomerRow[];
    meta: TableMetaProp;
    groups: GroupOption[];
    can: Record<string, boolean>;
};

/** Up to this many customer groups show as chips (mockup); more fall back to a select. */
const GROUP_CHIPS_MAX = 6;

export default function CustomersIndex({
    customers,
    meta,
    groups,
    can,
}: Props) {
    const { t } = useT();
    const digits = useDigits();
    const table = useServerTable({ only: ['customers', 'meta'] });
    const searchRef = useRef<HTMLInputElement>(null);
    const [deactivating, setDeactivating] = useState<CustomerRow | null>(null);
    const [merging, setMerging] = useState<CustomerRow | null>(null);

    useHotkeys('list', [
        {
            id: 'list.new',
            keys: 'n',
            labelKey: 'hotkeys.new',
            handler: () => {
                if (can['create']) {
                    router.visit('/app/customers/create');
                }
            },
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
            numeric: true,
            cell: (r) => (
                <span className="num">
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
                <Link
                    href={`/app/customers/${r.uuid}`}
                    className="font-medium hover:underline"
                >
                    {r.name}
                    {r.name_bn ? (
                        <span className="ml-2 text-muted-foreground">
                            {r.name_bn}
                        </span>
                    ) : null}
                </Link>
            ),
        },
        {
            id: 'shop_name',
            headerKey: 'customers.col.shop',
            cell: (r) => r.shop_name ?? '-',
        },
        {
            id: 'phone',
            headerKey: 'customers.col.phone',
            cell: (r) => (
                <span className="num">{r.phone ? digits.n(r.phone) : '-'}</span>
            ),
        },
        {
            id: 'group',
            headerKey: 'customers.col.group',
            cell: (r) => r.group ?? '-',
        },
        {
            id: 'balance',
            headerKey: 'customers.col.balance',
            sortKey: 'balance',
            numeric: true,
            cell: (r) =>
                r.balance > 0 ? (
                    <span className="font-medium text-destructive">
                        {digits.money(r.balance)}
                    </span>
                ) : (
                    digits.money(r.balance)
                ),
        },
        {
            id: 'last_purchase_at',
            headerKey: 'customers.col.last_purchase',
            sortKey: 'last_purchase_at',
            cell: (r) =>
                r.last_purchase_at
                    ? digits.n(r.last_purchase_at.slice(0, 10))
                    : t('customers.never'),
        },
        {
            id: 'card',
            headerKey: 'customers.col.card',
            hidden: true,
            cell: (r) =>
                r.card_status ? t(`cards.status.${r.card_status}`) : '-',
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

    return (
        <>
            <Head title={t('customers.title')} />
            <Page fill>
                <PageHeader
                    title={t('customers.title')}
                    count={meta.total}
                    actions={
                        <>
                            {can['create'] ? (
                                <Button asChild>
                                    <Link href="/app/customers/create">
                                        <Plus aria-hidden="true" />
                                        {t('customers.new')}
                                    </Link>
                                </Button>
                            ) : null}
                            <Button asChild variant="outline">
                                <Link href="/app/customers/statement">
                                    <ScrollText aria-hidden="true" />
                                    {t('customers.statement.title')}
                                </Link>
                            </Button>
                            <ColumnChooser
                                columns={columns}
                                visibility={columnState.visibility}
                                onChange={columnState.setVisibility}
                            />
                            <PrintButton />
                        </>
                    }
                />

                <TableToolbar
                    search={table.searchText}
                    onSearch={table.setSearch}
                    placeholderKey="customers.search"
                    inputRef={searchRef}
                    chips={
                        groups.length > 0 && groups.length <= GROUP_CHIPS_MAX
                            ? [
                                  {
                                      key: 'group',
                                      label: t('customers.filter.group'),
                                      value: table.state.filters['group'] ?? '',
                                      options: [
                                          {
                                              value: '',
                                              label: t(
                                                  'customers.filter.all_groups',
                                              ),
                                          },
                                          ...groups.map((g) => ({
                                              value: String(g.id),
                                              label: g.name,
                                          })),
                                      ],
                                      onChange: (v) =>
                                          table.setFilter('group', v),
                                  },
                              ]
                            : []
                    }
                    filters={[
                        ...(groups.length > GROUP_CHIPS_MAX
                            ? [
                                  {
                                      key: 'group',
                                      labelKey: 'customers.filter.all_groups',
                                      value: table.state.filters['group'] ?? '',
                                      options: groups.map((g) => ({
                                          value: String(g.id),
                                          label: g.name,
                                      })),
                                      onChange: (v: string) =>
                                          table.setFilter('group', v),
                                  },
                              ]
                            : []),
                        {
                            key: 'card',
                            labelKey: 'customers.filter.card_any',
                            value: table.state.filters['card'] ?? '',
                            options: [
                                {
                                    value: 'yes',
                                    label: t('customers.filter.card_yes'),
                                },
                                {
                                    value: 'no',
                                    label: t('customers.filter.card_no'),
                                },
                            ],
                            onChange: (v) => table.setFilter('card', v),
                        },
                        {
                            key: 'due',
                            labelKey: 'customers.filter.balance_any',
                            value: table.state.filters['due'] ?? '',
                            options: [
                                {
                                    value: '1',
                                    label: t('customers.filter.balance_due'),
                                },
                            ],
                            onChange: (v) => table.setFilter('due', v),
                        },
                        {
                            key: 'status',
                            labelKey: 'customers.filter.active',
                            value: table.state.filters['status'] ?? '',
                            options: [
                                {
                                    value: 'inactive',
                                    label: t('customers.filter.inactive'),
                                },
                            ],
                            onChange: (v) => table.setFilter('status', v),
                        },
                    ]}
                />

                <DataTable<CustomerRow>
                    entity="customer"
                    caption={t('customers.title')}
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
                    fill
                    empty={
                        <EmptyState
                            icon={Users}
                            title={t('customers.empty')}
                            description={t('customers.empty_hint')}
                            action={
                                can['create'] ? (
                                    <Button asChild>
                                        <Link href="/app/customers/create">
                                            {t('customers.new')}
                                        </Link>
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </Page>

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
