import { Head, Link } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { Check, Trash2 } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { FormField } from '@/components/form-field';
import { MoneyInput } from '@/components/money-input';
import { NativeSelect } from '@/components/native-select';
import type { PaymentMethodOption } from '@/components/pay-dialog';
import { QtyStepper } from '@/components/qty-stepper';
import { ScanField, type ScanProduct } from '@/components/scan-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useDigits } from '@/hooks/use-digits';
import { useFormSubmit } from '@/hooks/use-form-submit';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import {
    addLine,
    lineNet,
    purchaseTotals,
    type Line,
} from '@/lib/purchase-lines';

export default function PurchaseCreate({
    vendors,
    paymentMethods,
    canPay,
    today,
}: {
    vendors: { id: number; name: string }[];
    paymentMethods: PaymentMethodOption[];
    canPay: boolean;
    today: string;
}) {
    const { t } = useT();
    const digits = useDigits();
    const { submit, processing, errors } = useFormSubmit();
    const scan = useRef<HTMLInputElement>(null);

    const preset =
        typeof window === 'undefined'
            ? null
            : new URLSearchParams(window.location.search).get('vendor');
    const [vendorId, setVendorId] = useState(
        preset && vendors.some((v) => String(v.id) === preset) ? preset : '',
    );
    const [vendorRef, setVendorRef] = useState('');
    const [date, setDate] = useState(today);
    const [note, setNote] = useState('');
    const [lines, setLines] = useState<Line[]>([]);
    const [discount, setDiscount] = useState<number | null>(null);
    const [shipping, setShipping] = useState<number | null>(null);
    const [paid, setPaid] = useState<number | null>(null);
    const [methodId, setMethodId] = useState(
        paymentMethods[0] ? String(paymentMethods[0].id) : '',
    );

    const totals = useMemo(
        () => purchaseTotals(lines, discount ?? 0, shipping ?? 0),
        [lines, discount, shipping],
    );

    const onPick = (p: ScanProduct) =>
        setLines((prev) =>
            addLine(prev, {
                product_id: p.id,
                name: p.variant_label
                    ? `${p.name} · ${p.variant_label}`
                    : p.name,
                sku: p.sku,
                unit_cost: p.cost_price,
            }),
        );
    const setLine = (index: number, patch: Partial<Line>) =>
        setLines((prev) =>
            prev.map((l, i) => (i === index ? { ...l, ...patch } : l)),
        );

    const receive = () =>
        submit('post', '/app/purchases', {
            vendor_id: vendorId === '' ? null : Number(vendorId),
            vendor_ref: vendorRef || null,
            purchased_at: date,
            discount: discount ?? 0,
            shipping: shipping ?? 0,
            note: note || null,
            items: lines.map((l) => ({
                product_id: l.product_id,
                qty: l.qty,
                unit_cost: l.unit_cost,
                discount: l.discount,
            })),
            ...(canPay && paid !== null && paid > 0
                ? {
                      payment: {
                          amount: paid,
                          payment_method_id:
                              methodId === '' ? null : Number(methodId),
                      },
                  }
                : {}),
        });

    useHotkeys('form', [
        {
            id: 'purchase.receive',
            keys: 'mod+enter',
            labelKey: 'purchases.receive',
            allowInInput: true,
            handler: (e) => {
                e.preventDefault();
                if (!processing && lines.length > 0) receive();
            },
        },
        {
            id: 'purchase.scan',
            keys: 'f2',
            labelKey: 'scan.label',
            allowInInput: true,
            handler: (e) => {
                e.preventDefault();
                scan.current?.focus();
            },
        },
    ]);

    const err = (key: string) => errors[key] || undefined;
    const lineError = Object.entries(errors).find(([k]) =>
        k.startsWith('items'),
    )?.[1];

    return (
        <>
            <Head title={t('purchases.new')} />
            <form
                className="flex max-w-6xl flex-col gap-6 p-page"
                noValidate
                onSubmit={(e) => {
                    e.preventDefault();
                    receive();
                }}
            >
                <PageHeader title={t('purchases.new')} />
                <div className="grid gap-4 lg:grid-cols-4">
                    <FormField
                        label={t('purchases.vendor')}
                        error={err('vendor_id')}
                    >
                        <NativeSelect
                            value={vendorId}
                            onChange={(e) => setVendorId(e.target.value)}
                        >
                            <option value="">{t('common.choose')}</option>
                            {vendors.map((v) => (
                                <option key={v.id} value={v.id}>
                                    {v.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        label={t('purchases.vendor_ref')}
                        error={err('vendor_ref')}
                    >
                        <Input
                            className="min-h-11"
                            value={vendorRef}
                            onChange={(e) => setVendorRef(e.target.value)}
                        />
                    </FormField>
                    <FormField
                        label={t('purchases.date')}
                        error={err('purchased_at')}
                    >
                        <Input
                            className="min-h-11"
                            type="date"
                            value={date}
                            onChange={(e) => setDate(e.target.value)}
                        />
                    </FormField>
                    <FormField label={t('purchases.note')} error={err('note')}>
                        <Input
                            className="min-h-11"
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                        />
                    </FormField>
                </div>

                <section aria-labelledby="pl-title" className="grid gap-3">
                    <h2 id="pl-title" className="text-xl font-semibold">
                        {t('purchases.items')}
                    </h2>
                    <ScanField
                        ref={scan}
                        lookupUrl="/app/purchases/lookup"
                        onPick={onPick}
                    />
                    {lineError ? (
                        <p role="alert" className="text-sm text-destructive">
                            {lineError}
                        </p>
                    ) : null}
                    {lines.length === 0 ? (
                        <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                            {t('purchases.scan_hint')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <caption className="sr-only">
                                    {t('purchases.items')}
                                </caption>
                                <thead className="bg-muted text-left">
                                    <tr>
                                        <th scope="col" className="px-3 py-2">
                                            {t('products.product')}
                                        </th>
                                        <th scope="col" className="px-3 py-2">
                                            {t('purchases.qty')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2 text-right"
                                        >
                                            {t('purchases.unit_cost')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2 text-right"
                                        >
                                            {t('purchases.line_discount')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2 text-right"
                                        >
                                            {t('purchases.line_total')}
                                        </th>
                                        <th scope="col" className="px-3 py-2">
                                            <span className="sr-only">
                                                {t('common.actions')}
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {lines.map((l, i) => (
                                        <tr
                                            key={l.product_id}
                                            className="border-t"
                                        >
                                            <th
                                                scope="row"
                                                className="px-3 py-2 text-left font-medium"
                                            >
                                                {l.name}
                                                <span className="num block text-xs font-normal text-muted-foreground">
                                                    {l.sku}
                                                </span>
                                            </th>
                                            <td className="px-3 py-2">
                                                <QtyStepper
                                                    value={l.qty}
                                                    min={1}
                                                    onChange={(v) =>
                                                        setLine(i, { qty: v })
                                                    }
                                                    aria-label={`${t('purchases.qty')} ${l.name}`}
                                                />
                                            </td>
                                            <td className="px-3 py-2">
                                                <MoneyInput
                                                    value={l.unit_cost}
                                                    onChange={(v) =>
                                                        setLine(i, {
                                                            unit_cost: v ?? 0,
                                                        })
                                                    }
                                                />
                                            </td>
                                            <td className="px-3 py-2">
                                                <MoneyInput
                                                    value={l.discount}
                                                    onChange={(v) =>
                                                        setLine(i, {
                                                            discount: v ?? 0,
                                                        })
                                                    }
                                                />
                                            </td>
                                            <td className="num px-3 py-2 text-right">
                                                {digits.money(lineNet(l))}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-11"
                                                    aria-label={`${t('purchases.remove_line')} ${l.name}`}
                                                    onClick={() =>
                                                        setLines((prev) =>
                                                            prev.filter(
                                                                (_, idx) =>
                                                                    idx !== i,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Trash2 aria-hidden="true" />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="grid content-start gap-3">
                        {canPay ? (
                            <>
                                <FormField
                                    label={t('purchases.pay_now')}
                                    error={err('payment.amount')}
                                    hint={t('purchases.pay_now_hint')}
                                >
                                    <MoneyInput
                                        value={paid}
                                        onChange={setPaid}
                                    />
                                </FormField>
                                {paid !== null && paid > 0 ? (
                                    <FormField
                                        label={t('vendors.pay_method')}
                                        error={err('payment.payment_method_id')}
                                    >
                                        <NativeSelect
                                            value={methodId}
                                            onChange={(e) =>
                                                setMethodId(e.target.value)
                                            }
                                        >
                                            {paymentMethods.map((m) => (
                                                <option key={m.id} value={m.id}>
                                                    {m.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </FormField>
                                ) : null}
                            </>
                        ) : null}
                    </div>
                    <dl className="grid content-start gap-2 rounded-lg border p-4">
                        <div className="flex justify-between">
                            <dt>{t('purchases.subtotal')}</dt>
                            <dd className="num">
                                {digits.money(totals.subtotal)}
                            </dd>
                        </div>
                        <div className="flex items-center justify-between gap-4">
                            <dt>
                                <label htmlFor="order-discount">
                                    {t('purchases.order_discount')}
                                </label>
                            </dt>
                            <dd className="w-44">
                                <MoneyInput
                                    id="order-discount"
                                    value={discount}
                                    onChange={setDiscount}
                                />
                            </dd>
                        </div>
                        <div className="flex items-center justify-between gap-4">
                            <dt>
                                <label htmlFor="shipping">
                                    {t('purchases.shipping')}
                                </label>
                            </dt>
                            <dd className="w-44">
                                <MoneyInput
                                    id="shipping"
                                    value={shipping}
                                    onChange={setShipping}
                                />
                            </dd>
                        </div>
                        <div className="flex justify-between border-t pt-2 text-lg font-bold">
                            <dt>{t('purchases.total')}</dt>
                            <dd className="num">
                                {digits.money(totals.total)}
                            </dd>
                        </div>
                        {paid !== null && paid > 0 ? (
                            <div className="flex justify-between text-sm text-muted-foreground">
                                <dt>{t('purchases.col_due')}</dt>
                                <dd className="num">
                                    {digits.money(
                                        Math.max(totals.total - paid, 0),
                                    )}
                                </dd>
                            </div>
                        ) : null}
                    </dl>
                </div>

                <div className="flex gap-2">
                    <Button
                        type="submit"
                        className="min-h-11"
                        disabled={
                            processing || lines.length === 0 || vendorId === ''
                        }
                    >
                        <Check aria-hidden="true" /> {t('purchases.receive')}
                    </Button>
                    <Button asChild variant="outline" className="min-h-11">
                        <Link href="/app/purchases">{t('common.cancel')}</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}
