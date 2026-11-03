import { Head, Link, router } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { Layers, Save, Wand2 } from 'lucide-react';
import { useState } from 'react';
import { FormField } from '@/components/form-field';
import { FormSection } from '@/components/form-section';
import { MoneyInput } from '@/components/money-input';
import { NativeSelect } from '@/components/native-select';
import { QtyStepper } from '@/components/qty-stepper';
import { Upload } from '@/components/upload';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFormSubmit } from '@/hooks/use-form-submit';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import {
    EMPTY_VARIANTS,
    gridFor,
    VariantBuilder,
    type VariantDraft,
    type VariantOptionDef,
} from './variant-builder';

type Option = { id: number; name: string };
type Product = {
    id: number;
    uuid: string;
    name: string;
    name_bn: string | null;
    sku: string;
    barcode: string | null;
    hs_code: string | null;
    type: 'standard' | 'service';
    category_id: number | null;
    brand_id: number | null;
    unit_id: number;
    tax_rate_id: number | null;
    default_vendor_id: number | null;
    sale_price: number;
    cost_price: number | null;
    min_sale_price: number | null;
    min_sale_qty: number;
    reorder_level: number;
    track_stock: boolean;
    is_active: boolean;
    description: string | null;
    image_url: string | null;
    family_id: number | null;
    family_uuid: string | null;
    family_code: string | null;
    variant_label: string | null;
};
type Family = {
    id: number;
    uuid: string;
    code: string;
    name: string;
    option1: VariantOptionDef | null;
    option2: VariantOptionDef | null;
};

type Props = {
    product: Product | null;
    categories: Option[];
    brands: Option[];
    units: {
        id: number;
        name: string;
        short_name: string;
        allows_decimal: boolean;
    }[];
    taxRates: { id: number; name: string; rate_bp: number }[];
    vendors: Option[];
    variantOptions: VariantOptionDef[];
    families?: Family[];
    canViewCost?: boolean;
};

const idOrNull = (v: string): number | null => (v === '' ? null : Number(v));

export default function ProductForm({
    product,
    categories,
    brands,
    units,
    taxRates,
    vendors,
    variantOptions,
    families = [],
    canViewCost = true,
}: Props) {
    const { t } = useT();
    const { submit, processing, errors } = useFormSubmit();
    const editing = product !== null;

    const [f, setF] = useState({
        name: product?.name ?? '',
        name_bn: product?.name_bn ?? '',
        sku: product?.sku ?? '',
        barcode: product?.barcode ?? '',
        hs_code: product?.hs_code ?? '',
        type: product?.type ?? 'standard',
        category_id: product?.category_id ?? null,
        brand_id: product?.brand_id ?? null,
        unit_id: product?.unit_id ?? units[0]?.id ?? null,
        tax_rate_id: product?.tax_rate_id ?? null,
        default_vendor_id: product?.default_vendor_id ?? null,
        sale_price: product?.sale_price ?? (null as number | null),
        cost_price: product?.cost_price ?? (null as number | null),
        min_sale_price: product?.min_sale_price ?? (null as number | null),
        min_sale_qty: product?.min_sale_qty ?? 0,
        reorder_level: product?.reorder_level ?? 0,
        track_stock: product?.track_stock ?? true,
        is_active: product?.is_active ?? true,
        description: product?.description ?? '',
    });
    const set = <K extends keyof typeof f>(key: K, value: (typeof f)[K]) =>
        setF((prev) => ({ ...prev, [key]: value }));

    const [image, setImage] = useState<File | null>(null);
    const [removeImage, setRemoveImage] = useState(false);
    const [withVariants, setWithVariants] = useState(false);
    const [options, setOptions] = useState(variantOptions);
    const [draft, setDraft] = useState<VariantDraft>(EMPTY_VARIANTS);
    const [groupOpen, setGroupOpen] = useState(false);

    const save = () => {
        const payload: Record<string, unknown> = {
            ...f,
            hs_code: f.hs_code || null,
            barcode: withVariants ? null : f.barcode || null,
            track_stock: f.type === 'service' ? false : f.track_stock,
            cost_price: canViewCost ? f.cost_price : null,
        };
        if (image) {
            payload['image'] = image;
        }
        if (editing && removeImage) {
            payload['remove_image'] = true;
        }
        if (!editing && withVariants && draft.option1_id !== null) {
            payload['variants'] = {
                option1_id: draft.option1_id,
                option2_id: draft.option2_id,
                rows: gridFor(options, draft, f.sku).map((r) => ({
                    option1_value_id: r.option1_value_id,
                    option2_value_id: r.option2_value_id,
                    sale_price: r.sale_price,
                    cost_price: r.cost_price,
                    sku: r.sku,
                    barcode: r.barcode || null,
                })),
            };
        }
        submit(
            editing ? 'put' : 'post',
            editing ? `/app/products/${product.uuid}` : '/app/products',
            payload,
            { files: image !== null || withVariants },
        );
    };

    useHotkeys('form', [
        {
            id: 'products.save',
            keys: 'mod+s',
            labelKey: 'hotkeys.save',
            allowInInput: true,
            handler: (event) => {
                event.preventDefault();
                if (!processing) {
                    save();
                }
            },
        },
    ]);

    const err = (key: string) => errors[key] || undefined;
    const money = (key: 'sale_price' | 'cost_price' | 'min_sale_price') => (
        <MoneyInput
            value={f[key]}
            onChange={(v) => set(key, v)}
            invalid={Boolean(err(key))}
        />
    );

    return (
        <>
            <Head title={editing ? t('products.edit') : t('products.new')} />
            <form
                className="flex max-w-5xl flex-col gap-8 p-page"
                onSubmit={(e) => {
                    e.preventDefault();
                    save();
                }}
                noValidate
            >
                <PageHeader
                    title={editing ? t('products.edit') : t('products.new')}
                    actions={
                        <>
                            {editing && product.family_uuid !== null ? (
                                <Button asChild variant="outline">
                                    <Link
                                        href={`/app/products/families/${product.family_uuid}`}
                                    >
                                        <Layers aria-hidden="true" />{' '}
                                        {t('products.open_family')}
                                    </Link>
                                </Button>
                            ) : null}
                            {editing &&
                            product.family_id === null &&
                            families.length > 0 ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setGroupOpen(true)}
                                >
                                    <Layers aria-hidden="true" />{' '}
                                    {t('products.group')}
                                </Button>
                            ) : null}
                        </>
                    }
                />
                {err('form') ? (
                    <p
                        role="alert"
                        className="rounded-md bg-danger-soft p-3 text-sm text-destructive"
                    >
                        {err('form')}
                    </p>
                ) : null}
                {editing && product.variant_label ? (
                    <p className="text-sm text-muted-foreground">
                        {t('products.variant_of', {
                            code: product.family_code ?? '',
                            label: product.variant_label,
                        })}
                    </p>
                ) : null}

                <FormSection title={t('products.identity')}>
                    <FormField label={t('products.name')} error={err('name')}>
                        <Input
                            className="min-h-11"
                            value={f.name}
                            onChange={(e) => set('name', e.target.value)}
                            autoFocus
                        />
                    </FormField>
                    <FormField
                        label={t('products.name_bn')}
                        error={err('name_bn')}
                    >
                        <Input
                            className="min-h-11"
                            lang="bn"
                            value={f.name_bn}
                            onChange={(e) => set('name_bn', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        label={
                            withVariants
                                ? t('products.family_code')
                                : t('products.sku')
                        }
                        error={err('sku')}
                        hint={
                            withVariants
                                ? t('products.family_code_hint')
                                : undefined
                        }
                    >
                        <Input
                            className="num min-h-11"
                            value={f.sku}
                            onChange={(e) => set('sku', e.target.value)}
                        />
                    </FormField>
                    {!withVariants ? (
                        <FormField
                            label={t('products.barcode')}
                            error={err('barcode')}
                            hint={
                                f.barcode === '' && !editing
                                    ? t('products.barcode_auto')
                                    : undefined
                            }
                        >
                            <div className="flex gap-2">
                                <Input
                                    className="num min-h-11"
                                    value={f.barcode}
                                    onChange={(e) =>
                                        set('barcode', e.target.value)
                                    }
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-h-11"
                                    onClick={() => set('barcode', '')}
                                    disabled={f.barcode === ''}
                                >
                                    <Wand2 aria-hidden="true" />{' '}
                                    {t('products.barcode_gen')}
                                </Button>
                            </div>
                        </FormField>
                    ) : null}
                    <FormField label={t('products.type')} error={err('type')}>
                        <NativeSelect
                            value={f.type}
                            onChange={(e) =>
                                set(
                                    'type',
                                    e.target.value as 'standard' | 'service',
                                )
                            }
                        >
                            <option value="standard">
                                {t('products.type_standard')}
                            </option>
                            <option value="service">
                                {t('products.type_service')}
                            </option>
                        </NativeSelect>
                    </FormField>
                    <FormField
                        label={t('products.hs_code')}
                        error={err('hs_code')}
                    >
                        <Input
                            className="num min-h-11"
                            value={f.hs_code}
                            onChange={(e) => set('hs_code', e.target.value)}
                        />
                    </FormField>
                </FormSection>

                <FormSection title={t('products.classification')}>
                    <FormField
                        label={t('products.col_category')}
                        error={err('category_id')}
                    >
                        <NativeSelect
                            value={f.category_id ?? ''}
                            onChange={(e) =>
                                set('category_id', idOrNull(e.target.value))
                            }
                        >
                            <option value="">{t('common.none')}</option>
                            {categories.map((o) => (
                                <option key={o.id} value={o.id}>
                                    {o.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        label={t('products.col_brand')}
                        error={err('brand_id')}
                    >
                        <NativeSelect
                            value={f.brand_id ?? ''}
                            onChange={(e) =>
                                set('brand_id', idOrNull(e.target.value))
                            }
                        >
                            <option value="">{t('common.none')}</option>
                            {brands.map((o) => (
                                <option key={o.id} value={o.id}>
                                    {o.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        label={t('products.unit')}
                        error={err('unit_id')}
                    >
                        <NativeSelect
                            value={f.unit_id ?? ''}
                            onChange={(e) =>
                                set('unit_id', idOrNull(e.target.value))
                            }
                        >
                            {units.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name} ({u.short_name})
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        label={t('products.tax')}
                        error={err('tax_rate_id')}
                    >
                        <NativeSelect
                            value={f.tax_rate_id ?? ''}
                            onChange={(e) =>
                                set('tax_rate_id', idOrNull(e.target.value))
                            }
                        >
                            <option value="">{t('common.none')}</option>
                            {taxRates.map((r) => (
                                <option key={r.id} value={r.id}>
                                    {r.name} ({r.rate_bp / 100}%)
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        label={t('products.default_vendor')}
                        error={err('default_vendor_id')}
                    >
                        <NativeSelect
                            value={f.default_vendor_id ?? ''}
                            onChange={(e) =>
                                set(
                                    'default_vendor_id',
                                    idOrNull(e.target.value),
                                )
                            }
                        >
                            <option value="">{t('common.none')}</option>
                            {vendors.map((o) => (
                                <option key={o.id} value={o.id}>
                                    {o.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                </FormSection>

                <FormSection title={t('products.prices')}>
                    <FormField
                        label={t('products.col_price')}
                        error={err('sale_price')}
                    >
                        {money('sale_price')}
                    </FormField>
                    {canViewCost ? (
                        <FormField
                            label={t('products.col_cost')}
                            error={err('cost_price')}
                        >
                            {money('cost_price')}
                        </FormField>
                    ) : null}
                    <FormField
                        label={t('products.min_sale_price')}
                        error={err('min_sale_price')}
                        hint={t('products.min_sale_price_hint')}
                    >
                        {money('min_sale_price')}
                    </FormField>
                    <FormField
                        label={t('products.min_sale_qty')}
                        error={err('min_sale_qty')}
                    >
                        <QtyStepper
                            value={f.min_sale_qty}
                            onChange={(v) => set('min_sale_qty', v)}
                            aria-label={t('products.min_sale_qty')}
                        />
                    </FormField>
                </FormSection>

                <FormSection title={t('products.stock_section')}>
                    {f.type === 'standard' ? (
                        <>
                            <div className="flex items-center gap-2 lg:col-span-2">
                                <Checkbox
                                    id="track_stock"
                                    checked={f.track_stock}
                                    onCheckedChange={(v) =>
                                        set('track_stock', v === true)
                                    }
                                />
                                <Label htmlFor="track_stock">
                                    {t('products.track_stock')}
                                </Label>
                            </div>
                            <FormField
                                label={t('products.reorder_level')}
                                error={err('reorder_level')}
                                hint={t('products.reorder_hint')}
                            >
                                <QtyStepper
                                    value={f.reorder_level}
                                    onChange={(v) => set('reorder_level', v)}
                                    aria-label={t('products.reorder_level')}
                                />
                            </FormField>
                        </>
                    ) : (
                        <p className="text-sm text-muted-foreground lg:col-span-2">
                            {t('products.service_hint')}
                        </p>
                    )}
                    <div className="flex items-center gap-2 lg:col-span-2">
                        <Checkbox
                            id="is_active"
                            checked={f.is_active}
                            onCheckedChange={(v) =>
                                set('is_active', v === true)
                            }
                        />
                        <Label htmlFor="is_active">
                            {t('products.status_active')}
                        </Label>
                    </div>
                </FormSection>

                <FormSection title={t('products.image_section')}>
                    <div className="grid gap-2 lg:col-span-2">
                        {product?.image_url && !removeImage ? (
                            <div className="flex items-center gap-3">
                                <img
                                    src={product.image_url}
                                    alt=""
                                    className="size-20 rounded-md object-cover"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-h-11"
                                    onClick={() => setRemoveImage(true)}
                                >
                                    {t('products.remove_image')}
                                </Button>
                            </div>
                        ) : null}
                        <Upload
                            accept="image/png,image/jpeg,image/webp"
                            maxSizeMb={4}
                            onFiles={(files) => setImage(files[0] ?? null)}
                        />
                        {err('image') ? (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {err('image')}
                            </p>
                        ) : null}
                    </div>
                    <div className="grid gap-1.5 lg:col-span-2">
                        <Label htmlFor="description">
                            {t('products.description')}
                        </Label>
                        <textarea
                            id="description"
                            className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-base outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                            value={f.description}
                            onChange={(e) => set('description', e.target.value)}
                        />
                    </div>
                </FormSection>

                {!editing ? (
                    <div className="grid gap-4">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="with_variants"
                                checked={withVariants}
                                onCheckedChange={(v) =>
                                    setWithVariants(v === true)
                                }
                            />
                            <Label htmlFor="with_variants">
                                {t('products.has_variants')}
                            </Label>
                        </div>
                        {withVariants ? (
                            <>
                                <VariantBuilder
                                    options={options}
                                    onOptionsChange={setOptions}
                                    draft={draft}
                                    onChange={setDraft}
                                    familyCode={f.sku}
                                    canViewCost={canViewCost}
                                />
                                {err('variants') || err('variants.rows') ? (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {err('variants') ||
                                            err('variants.rows')}
                                    </p>
                                ) : null}
                            </>
                        ) : null}
                    </div>
                ) : null}

                <div className="flex gap-2">
                    <Button
                        type="submit"
                        className="min-h-11"
                        disabled={processing}
                    >
                        <Save aria-hidden="true" /> {t('common.save')}
                    </Button>
                    <Button asChild variant="outline" className="min-h-11">
                        <Link href="/app/products">{t('common.cancel')}</Link>
                    </Button>
                </div>
            </form>

            {editing ? (
                <GroupDialog
                    open={groupOpen}
                    onOpenChange={setGroupOpen}
                    families={families}
                    productId={product.id}
                />
            ) : null}
        </>
    );
}

function GroupDialog({
    open,
    onOpenChange,
    families,
    productId,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    families: Family[];
    productId: number;
}) {
    const { t } = useT();
    const [familyId, setFamilyId] = useState<number | null>(
        families[0]?.id ?? null,
    );
    const [v1, setV1] = useState<number | null>(null);
    const [v2, setV2] = useState<number | null>(null);
    const family = families.find((x) => x.id === familyId) ?? null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-140">
                <DialogHeader>
                    <DialogTitle>{t('products.group')}</DialogTitle>
                </DialogHeader>
                <div className="grid gap-4">
                    <FormField label={t('products.family')}>
                        <NativeSelect
                            value={familyId ?? ''}
                            onChange={(e) => {
                                setFamilyId(idOrNull(e.target.value));
                                setV1(null);
                                setV2(null);
                            }}
                        >
                            {families.map((x) => (
                                <option key={x.id} value={x.id}>
                                    {x.code} · {x.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    {family?.option1 ? (
                        <FormField label={family.option1.name}>
                            <NativeSelect
                                value={v1 ?? ''}
                                onChange={(e) =>
                                    setV1(idOrNull(e.target.value))
                                }
                            >
                                <option value="">{t('common.choose')}</option>
                                {family.option1.values.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.value}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                    ) : null}
                    {family?.option2 ? (
                        <FormField label={family.option2.name}>
                            <NativeSelect
                                value={v2 ?? ''}
                                onChange={(e) =>
                                    setV2(idOrNull(e.target.value))
                                }
                            >
                                <option value="">{t('common.choose')}</option>
                                {family.option2.values.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.value}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                    ) : null}
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common.cancel')}
                    </Button>
                    <Button
                        type="button"
                        className="min-h-11"
                        disabled={
                            family === null ||
                            v1 === null ||
                            (family.option2 !== null && v2 === null)
                        }
                        onClick={() =>
                            family &&
                            router.post(
                                `/app/products/families/${family.uuid}/members`,
                                {
                                    product_id: productId,
                                    option1_value_id: v1,
                                    option2_value_id: v2,
                                },
                            )
                        }
                    >
                        {t('products.group_confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
