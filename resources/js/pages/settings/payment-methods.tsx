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
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';

type Method = {
    id: number;
    name: string;
    type: string;
    requires_reference: boolean;
    sort_order: number;
    is_active: boolean;
};

const SYSTEM = ['customer_credit', 'exchange_credit'];

export default function PaymentMethods({
    methods,
    types,
}: {
    methods: Method[];
    types: string[];
}) {
    const { t } = useT();
    const digits = useDigits();
    const [editing, setEditing] = useState<Method | 'new' | null>(null);

    const columns: SimpleColumn<Method>[] = [
        {
            id: 'name',
            headerKey: 'admin.name',
            cell: (r) => <span className="font-medium">{r.name}</span>,
        },
        {
            id: 'type',
            headerKey: 'payment.type',
            cell: (r) => t(`payment.types.${r.type}`),
        },
        {
            id: 'reference',
            headerKey: 'payment.requires_reference',
            cell: (r) =>
                r.requires_reference ? t('admin.yes') : t('admin.no'),
        },
        {
            id: 'sort',
            headerKey: 'payment.sort',
            numeric: true,
            cell: (r) => digits.n(r.sort_order),
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
            title={t('payment.title')}
            newLabel={t('payment.new')}
            onNew={() => setEditing('new')}
        >
            <SimpleTable
                caption={t('payment.title')}
                rows={methods}
                columns={columns}
            />
            {editing ? (
                <RecordDialog
                    key={editing === 'new' ? 'new' : editing.id}
                    title={
                        editing === 'new' ? t('payment.new') : t('payment.edit')
                    }
                    initial={{
                        name: editing === 'new' ? '' : editing.name,
                        type: editing === 'new' ? 'cash' : editing.type,
                        requires_reference:
                            editing === 'new'
                                ? false
                                : editing.requires_reference,
                        sort_order:
                            editing === 'new'
                                ? methods.length + 1
                                : editing.sort_order,
                        is_active: editing === 'new' ? true : editing.is_active,
                    }}
                    request={
                        editing === 'new'
                            ? ['post', '/app/settings/payment-methods']
                            : [
                                  'put',
                                  `/app/settings/payment-methods/${editing.id}`,
                              ]
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
                                label={t('payment.type')}
                                error={f.error('type')}
                            >
                                <NativeSelect
                                    value={f.data.type}
                                    disabled={editing !== 'new'}
                                    onChange={(e) =>
                                        f.set('type', e.target.value)
                                    }
                                >
                                    {types
                                        .filter(
                                            (x) =>
                                                editing !== 'new' ||
                                                !SYSTEM.includes(x),
                                        )
                                        .map((type) => (
                                            <option key={type} value={type}>
                                                {t(`payment.types.${type}`)}
                                            </option>
                                        ))}
                                </NativeSelect>
                            </FormField>
                            <FormField
                                label={t('payment.sort')}
                                error={f.error('sort_order')}
                            >
                                <Input
                                    type="number"
                                    value={f.data.sort_order}
                                    onChange={(e) =>
                                        f.set(
                                            'sort_order',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </FormField>
                            <CheckField
                                label={t('payment.requires_reference')}
                                checked={f.data.requires_reference}
                                onChange={(v) => f.set('requires_reference', v)}
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
