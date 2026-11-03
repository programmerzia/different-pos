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
import { NativeSelect } from '@/components/native-select';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useT } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

type Branch = { id: number; code: string; name: string };
type RegisterRow = {
    id: number;
    branch_id: number;
    name: string;
    code: string;
    is_active: boolean;
};

export default function Registers({
    branches,
    registers,
}: {
    branches: Branch[];
    registers: RegisterRow[];
}) {
    const { t } = useT();
    const [editing, setEditing] = useState<RegisterRow | 'new' | null>(null);
    const [branchFilter, setBranchFilter] = useState('');

    const branchName = (id: number) =>
        branches.find((b) => b.id === id)?.name ?? '-';
    const rows = branchFilter
        ? registers.filter((r) => String(r.branch_id) === branchFilter)
        : registers;

    const columns: SimpleColumn<RegisterRow>[] = [
        {
            id: 'branch',
            headerKey: 'registers.branch',
            cell: (r) => branchName(r.branch_id),
        },
        {
            id: 'name',
            headerKey: 'admin.name',
            cell: (r) => <span className="font-medium">{r.name}</span>,
        },
        {
            id: 'code',
            headerKey: 'admin.code',
            cell: (r) => <span className="font-mono">{r.code}</span>,
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
            title={t('registers.title')}
            subtitle={t('settings_hub.desc_long.registers_devices')}
            newLabel={t('registers.new')}
            onNew={() => setEditing('new')}
            actions={
                branches.length > 1 ? (
                    <NativeSelect
                        className="w-auto min-w-40"
                        aria-label={t('registers.branch')}
                        value={branchFilter}
                        onChange={(e) => setBranchFilter(e.target.value)}
                    >
                        <option value="">{t('registers.all_branches')}</option>
                        {branches.map((b) => (
                            <option key={b.id} value={b.id}>
                                {b.name}
                            </option>
                        ))}
                    </NativeSelect>
                ) : null
            }
        >
            <SimpleTable
                caption={t('registers.title')}
                rows={rows}
                columns={columns}
            />
            {editing ? (
                <RecordDialog
                    key={editing === 'new' ? 'new' : editing.id}
                    title={
                        editing === 'new'
                            ? t('registers.new')
                            : t('registers.edit')
                    }
                    initial={{
                        branch_id:
                            editing === 'new'
                                ? (branches[0]?.id ?? 0)
                                : editing.branch_id,
                        name: editing === 'new' ? '' : editing.name,
                        code: editing === 'new' ? '' : editing.code,
                        is_active: editing === 'new' ? true : editing.is_active,
                    }}
                    request={
                        editing === 'new'
                            ? ['post', '/app/settings/registers']
                            : ['put', `/app/settings/registers/${editing.id}`]
                    }
                    onClose={() => setEditing(null)}
                >
                    {(f) => (
                        <>
                            {editing === 'new' ? (
                                <FormField
                                    label={t('registers.branch')}
                                    error={f.error('branch_id')}
                                >
                                    <NativeSelect
                                        value={f.data.branch_id}
                                        onChange={(e) =>
                                            f.set(
                                                'branch_id',
                                                Number(e.target.value),
                                            )
                                        }
                                    >
                                        {branches.map((b) => (
                                            <option key={b.id} value={b.id}>
                                                {b.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                            ) : null}
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
                                label={t('admin.code')}
                                hint={t('registers.code_hint')}
                                error={f.error('code')}
                            >
                                <Input
                                    value={f.data.code}
                                    required
                                    maxLength={6}
                                    className="uppercase"
                                    onChange={(e) =>
                                        f.set('code', e.target.value)
                                    }
                                />
                            </FormField>
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

Registers.layout = [AppLayout, SettingsLayout];
