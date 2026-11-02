import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Plus, Save } from 'lucide-react';
import { useState } from 'react';
import { FormField } from '@/components/form-field';
import { MoneyInput } from '@/components/money-input';
import { NativeSelect } from '@/components/native-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormSubmit } from '@/hooks/use-form-submit';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { rowKey } from '@/lib/variant-grid';

type Value = { id: number; value: string; code: string };
type Option = { id: number; name: string; values: Value[] };
type Variant = {
    id: number;
    uuid: string;
    sku: string;
    barcode: string | null;
    label: string | null;
    option1_value_id: number | null;
    option2_value_id: number | null;
    sale_price: number;
    cost_price: number | null;
    is_active: boolean;
};

type Cell = { sale_price: number | null; cost_price: number | null; sku: string; exists: boolean };

export default function FamilyPage({
    family,
    variants,
    candidates,
    canViewCost,
}: {
    family: { id: number; uuid: string; code: string; name: string; image_url: string | null; option1: Option | null; option2: Option | null };
    variants: Variant[];
    candidates: { id: number; name: string; sku: string }[];
    canViewCost: boolean;
}) {
    const { t } = useT();
    const { submit, processing, errors } = useFormSubmit();
    const first = family.option1?.values ?? [];
    const second = family.option2?.values ?? [];

    // The matrix: every size x colour of the family. Existing variants carry their values; a blank
    // cell is "not stocked yet" and is created only once a price is entered.
    const initial = () => {
        const map: Record<string, Cell> = {};
        for (const a of first) {
            for (const b of second.length > 0 ? second : [null]) {
                const key = rowKey(a.id, b?.id ?? null);
                const found = variants.find((v) => v.option1_value_id === a.id && v.option2_value_id === (b?.id ?? null));
                map[key] = {
                    sale_price: found?.sale_price ?? null,
                    cost_price: found?.cost_price ?? null,
                    sku: found?.sku ?? [family.code, a.code, b?.code].filter(Boolean).join('-'),
                    exists: found !== undefined,
                };
            }
        }

        return map;
    };
    const [cells, setCells] = useState(initial);
    const [attach, setAttach] = useState({ product_id: '', option1_value_id: '', option2_value_id: '' });

    const patch = (key: string, next: Partial<Cell>) => setCells((prev) => ({ ...prev, [key]: { ...prev[key]!, ...next } }));

    const save = () => {
        const rows = first.flatMap((a) =>
            (second.length > 0 ? second : [null]).flatMap((b) => {
                const key = rowKey(a.id, b?.id ?? null);
                const cell = cells[key];
                if (!cell || (!cell.exists && cell.sale_price === null)) {
                    return [];
                }

                return [{
                    option1_value_id: a.id,
                    option2_value_id: b?.id ?? null,
                    sale_price: cell.sale_price,
                    cost_price: canViewCost ? cell.cost_price : null,
                    sku: cell.sku,
                }];
            }),
        );
        submit('post', `/app/products/families/${family.uuid}/variants`, { rows });
    };

    useHotkeys('form', [
        { id: 'family.save', keys: 'mod+s', labelKey: 'hotkeys.save', allowInInput: true, handler: (e) => { e.preventDefault(); if (!processing) save(); } },
    ]);

    const rowsFor = second.length > 0 ? second : [null];

    return (
        <>
            <Head title={family.name} />
            <div className="flex max-w-6xl flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <Button asChild variant="ghost" className="-ml-3 min-h-11">
                            <Link href="/app/products"><ArrowLeft aria-hidden="true" /> {t('products.title')}</Link>
                        </Button>
                        <h1 className="text-2xl font-bold">{family.name}</h1>
                        <p className="num text-sm text-muted-foreground">{family.code}</p>
                    </div>
                    <Button className="min-h-11" disabled={processing} onClick={save}>
                        <Save aria-hidden="true" /> {t('common.save')}
                    </Button>
                </div>
                {errors['rows'] || errors['form'] ? <p role="alert" className="text-sm text-destructive">{errors['rows'] || errors['form']}</p> : null}

                <section aria-labelledby="matrix-title" className="grid gap-2">
                    <h2 id="matrix-title" className="text-xl font-semibold">{t('products.matrix')}</h2>
                    <p className="text-sm text-muted-foreground">{t('products.matrix_hint')}</p>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <caption className="sr-only">{t('products.matrix')}</caption>
                            <thead className="bg-muted text-left">
                                <tr>
                                    <th scope="col" className="px-3 py-2">{family.option1?.name}{family.option2 ? ` / ${family.option2.name}` : ''}</th>
                                    <th scope="col" className="px-3 py-2">{t('products.col_sku')}</th>
                                    <th scope="col" className="px-3 py-2 text-right">{t('products.col_price')}</th>
                                    {canViewCost ? <th scope="col" className="px-3 py-2 text-right">{t('products.col_cost')}</th> : null}
                                    <th scope="col" className="px-3 py-2"><span className="sr-only">{t('common.actions')}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                {first.flatMap((a) =>
                                    rowsFor.map((b) => {
                                        const key = rowKey(a.id, b?.id ?? null);
                                        const cell = cells[key];
                                        const label = b ? `${a.value} / ${b.value}` : a.value;
                                        const variant = variants.find((v) => v.option1_value_id === a.id && v.option2_value_id === (b?.id ?? null));
                                        if (!cell) {
                                            return null;
                                        }

                                        return (
                                            <tr key={key} className="border-t">
                                                <th scope="row" className="px-3 py-2 text-left font-medium">{label}</th>
                                                <td className="px-3 py-2">
                                                    <Input className="num min-h-11 md:min-h-9" aria-label={`${t('products.col_sku')} ${label}`} value={cell.sku} disabled={cell.exists} onChange={(e) => patch(key, { sku: e.target.value })} />
                                                </td>
                                                <td className="px-3 py-2"><MoneyInput value={cell.sale_price} onChange={(v) => patch(key, { sale_price: v })} /></td>
                                                {canViewCost ? <td className="px-3 py-2"><MoneyInput value={cell.cost_price} onChange={(v) => patch(key, { cost_price: v })} /></td> : null}
                                                <td className="px-3 py-2 text-right">
                                                    {variant ? (
                                                        <Button asChild variant="ghost" className="min-h-11">
                                                            <Link href={`/app/products/${variant.uuid}/edit`}>{t('actions.edit')}</Link>
                                                        </Button>
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground">{t('products.not_created')}</span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    }),
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                {candidates.length > 0 ? (
                    <section aria-labelledby="attach-title" className="grid max-w-180 gap-3">
                        <h2 id="attach-title" className="text-xl font-semibold">{t('products.group_existing')}</h2>
                        <div className="grid gap-3 lg:grid-cols-2">
                            <FormField label={t('products.product')} error={errors['product_id']}>
                                <NativeSelect value={attach.product_id} onChange={(e) => setAttach({ ...attach, product_id: e.target.value })}>
                                    <option value="">{t('common.choose')}</option>
                                    {candidates.map((c) => <option key={c.id} value={c.id}>{c.name} · {c.sku}</option>)}
                                </NativeSelect>
                            </FormField>
                            <FormField label={family.option1?.name ?? ''} error={errors['option1_value_id']}>
                                <NativeSelect value={attach.option1_value_id} onChange={(e) => setAttach({ ...attach, option1_value_id: e.target.value })}>
                                    <option value="">{t('common.choose')}</option>
                                    {first.map((v) => <option key={v.id} value={v.id}>{v.value}</option>)}
                                </NativeSelect>
                            </FormField>
                            {family.option2 ? (
                                <FormField label={family.option2.name} error={errors['option2_value_id']}>
                                    <NativeSelect value={attach.option2_value_id} onChange={(e) => setAttach({ ...attach, option2_value_id: e.target.value })}>
                                        <option value="">{t('common.choose')}</option>
                                        {second.map((v) => <option key={v.id} value={v.id}>{v.value}</option>)}
                                    </NativeSelect>
                                </FormField>
                            ) : null}
                        </div>
                        <div>
                            <Button
                                variant="outline"
                                className="min-h-11"
                                disabled={processing || attach.product_id === '' || attach.option1_value_id === ''}
                                onClick={() => submit('post', `/app/products/families/${family.uuid}/members`, attach)}
                            >
                                <Plus aria-hidden="true" /> {t('products.group_confirm')}
                            </Button>
                        </div>
                    </section>
                ) : null}
            </div>
        </>
    );
}
