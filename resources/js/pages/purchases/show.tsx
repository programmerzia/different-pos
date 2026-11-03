import { Head, Link, router } from '@inertiajs/react';
import { Page, PageHeader } from '@/components/page-header';
import { ArrowLeft, HandCoins, Ban } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PayDialog, type PaymentMethodOption } from '@/components/pay-dialog';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';

type Item = {
    id: number;
    product: string;
    variant_label: string | null;
    sku: string;
    qty: number;
    unit_cost: number;
    discount: number;
    line_total: number;
};
type Batch = {
    id: number;
    product_id: number;
    batch_no: string | null;
    unit_cost: number;
    qty_received: number;
    qty_remaining: number;
    expires_at: string | null;
};
type Payment = {
    id: number;
    number: string;
    paid_at: string | null;
    amount: number;
    reference: string | null;
};

export default function PurchaseShow({
    purchase,
    items,
    batches,
    payments,
    paymentMethods,
    can,
}: {
    purchase: {
        id: number;
        uuid: string;
        vendor_uuid: string;
        number: string;
        vendor_id: number;
        vendor: string;
        vendor_ref: string | null;
        status: string;
        payment_status: string;
        purchased_at: string;
        subtotal: number;
        discount: number;
        tax_total: number;
        shipping: number;
        total: number;
        paid: number;
        due: number;
        note: string | null;
    };
    items: Item[];
    batches: Batch[];
    payments: Payment[];
    paymentMethods: PaymentMethodOption[];
    can: { cancel: boolean; pay: boolean };
}) {
    const { t } = useT();
    const digits = useDigits();
    const [paying, setPaying] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const received = purchase.status === 'received';

    return (
        <>
            <Head title={purchase.number} />
            <Page className="max-w-6xl gap-6">
                <Button asChild variant="ghost" className="-ml-3 w-fit">
                    <Link href="/app/purchases">
                        <ArrowLeft aria-hidden="true" /> {t('purchases.title')}
                    </Link>
                </Button>
                <PageHeader
                    title={<span className="num">{purchase.number}</span>}
                    subtitle={
                        <>
                            <Link
                                className="underline-offset-4 hover:underline"
                                href={`/app/vendors/${purchase.vendor_uuid}`}
                            >
                                {purchase.vendor}
                            </Link>
                            {' · '}
                            <span className="num">
                                {digits.n(purchase.purchased_at)}
                            </span>
                            {purchase.vendor_ref
                                ? ` · ${purchase.vendor_ref}`
                                : ''}
                        </>
                    }
                    actions={
                        <>
                            <StatusBadge
                                status={
                                    received
                                        ? purchase.payment_status
                                        : purchase.status
                                }
                            />
                            {received && can.pay && purchase.due > 0 ? (
                                <Button
                                    variant="outline"
                                    onClick={() => setPaying(true)}
                                >
                                    <HandCoins aria-hidden="true" />{' '}
                                    {t('vendors.pay')}
                                </Button>
                            ) : null}
                            {received && can.cancel ? (
                                <Button
                                    variant="outline"
                                    className="text-destructive"
                                    onClick={() => setCancelling(true)}
                                >
                                    <Ban aria-hidden="true" />{' '}
                                    {t('purchases.cancel')}
                                </Button>
                            ) : null}
                        </>
                    }
                />

                <section aria-labelledby="pi-title" className="grid gap-2">
                    <h2 id="pi-title" className="text-xl font-semibold">
                        {t('purchases.items')}
                    </h2>
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
                                    <th
                                        scope="col"
                                        className="px-3 py-2 text-right"
                                    >
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
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((i) => (
                                    <tr key={i.id} className="border-t">
                                        <th
                                            scope="row"
                                            className="px-3 py-2 text-left font-medium"
                                        >
                                            {i.product}
                                            {i.variant_label
                                                ? ` · ${i.variant_label}`
                                                : ''}
                                            <span className="num block text-xs font-normal text-muted-foreground">
                                                {i.sku}
                                            </span>
                                        </th>
                                        <td className="num px-3 py-2 text-right">
                                            {digits.qty(i.qty)}
                                        </td>
                                        <td className="num px-3 py-2 text-right">
                                            {digits.money(i.unit_cost)}
                                        </td>
                                        <td className="num px-3 py-2 text-right">
                                            {digits.money(i.discount)}
                                        </td>
                                        <td className="num px-3 py-2 text-right">
                                            {digits.money(i.line_total)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section
                        aria-labelledby="pb-title"
                        className="grid content-start gap-2"
                    >
                        <h2 id="pb-title" className="text-xl font-semibold">
                            {t('purchases.batches')}
                        </h2>
                        <ul className="divide-y rounded-lg border text-sm">
                            {batches.map((b) => (
                                <li
                                    key={b.id}
                                    className="flex flex-wrap justify-between gap-2 px-3 py-2"
                                >
                                    <span>{b.batch_no ?? `#${b.id}`}</span>
                                    <span className="num text-muted-foreground">
                                        {digits.qty(b.qty_remaining)} /{' '}
                                        {digits.qty(b.qty_received)} @{' '}
                                        {digits.money(b.unit_cost)}
                                    </span>
                                </li>
                            ))}
                            {batches.length === 0 ? (
                                <li className="px-3 py-2 text-muted-foreground">
                                    {t('purchases.no_batches')}
                                </li>
                            ) : null}
                        </ul>
                        {payments.length > 0 ? (
                            <>
                                <h2 className="mt-4 text-xl font-semibold">
                                    {t('purchases.payments')}
                                </h2>
                                <ul className="divide-y rounded-lg border text-sm">
                                    {payments.map((p) => (
                                        <li
                                            key={p.id}
                                            className="flex justify-between gap-2 px-3 py-2"
                                        >
                                            <span className="num">
                                                {p.number} ·{' '}
                                                {digits.n(p.paid_at ?? '')}
                                            </span>
                                            <span className="num">
                                                {digits.money(p.amount)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </>
                        ) : null}
                    </section>
                    <dl className="grid content-start gap-2 rounded-lg border p-4 text-sm">
                        <div className="flex justify-between">
                            <dt>{t('purchases.subtotal')}</dt>
                            <dd className="num">
                                {digits.money(purchase.subtotal)}
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt>{t('purchases.order_discount')}</dt>
                            <dd className="num">
                                {digits.money(purchase.discount)}
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt>{t('purchases.shipping')}</dt>
                            <dd className="num">
                                {digits.money(purchase.shipping)}
                            </dd>
                        </div>
                        <div className="flex justify-between border-t pt-2 text-lg font-bold">
                            <dt>{t('purchases.total')}</dt>
                            <dd className="num">
                                {digits.money(purchase.total)}
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt>{t('purchases.paid')}</dt>
                            <dd className="num">
                                {digits.money(purchase.paid)}
                            </dd>
                        </div>
                        <div className="flex justify-between font-medium">
                            <dt>{t('purchases.col_due')}</dt>
                            <dd className="num">
                                {digits.money(purchase.due)}
                            </dd>
                        </div>
                        {purchase.note ? (
                            <p className="border-t pt-2 text-muted-foreground">
                                {purchase.note}
                            </p>
                        ) : null}
                    </dl>
                </div>
            </Page>

            {can.pay ? (
                <PayDialog
                    open={paying}
                    onOpenChange={setPaying}
                    url={`/app/purchases/${purchase.uuid}/payments`}
                    due={purchase.due}
                    methods={paymentMethods}
                />
            ) : null}
            <ConfirmDialog
                open={cancelling}
                onOpenChange={setCancelling}
                title={t('purchases.cancel_title')}
                description={t('purchases.cancel_hint')}
                destructive
                confirmLabel={t('purchases.cancel')}
                onConfirm={() =>
                    router.post(`/app/purchases/${purchase.uuid}/cancel`)
                }
            />
        </>
    );
}
