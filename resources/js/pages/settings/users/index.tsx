import { Link, router } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AdminPage } from '@/components/admin/admin-page';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable, type DataColumn } from '@/components/data-table/data-table';
import { useServerTable } from '@/components/data-table/server-table';
import { EmptyState } from '@/components/empty-state';
import { StatusBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';

type UserRow = {
    id: number;
    name: string;
    email: string | null;
    username: string | null;
    phone: string | null;
    is_active: boolean;
    last_login_at: string | null;
    roles: string[];
    branches: string[];
};

type Props = {
    users: UserRow[];
    meta: { page: number; perPage: number; total: number; lastPage: number };
    roles: { id: number; name: string }[];
};

const editUrl = (row: UserRow) => `/app/settings/users/${row.id}/edit`;

export default function UsersIndex({ users, meta, roles }: Props) {
    const { t } = useT();
    const digits = useDigits();
    const table = useServerTable({ only: ['users', 'meta'] });
    const [deactivating, setDeactivating] = useState<UserRow | null>(null);

    const handlers = useMemo(
        () => ({
            'row.edit': (row: UserRow) => router.visit(editUrl(row)),
            'user.deactivate': (row: UserRow) =>
                row.is_active ? setDeactivating(row) : undefined,
        }),
        [],
    );

    const columns: DataColumn<UserRow>[] = [
        {
            id: 'name',
            headerKey: 'admin.name',
            sortKey: 'name',
            required: true,
            mobile: 'title',
            cell: (r) => (
                <Link href={editUrl(r)} className="font-medium hover:underline">
                    {r.name}
                </Link>
            ),
        },
        {
            id: 'login',
            headerKey: 'users.login',
            cell: (r) => r.email ?? r.username ?? r.phone ?? '-',
        },
        {
            id: 'roles',
            headerKey: 'users.roles',
            cell: (r) => r.roles.join(', ') || '-',
        },
        {
            id: 'branches',
            headerKey: 'users.branches',
            cell: (r) => r.branches.join(', ') || '-',
        },
        {
            id: 'last_login_at',
            headerKey: 'users.last_login',
            sortKey: 'last_login_at',
            cell: (r) =>
                r.last_login_at
                    ? digits.n(r.last_login_at.slice(0, 16).replace('T', ' '))
                    : t('users.never'),
        },
        {
            id: 'status',
            headerKey: 'admin.status',
            cell: (r) => (
                <StatusBadge
                    status={r.is_active ? 'active' : 'inactive'}
                    tone={r.is_active ? 'success' : 'neutral'}
                    label={t(r.is_active ? 'admin.active' : 'admin.inactive')}
                />
            ),
        },
    ];

    return (
        <AdminPage
            title={t('users.title')}
            newLabel={t('users.new')}
            onNew={() => router.visit('/app/settings/users/create')}
        >
            <TableToolbar
                search={table.searchText}
                onSearch={table.setSearch}
                placeholderKey="users.search"
                filters={[
                    {
                        key: 'status',
                        labelKey: 'admin.status',
                        value: table.state.filters['status'] ?? '',
                        options: [
                            { value: 'active', label: t('admin.active') },
                            { value: 'inactive', label: t('admin.inactive') },
                        ],
                        onChange: (v) => table.setFilter('status', v),
                    },
                    {
                        key: 'role',
                        labelKey: 'users.roles',
                        value: table.state.filters['role'] ?? '',
                        options: roles.map((r) => ({
                            value: String(r.id),
                            label: r.name,
                        })),
                        onChange: (v) => table.setFilter('role', v),
                    },
                ]}
            />
            <DataTable<UserRow>
                entity="user"
                caption={t('users.title')}
                rows={users}
                columns={columns}
                meta={meta}
                getRowId={(r) => String(r.id)}
                rowHref={editUrl}
                handlers={handlers}
                sort={table.state.sort}
                onSort={table.toggleSort}
                onPage={table.setPage}
                loading={table.loading}
                selectable={false}
                empty={<EmptyState icon={Users} title={t('users.empty')} />}
            />
            <ConfirmDialog
                open={deactivating !== null}
                onOpenChange={(open) =>
                    open ? undefined : setDeactivating(null)
                }
                title={t('users.deactivate_title')}
                description={t('users.deactivate_body')}
                confirmLabel={t('users.deactivate')}
                recordName={deactivating?.name ?? ''}
                destructive
                onConfirm={() =>
                    deactivating
                        ? router.post(
                              `/app/settings/users/${deactivating.id}/deactivate`,
                              {},
                              { preserveScroll: true },
                          )
                        : undefined
                }
            />
        </AdminPage>
    );
}
