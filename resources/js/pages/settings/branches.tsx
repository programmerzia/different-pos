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
import { MoneyInput } from '@/components/money-input';
import { NativeSelect } from '@/components/native-select';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';

type BranchRow = {
    id: number;
    code: string;
    name: string;
    phone: string | null;
    address: string | null;
    receipt_header: string | null;
    receipt_footer: string | null;
    is_active: boolean;
    users: number;
    settings: {
        allow_negative_stock: boolean;
        batch_selection: string;
        cash_rounding: number;
        auto_print: boolean;
        paper: string;
    };
};

export default function Branches({ branches }: { branches: BranchRow[] }) {
    const { t } = useT();
    const digits = useDigits();
    const [editing, setEditing] = useState<BranchRow | 'new' | null>(null);

    const columns: SimpleColumn<BranchRow>[] = [
        {
            id: 'code',
            headerKey: 'admin.code',
            cell: (r) => <span className="font-mono">{r.code}</span>,
        },
        {
            id: 'name',
            headerKey: 'admin.name',
            cell: (r) => <span className="font-medium">{r.name}</span>,
        },
        {
            id: 'phone',
            headerKey: 'admin.phone',
            cell: (r) => (r.phone ? digits.n(r.phone) : '-'),
        },
        {
            id: 'users',
            headerKey: 'branches.staff',
            numeric: true,
            cell: (r) => digits.n(r.users),
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

    const initial =
        editing && editing !== 'new'
            ? {
                  code: editing.code,
                  name: editing.name,
                  phone: editing.phone ?? '',
                  address: editing.address ?? '',
                  receipt_header: editing.receipt_header ?? '',
                  receipt_footer: editing.receipt_footer ?? '',
                  is_active: editing.is_active,
                  settings: { ...editing.settings },
              }
            : {
                  code: '',
                  name: '',
                  phone: '',
                  address: '',
                  receipt_header: '',
                  receipt_footer: '',
                  is_active: true,
                  settings: {
                      allow_negative_stock: false,
                      batch_selection: 'auto',
                      cash_rounding: 0,
                      auto_print: true,
                      paper: '80',
                  },
              };

    return (
        <AdminPage
            title={t('branches.title')}
            newLabel={t('branches.new')}
            onNew={() => setEditing('new')}
        >
            <SimpleTable
                caption={t('branches.title')}
                rows={branches}
                columns={columns}
            />
            {editing ? (
                <RecordDialog
                    key={editing === 'new' ? 'new' : editing.id}
                    title={
                        editing === 'new'
                            ? t('branches.new')
                            : t('branches.edit')
                    }
                    initial={initial}
                    request={
                        editing === 'new'
                            ? ['post', '/app/settings/branches']
                            : ['put', `/app/settings/branches/${editing.id}`]
                    }
                    onClose={() => setEditing(null)}
                >
                    {(f) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    label={t('admin.code')}
                                    error={f.error('code')}
                                >
                                    <Input
                                        value={f.data.code}
                                        required
                                        maxLength={10}
                                        className="uppercase"
                                        onChange={(e) =>
                                            f.set('code', e.target.value)
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label={t('admin.name')}
                                    error={f.error('name')}
                                >
                                    <Input
                                        value={f.data.name}
                                        required
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
                                        type="tel"
                                        value={f.data.phone}
                                        onChange={(e) =>
                                            f.set('phone', e.target.value)
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label={t('admin.address')}
                                    error={f.error('address')}
                                >
                                    <Input
                                        value={f.data.address}
                                        onChange={(e) =>
                                            f.set('address', e.target.value)
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label={t('branches.receipt_header')}
                                    error={f.error('receipt_header')}
                                >
                                    <Input
                                        value={f.data.receipt_header}
                                        onChange={(e) =>
                                            f.set(
                                                'receipt_header',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label={t('branches.receipt_footer')}
                                    error={f.error('receipt_footer')}
                                >
                                    <Input
                                        value={f.data.receipt_footer}
                                        onChange={(e) =>
                                            f.set(
                                                'receipt_footer',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField label={t('branches.paper')}>
                                    <NativeSelect
                                        value={f.data.settings.paper}
                                        onChange={(e) =>
                                            f.set('settings', {
                                                ...f.data.settings,
                                                paper: e.target.value,
                                            })
                                        }
                                    >
                                        <option value="80">
                                            {t('branches.paper_80')}
                                        </option>
                                        <option value="58">
                                            {t('branches.paper_58')}
                                        </option>
                                        <option value="a4">A4</option>
                                    </NativeSelect>
                                </FormField>
                                <FormField
                                    label={t('branches.batch_selection')}
                                >
                                    <NativeSelect
                                        value={f.data.settings.batch_selection}
                                        onChange={(e) =>
                                            f.set('settings', {
                                                ...f.data.settings,
                                                batch_selection: e.target.value,
                                            })
                                        }
                                    >
                                        <option value="auto">
                                            {t('branches.batch_auto')}
                                        </option>
                                        <option value="manual">
                                            {t('branches.batch_manual')}
                                        </option>
                                    </NativeSelect>
                                </FormField>
                                <FormField
                                    label={t('branches.cash_rounding')}
                                    hint={t('branches.cash_rounding_hint')}
                                >
                                    <MoneyInput
                                        value={f.data.settings.cash_rounding}
                                        onChange={(v) =>
                                            f.set('settings', {
                                                ...f.data.settings,
                                                cash_rounding: v ?? 0,
                                            })
                                        }
                                    />
                                </FormField>
                            </div>
                            <div className="grid sm:grid-cols-2">
                                <CheckField
                                    label={t('branches.allow_negative_stock')}
                                    checked={
                                        f.data.settings.allow_negative_stock
                                    }
                                    onChange={(v) =>
                                        f.set('settings', {
                                            ...f.data.settings,
                                            allow_negative_stock: v,
                                        })
                                    }
                                />
                                <CheckField
                                    label={t('branches.auto_print')}
                                    checked={f.data.settings.auto_print}
                                    onChange={(v) =>
                                        f.set('settings', {
                                            ...f.data.settings,
                                            auto_print: v,
                                        })
                                    }
                                />
                                <CheckField
                                    label={t('admin.active')}
                                    checked={f.data.is_active}
                                    onChange={(v) => f.set('is_active', v)}
                                />
                            </div>
                        </>
                    )}
                </RecordDialog>
            ) : null}
        </AdminPage>
    );
}
