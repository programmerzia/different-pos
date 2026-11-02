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
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';

type Tax = {
    id: number;
    name: string;
    rate_bp: number;
    is_default: boolean;
    is_active: boolean;
};

/** Basis points <-> percent text (1500 <-> "15"). */
const toPercent = (bp: number) => String(bp / 100);
const fromPercent = (text: string) =>
    Math.round((Number.parseFloat(text) || 0) * 100);

export default function Taxes({ taxes }: { taxes: Tax[] }) {
    const { t } = useT();
    const digits = useDigits();
    const [editing, setEditing] = useState<Tax | 'new' | null>(null);

    const columns: SimpleColumn<Tax>[] = [
        {
            id: 'name',
            headerKey: 'admin.name',
            cell: (r) => <span className="font-medium">{r.name}</span>,
        },
        {
            id: 'rate',
            headerKey: 'taxes.rate',
            numeric: true,
            cell: (r) => `${digits.n(toPercent(r.rate_bp))}%`,
        },
        {
            id: 'default',
            headerKey: 'taxes.default',
            cell: (r) => (r.is_default ? t('admin.yes') : ''),
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
            title={t('taxes.title')}
            newLabel={t('taxes.new')}
            onNew={() => setEditing('new')}
        >
            <SimpleTable
                caption={t('taxes.title')}
                rows={taxes}
                columns={columns}
            />
            {editing ? (
                <RecordDialog
                    key={editing === 'new' ? 'new' : editing.id}
                    title={editing === 'new' ? t('taxes.new') : t('taxes.edit')}
                    initial={{
                        name: editing === 'new' ? '' : editing.name,
                        rate_bp: editing === 'new' ? 0 : editing.rate_bp,
                        is_default:
                            editing === 'new' ? false : editing.is_default,
                        is_active: editing === 'new' ? true : editing.is_active,
                    }}
                    request={
                        editing === 'new'
                            ? ['post', '/app/settings/taxes']
                            : ['put', `/app/settings/taxes/${editing.id}`]
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
                                label={t('taxes.rate_percent')}
                                error={f.error('rate_bp')}
                            >
                                <Input
                                    type="text"
                                    inputMode="decimal"
                                    className="num text-right"
                                    defaultValue={toPercent(f.data.rate_bp)}
                                    onChange={(e) =>
                                        f.set(
                                            'rate_bp',
                                            Math.min(
                                                10000,
                                                fromPercent(e.target.value),
                                            ),
                                        )
                                    }
                                />
                            </FormField>
                            <CheckField
                                label={t('taxes.default')}
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
        </AdminPage>
    );
}
