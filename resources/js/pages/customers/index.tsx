import { Head, Link, router } from '@inertiajs/react';
import { CreditCard, Plus, Search, Users } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { MergeDialog } from '@/components/customers/merge-dialog';
import {
    DataTable,
    type BulkAction,
    type DataColumn,
} from '@/components/data-table/data-table';
import { useServerTable } from '@/components/data-table/server-table';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

const ALL = '__all';

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
            cell: (r) => <span className="num">{r.phone ? digits.n(r.phone) : '-'}</span>,
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

    // Keep the search box in step with back/forward navigation.
    useEffect(() => {
        if (searchRef.current && document.activeElement !== searchRef.current) {
            searchRef.current.value = table.state.q;
        }
    }, [table.state.q]);

    const filter = (key: string, value: string) =>
        table.setFilter(key, value === ALL ? '' : value);

    return (
        <>
            <Head title={t('customers.title')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="mr-auto text-2xl font-bold">
                        {t('customers.title')}
                    </h1>
                    <Button asChild variant="outline" className="min-h-11 md:min-h-9">
                        <Link href="/app/customers/statement">
                            {t('customers.statement.title')}
                        </Link>
                    </Button>
                    {can['create'] ? (
                        <Button asChild className="min-h-11 md:min-h-9">
                            <Link href="/app/customers/create">
                                <Plus aria-hidden="true" />
                                {t('customers.new')}
                            </Link>
                        </Button>
                    ) : null}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative w-full max-w-sm">
                        <Search
                            className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            ref={searchRef}
                            type="search"
                            defaultValue={table.state.q}
                            placeholder={t('customers.search')}
                            aria-label={t('customers.search')}
                            className="min-h-11 pl-9 md:min-h-9"
                            onChange={(e) => table.setSearch(e.target.value)}
                        />
                    </div>
                    <Select
                        value={table.state.filters['group'] ?? ALL}
                        onValueChange={(v) => filter('group', v)}
                    >
                        <SelectTrigger
                            className="min-h-11 w-44 md:min-h-9"
                            aria-label={t('customers.filter.group')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                {t('customers.filter.all_groups')}
                            </SelectItem>
                            {groups.map((g) => (
                                <SelectItem key={g.id} value={String(g.id)}>
                                    {g.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={table.state.filters['card'] ?? ALL}
                        onValueChange={(v) => filter('card', v)}
                    >
                        <SelectTrigger
                            className="min-h-11 w-44 md:min-h-9"
                            aria-label={t('customers.filter.card')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                {t('customers.filter.card_any')}
                            </SelectItem>
                            <SelectItem value="yes">
                                {t('customers.filter.card_yes')}
                            </SelectItem>
                            <SelectItem value="no">
                                {t('customers.filter.card_no')}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select
                        value={table.state.filters['due'] ?? ALL}
                        onValueChange={(v) => filter('due', v)}
                    >
                        <SelectTrigger
                            className="min-h-11 w-44 md:min-h-9"
                            aria-label={t('customers.filter.balance')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                {t('customers.filter.balance_any')}
                            </SelectItem>
                            <SelectItem value="1">
                                {t('customers.filter.balance_due')}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select
                        value={table.state.filters['status'] ?? ALL}
                        onValueChange={(v) => filter('status', v)}
                    >
                        <SelectTrigger
                            className="min-h-11 w-40 md:min-h-9"
                            aria-label={t('customers.filter.status')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                {t('customers.filter.active')}
                            </SelectItem>
                            <SelectItem value="inactive">
                                {t('customers.filter.inactive')}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

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
            </div>

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
            <MergeDialog
                customer={merging}
                onClose={() => setMerging(null)}
            />
        </>
    );
}
