import { Page, PageHeader } from '@/components/page-header';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Plus, Save, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { NativeSelect } from '@/components/native-select';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { useDigits } from '@/hooks/use-digits';
import { useFormSubmit } from '@/hooks/use-form-submit';
import { useT } from '@/i18n';

type Row = {
    id: number;
    name: string;
    name_bn: string | null;
    parent_id: number | null;
    is_active: boolean;
    products: number;
};

/** Categories and brands: one inline-edit table, the `kind` prop picks the URL set and labels. */
export default function Taxonomy({
    kind,
    rows,
}: {
    kind: 'category' | 'brand';
    rows: Row[];
}) {
    const { t } = useT();
    const can = useCan();
    const digits = useDigits();
    const isCategory = kind === 'category';
    const base = isCategory
        ? '/app/products/categories'
        : '/app/products/brands';
    const ns = isCategory ? 'categories' : 'brands';
    const { submit, processing, errors } = useFormSubmit();

    const [editing, setEditing] = useState<number | 'new' | null>(null);
    const [form, setForm] = useState({ name: '', name_bn: '', parent_id: '' });
    const [toDelete, setToDelete] = useState<Row | null>(null);

    const open = (row: Row | 'new') => {
        setEditing(row === 'new' ? 'new' : row.id);
        setForm(
            row === 'new'
                ? { name: '', name_bn: '', parent_id: '' }
                : {
                      name: row.name,
                      name_bn: row.name_bn ?? '',
                      parent_id: row.parent_id ? String(row.parent_id) : '',
                  },
        );
    };

    const save = () => {
        const data = {
            name: form.name,
            ...(isCategory
                ? {
                      name_bn: form.name_bn || null,
                      parent_id:
                          form.parent_id === '' ? null : Number(form.parent_id),
                  }
                : {}),
        };
        submit(
            editing === 'new' ? 'post' : 'put',
            editing === 'new' ? base : `${base}/${editing}`,
            data,
            { onSuccess: () => setEditing(null) },
        );
    };

    const nameOf = (id: number | null) =>
        rows.find((r) => r.id === id)?.name ?? '';
    const editor = (
        <div className="grid gap-3 rounded-lg border bg-muted p-4 lg:grid-cols-3">
            <FormField
                label={t('common.name')}
                error={errors['name'] || errors['form']}
            >
                <Input
                    className="min-h-11"
                    value={form.name}
                    autoFocus
                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            save();
                        }
                        if (e.key === 'Escape') setEditing(null);
                    }}
                />
            </FormField>
            {isCategory ? (
                <>
                    <FormField label={t('products.name_bn')}>
                        <Input
                            className="min-h-11"
                            lang="bn"
                            value={form.name_bn}
                            onChange={(e) =>
                                setForm({ ...form, name_bn: e.target.value })
                            }
                        />
                    </FormField>
                    <FormField
                        label={t('categories.parent')}
                        error={errors['parent_id']}
                    >
                        <NativeSelect
                            value={form.parent_id}
                            onChange={(e) =>
                                setForm({ ...form, parent_id: e.target.value })
                            }
                        >
                            <option value="">{t('common.none')}</option>
                            {rows
                                .filter(
                                    (r) =>
                                        r.parent_id === null &&
                                        r.id !== editing,
                                )
                                .map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.name}
                                    </option>
                                ))}
                        </NativeSelect>
                    </FormField>
                </>
            ) : null}
            <div className="flex gap-2 lg:col-span-3">
                <Button
                    className="min-h-11"
                    disabled={processing}
                    onClick={save}
                >
                    <Save aria-hidden="true" /> {t('common.save')}
                </Button>
                <Button
                    variant="outline"
                    className="min-h-11"
                    onClick={() => setEditing(null)}
                >
                    <X aria-hidden="true" /> {t('common.cancel')}
                </Button>
            </div>
        </div>
    );

    return (
        <>
            <Head title={t(`${ns}.title`)} />
            <Page className="max-w-4xl">
                <Button asChild variant="ghost" className="-ml-3 w-fit">
                    <Link href="/app/products">
                        <ArrowLeft aria-hidden="true" /> {t('products.title')}
                    </Link>
                </Button>
                <PageHeader
                    title={t(`${ns}.title`)}
                    actions={
                        can('products.create') ? (
                            <Button onClick={() => open('new')}>
                                <Plus aria-hidden="true" /> {t(`${ns}.new`)}
                            </Button>
                        ) : null
                    }
                />

                {editing === 'new' ? editor : null}

                {rows.length === 0 ? (
                    <EmptyState title={t(`${ns}.empty`)} />
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <caption className="sr-only">
                                {t(`${ns}.title`)}
                            </caption>
                            <thead className="bg-muted text-left">
                                <tr>
                                    <th scope="col" className="px-3 py-2">
                                        {t('common.name')}
                                    </th>
                                    {isCategory ? (
                                        <th scope="col" className="px-3 py-2">
                                            {t('categories.parent')}
                                        </th>
                                    ) : null}
                                    <th
                                        scope="col"
                                        className="px-3 py-2 text-right"
                                    >
                                        {t('categories.products')}
                                    </th>
                                    <th scope="col" className="px-3 py-2">
                                        {t('common.status')}
                                    </th>
                                    <th scope="col" className="px-3 py-2">
                                        <span className="sr-only">
                                            {t('common.actions')}
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) =>
                                    editing === row.id ? (
                                        <tr key={row.id}>
                                            <td colSpan={5} className="p-2">
                                                {editor}
                                            </td>
                                        </tr>
                                    ) : (
                                        <tr key={row.id} className="border-t">
                                            <th
                                                scope="row"
                                                className="px-3 py-2 text-left font-medium"
                                            >
                                                {row.name}
                                            </th>
                                            {isCategory ? (
                                                <td className="px-3 py-2">
                                                    {nameOf(row.parent_id)}
                                                </td>
                                            ) : null}
                                            <td className="num px-3 py-2 text-right">
                                                {digits.n(row.products)}
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.is_active ? null : (
                                                    <StatusBadge
                                                        status="void"
                                                        label={t(
                                                            'products.inactive',
                                                        )}
                                                    />
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-right whitespace-nowrap">
                                                {can('products.update') ? (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-11"
                                                        aria-label={`${t('actions.edit')} ${row.name}`}
                                                        onClick={() =>
                                                            open(row)
                                                        }
                                                    >
                                                        <Pencil aria-hidden="true" />
                                                    </Button>
                                                ) : null}
                                                {can('products.delete') ? (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-11"
                                                        aria-label={`${t('actions.delete')} ${row.name}`}
                                                        onClick={() =>
                                                            setToDelete(row)
                                                        }
                                                    >
                                                        <Trash2 aria-hidden="true" />
                                                    </Button>
                                                ) : null}
                                            </td>
                                        </tr>
                                    ),
                                )}
                            </tbody>
                        </table>
                    </div>
                )}
            </Page>
            <ConfirmDialog
                open={toDelete !== null}
                onOpenChange={(o) => !o && setToDelete(null)}
                title={t(`${ns}.delete_title`)}
                description={t('categories.delete_hint')}
                destructive
                recordName={toDelete?.name ?? ''}
                onConfirm={() => {
                    if (toDelete)
                        router.delete(`${base}/${toDelete.id}`, {
                            preserveScroll: true,
                        });
                }}
            />
        </>
    );
}
