import { router } from '@inertiajs/react';
import { Pencil, Star, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AdminPage } from '@/components/admin/admin-page';
import { CheckField } from '@/components/admin/check-field';
import { RecordDialog } from '@/components/admin/record-dialog';
import {
    SimpleTable,
    type SimpleColumn,
} from '@/components/admin/simple-table';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormField } from '@/components/form-field';
import { RowContextMenu } from '@/components/row-context-menu';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Handlers } from '@/command/actions';
import { useT } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

type Courier = {
    id: number;
    name: string;
    phone: string | null;
    is_default: boolean;
    is_active: boolean;
};

export default function Couriers({ couriers }: { couriers: Courier[] }) {
    const { t } = useT();
    const [editing, setEditing] = useState<Courier | 'new' | null>(() =>
        typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).has('new')
            ? 'new'
            : null,
    );
    const [toDelete, setToDelete] = useState<Courier | null>(null);

    const makeDefault = (c: Courier) =>
        router.post(
            `/app/settings/couriers/${c.id}/default`,
            {},
            { preserveScroll: true },
        );

    const handlers: Handlers<Courier> = useMemo(
        () => ({
            'row.edit': (c) => setEditing(c),
            'row.delete': (c) => setToDelete(c),
            'courier.default': (c) => makeDefault(c),
        }),
        [],
    );

    const columns: SimpleColumn<Courier>[] = [
        {
            id: 'name',
            headerKey: 'admin.name',
            cell: (r) => (
                <RowContextMenu entity="courier" row={r} handlers={handlers}>
                    <button
                        type="button"
                        onClick={() => setEditing(r)}
                        className="min-h-11 rounded-sm text-left font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none md:min-h-8"
                    >
                        {r.name}
                    </button>
                </RowContextMenu>
            ),
        },
        {
            id: 'phone',
            headerKey: 'admin.phone',
            cell: (r) => r.phone ?? '',
        },
        {
            id: 'default',
            headerKey: 'couriers.default',
            cell: (r) =>
                r.is_default ? (
                    <StatusBadge
                        status="default"
                        tone="success"
                        label={t('couriers.default')}
                    />
                ) : null,
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
        {
            id: 'actions',
            headerKey: 'admin.actions',
            cell: (r) => (
                <div className="flex flex-wrap gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="min-h-11 md:min-h-8"
                        onClick={() => setEditing(r)}
                    >
                        <Pencil aria-hidden="true" />
                        {t('actions.edit')}
                    </Button>
                    {!r.is_default && r.is_active ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="min-h-11 md:min-h-8"
                            onClick={() => makeDefault(r)}
                        >
                            <Star aria-hidden="true" />
                            {t('couriers.set_default')}
                        </Button>
                    ) : null}
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="min-h-11 md:min-h-8"
                        onClick={() => setToDelete(r)}
                    >
                        <Trash2 aria-hidden="true" />
                        {t('actions.delete')}
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AdminPage
            title={t('couriers.title')}
            subtitle={t('settings_hub.desc_long.couriers')}
            newLabel={t('couriers.new')}
            onNew={() => setEditing('new')}
        >
            <SimpleTable
                caption={t('couriers.title')}
                rows={couriers}
                columns={columns}
            />
            {editing ? (
                <RecordDialog
                    key={editing === 'new' ? 'new' : editing.id}
                    title={
                        editing === 'new'
                            ? t('couriers.new')
                            : t('couriers.edit')
                    }
                    initial={{
                        name: editing === 'new' ? '' : editing.name,
                        phone: editing === 'new' ? '' : (editing.phone ?? ''),
                        is_default:
                            editing === 'new' ? false : editing.is_default,
                        is_active: editing === 'new' ? true : editing.is_active,
                    }}
                    request={
                        editing === 'new'
                            ? ['post', '/app/settings/couriers']
                            : ['put', `/app/settings/couriers/${editing.id}`]
                    }
                    onClose={() => setEditing(null)}
                >
                    {(f) => (
                        <>
                            <FormField
                                label={t('admin.name')}
                                error={f.error('name')}
                            >
                                <Input
                                    value={f.data.name}
                                    required
                                    autoFocus
                                    onChange={(e) =>
                                        f.set('name', e.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                label={t('admin.phone')}
                                error={f.error('phone')}
                            >
                                <Input
                                    value={f.data.phone}
                                    inputMode="tel"
                                    onChange={(e) =>
                                        f.set('phone', e.target.value)
                                    }
                                />
                            </FormField>
                            <CheckField
                                label={t('couriers.make_default')}
                                checked={f.data.is_default}
                                onChange={(v) => f.set('is_default', v)}
                            />
                            <CheckField
                                label={t('admin.active')}
                                checked={f.data.is_active}
                                onChange={(v) => f.set('is_active', v)}
                            />
                        </>
                    )}
                </RecordDialog>
            ) : null}
            <ConfirmDialog
                open={toDelete !== null}
                onOpenChange={(o) => !o && setToDelete(null)}
                title={t('couriers.delete_title')}
                description={t('couriers.delete_hint')}
                destructive
                recordName={toDelete?.name ?? ''}
                onConfirm={() => {
                    if (toDelete) {
                        router.delete(`/app/settings/couriers/${toDelete.id}`, {
                            preserveScroll: true,
                        });
                    }
                }}
            />
        </AdminPage>
    );
}

Couriers.layout = [AppLayout, SettingsLayout];
