import { Head, Link } from '@inertiajs/react';
import { Page, PageHeader } from '@/components/page-header';
import { HandCoins, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { PayDialog, type PaymentMethodOption } from '@/components/pay-dialog';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';

type Purchase = {
    id: number;
    uuid: string;
    number: string;
    status: string;
    payment_status: string;
    purchased_at: string;
    total: number;
    due: number;
};

export default function VendorShow({
    vendor,
    purchases,
    paymentMethods,
    canPay,
}: {
    vendor: {
        id: number;
        uuid: string;
        name: string;
        company: string | null;
        phone: string | null;
        email: string | null;
        address: string | null;
        notes: string | null;
        balance: number;
        is_active: boolean;
    };
    purchases: Purchase[];
    paymentMethods: PaymentMethodOption[];
    canPay: boolean;
}) {
    const { t } = useT();
    const can = useCan();
    const digits = useDigits();
    const [paying, setPaying] = useState(false);

    return (
        <>
            <Head title={vendor.name} />
            <Page className="max-w-5xl gap-6">
                <PageHeader
                    title={vendor.name}
                    subtitle={
                        <>
                            {[vendor.company, vendor.phone, vendor.email]
                                .filter(Boolean)
                                .join(' · ')}
                            {vendor.address ? (
                                <span className="block">{vendor.address}</span>
                            ) : null}
                        </>
                    }
                    actions={
                        <>
                            {can('purchases.create') ? (
                                <Button asChild>
                                    <Link
                                        href={`/app/purchases/create?vendor=${vendor.id}`}
                                    >
                                        <Plus aria-hidden="true" />{' '}
                                        {t('purchases.new')}
                                    </Link>
                                </Button>
                            ) : null}
                            {canPay && vendor.balance > 0 ? (
                                <Button
                                    variant="outline"
                                    onClick={() => setPaying(true)}
                                >
                                    <HandCoins aria-hidden="true" />{' '}
                                    {t('vendors.pay')}
                                </Button>
                            ) : null}
                            {can('vendors.update') ? (
                                <Button asChild variant="outline">
                                    <Link
                                        href={`/app/vendors/${vendor.uuid}/edit`}
                                    >
                                        <Pencil aria-hidden="true" />{' '}
                                        {t('actions.edit')}
                                    </Link>
                                </Button>
                            ) : null}
                        </>
                    }
                />

                <div className="rounded-lg border p-4">
                    <p className="text-sm text-muted-foreground">
                        {t('vendors.col_balance')}
                    </p>
                    <p className="num text-3xl font-bold">
                        {digits.money(vendor.balance)}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {vendor.balance > 0
                            ? t('vendors.balance_owed')
                            : vendor.balance < 0
                              ? t('vendors.balance_advance')
                              : t('vendors.balance_clear')}
                    </p>
                </div>

                <section aria-labelledby="vp-title" className="grid gap-2">
                    <h2 id="vp-title" className="text-xl font-semibold">
                        {t('purchases.title')}
                    </h2>
                    {purchases.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('vendors.no_purchases')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <caption className="sr-only">
                                    {t('purchases.title')}
                                </caption>
                                <thead className="bg-muted text-left">
                                    <tr>
                                        <th scope="col" className="px-3 py-2">
                                            {t('purchases.col_number')}
                                        </th>
                                        <th scope="col" className="px-3 py-2">
                                            {t('purchases.col_date')}
                                        </th>
                                        <th scope="col" className="px-3 py-2">
                                            {t('common.status')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2 text-right"
                                        >
                                            {t('purchases.col_total')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2 text-right"
                                        >
                                            {t('purchases.col_due')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {purchases.map((p) => (
                                        <tr key={p.id} className="border-t">
                                            <th
                                                scope="row"
                                                className="px-3 py-2 text-left font-medium"
                                            >
                                                <Link
                                                    className="underline-offset-4 hover:underline"
                                                    href={`/app/purchases/${p.uuid}`}
                                                >
                                                    {p.number}
                                                </Link>
                                            </th>
                                            <td className="num px-3 py-2">
                                                {digits.n(p.purchased_at)}
                                            </td>
                                            <td className="px-3 py-2">
                                                <StatusBadge
                                                    status={
                                                        p.status === 'received'
                                                            ? p.payment_status
                                                            : p.status
                                                    }
                                                />
                                            </td>
                                            <td className="num px-3 py-2 text-right">
                                                {digits.money(p.total)}
                                            </td>
                                            <td className="num px-3 py-2 text-right">
                                                {digits.money(p.due)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </Page>
            {canPay ? (
                <PayDialog
                    open={paying}
                    onOpenChange={setPaying}
                    url={`/app/vendors/${vendor.uuid}/payments`}
                    due={Math.max(vendor.balance, 0)}
                    methods={paymentMethods}
                />
            ) : null}
        </>
    );
}
