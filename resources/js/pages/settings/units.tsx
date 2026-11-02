import { Pencil } from 'lucide-react';
import { useState } from 'react';
import { AdminPage } from '@/components/admin/admin-page';
import { CheckField } from '@/components/admin/check-field';
import { RecordDialog } from '@/components/admin/record-dialog';
import {
    SimpleTable,
    type SimpleColumn,
} from '@/components/admin/simple-table';
import { FormField } from '@/components/form-field';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useT } from '@/i18n';

type UnitRow = {
    id: number;
    name: string;
    short_name: string;
    allows_decimal: boolean;
    is_active: boolean;
};

export default function Units({ units }: { units: UnitRow[] }) {
    const { t } = useT();
    const [editing, setEditing] = useState<UnitRow | 'new' | null>(null);

    const columns: SimpleColumn<UnitRow>[] = [
        {
            id: 'name',
            headerKey: 'admin.name',
            cell: (r) => <span className="font-medium">{r.name}</span>,
        },
        {
            id: 'short',
            headerKey: 'units.short_name',
            cell: (r) => r.short_name,
        },
        {
            id: 'decimal',
            headerKey: 'units.allows_decimal',
            cell: (r) => (r.allows_decimal ? t('admin.yes') : t('admin.no')),
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
            id: 'edit',
            headerKey: 'admin.actions',
            cell: (r) => (
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
            ),
        },
    ];

    return (
        <AdminPage
            title={t('units.title')}
            newLabel={t('units.new')}
            onNew={() => setEditing('new')}
        >
            <SimpleTable
                caption={t('units.title')}
                rows={units}
                columns={columns}
            />
            {editing ? (
                <RecordDialog
                    key={editing === 'new' ? 'new' : editing.id}
                    title={editing === 'new' ? t('units.new') : t('units.edit')}
                    initial={{
                        name: editing === 'new' ? '' : editing.name,
                        short_name: editing === 'new' ? '' : editing.short_name,
                        allows_decimal:
                            editing === 'new' ? false : editing.allows_decimal,
                        is_active: editing === 'new' ? true : editing.is_active,
                    }}
                    request={
                        editing === 'new'
                            ? ['post', '/app/settings/units']
                            : ['put', `/app/settings/units/${editing.id}`]
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
                                label={t('units.short_name')}
                                error={f.error('short_name')}
                            >
                                <Input
                                    value={f.data.short_name}
                                    required
                                    maxLength={10}
                                    onChange={(e) =>
                                        f.set('short_name', e.target.value)
                                    }
                                />
                            </FormField>
                            <CheckField
                                label={t('units.allows_decimal')}
                                checked={f.data.allows_decimal}
                                onChange={(v) => f.set('allows_decimal', v)}
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
        </AdminPage>
    );
}
