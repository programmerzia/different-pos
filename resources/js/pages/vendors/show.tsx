import { Head, Link, router } from '@inertiajs/react';
import { Page } from '@/components/page-header';
import {
    ArrowLeft,
    FileText,
    HandCoins,
    Pencil,
    Plus,
    Printer,
    Truck,
    User,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { KpiTile } from '@/components/kpi-tile';
import { PayDialog, type PaymentMethodOption } from '@/components/pay-dialog';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

type Purchase = {
    id: number;
    uuid: string;
    number: string;
    status: string;
    payment_status: string;
    purchased_at: string;
    branch: string | null;
    branch_code: string | null;
    total: number;
    paid: number;
    due: number;
};
type Payment = {
    id: number;
    number: string;
    paid_at: string | null;
    method: string | null;
    amount: number;
    reference: string | null;
    bills: string[];
};
type Tab = 'info' | 'purchases' | 'payments' | 'statement';

const TABS: { id: Tab; labelKey: string; icon: typeof User }[] = [
    { id: 'info', labelKey: 'vendors.tab_info', icon: User },
    { id: 'purchases', labelKey: 'vendors.tab_purchases', icon: Truck },
    { id: 'payments', labelKey: 'vendors.tab_payments', icon: HandCoins },
    { id: 'statement', labelKey: 'vendors.tab_statement', icon: FileText },
];

const TH = 'px-3 py-2.5 text-left font-semibold';
const TD = 'px-3 py-2.5';

/** Vendor 360 profile (render_catalog_stock_purchases.py "vendor_profile"): KPIs, tabs for registration, bills, disbursements and the statement ledger. */
export default function VendorShow({
    vendor,
    kpis,
    purchases,
    payments,
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
        hub: string | null;
        notes: string | null;
        balance: number;
        is_active: boolean;
    };
    kpis: {
        purchases_ytd: number;
        shipments_ytd: number;
        paid_total: number;
        open_bills: number;
        last_purchase_at: string | null;
        all_branches: boolean;
    };
    purchases: Purchase[];
    payments: Payment[];
    paymentMethods: PaymentMethodOption[];
    canPay: boolean;
}) {
    const { t } = useT();
    const can = useCan();
    const digits = useDigits();
    const [paying, setPaying] = useState(false);
    const [tab, setTab] = useState<Tab>('info');
    const payable = canPay && vendor.balance > 0;

    useHotkeys('page', [
        {
            id: 'vendor.pay',
            keys: 'p',
            labelKey: 'vendors.pay',
            handler: () => payable && setPaying(true),
        },
        {
            id: 'vendor.edit',
            keys: 'e',
            labelKey: 'actions.edit',
            handler: () =>
                can('vendors.update') &&
                router.visit(`/app/vendors/${vendor.uuid}/edit`),
        },
        {
            id: 'vendor.new_purchase',
            keys: 'n',
            labelKey: 'vendors.new_po',
            handler: () =>
                can('purchases.create') &&
                router.visit(`/app/purchases/create?vendor=${vendor.id}`),
        },
    ]);

    /** Bills (debit) and payments (credit) in date order with the running balance, oldest first. */
    const statement = useMemo(() => {
        const rows = [
            ...purchases
                .filter((p) => p.status === 'received')
                .map((p) => ({
                    key: `p${p.id}`,
                    date: p.purchased_at,
                    label: p.number,
                    href: `/app/purchases/${p.uuid}`,
                    debit: p.total,
                    credit: 0,
                })),
            ...payments.map((p) => ({
                key: `y${p.id}`,
                date: p.paid_at ?? '',
                label: `${p.number}${p.method ? ` · ${p.method}` : ''}`,
                href: null,
                debit: 0,
                credit: p.amount,
            })),
        ].sort(
            (a, b) =>
                a.date.localeCompare(b.date) || a.key.localeCompare(b.key),
        );
        return rows.reduce<((typeof rows)[number] & { running: number })[]>(
            (acc, r) => {
                const running =
                    (acc[acc.length - 1]?.running ?? 0) + r.debit - r.credit;

                return [...acc, { ...r, running }];
            },
            [],
        );
    }, [purchases, payments]);

    const detail = (label: string, value: string | null, mono = false) => (
        <div className="flex justify-between gap-4 py-1.5">
            <dt className="text-muted-foreground">{label}</dt>
            <dd
                className={cn(
                    'text-right font-medium',
                    mono && 'num',
                    !value && 'text-muted-foreground',
                )}
            >
                {value || '—'}
            </dd>
        </div>
    );

    return (
        <>
            <Head title={vendor.name} />
            <Page className="gap-4">
                <div className="flex flex-col gap-2 border-b pb-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-2.5">
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="h-8 text-xs font-semibold"
                        >
                            <Link href="/app/vendors">
                                <ArrowLeft aria-hidden="true" />
                                {t('vendors.back')}
                            </Link>
                        </Button>
                        <div
                            className="h-4 w-px bg-border"
                            aria-hidden="true"
                        />
                        <div>
                            <h1 className="flex items-center gap-2 text-base font-bold">
                                <span>{vendor.name}</span>
                                {vendor.company ? (
                                    <span className="text-xs font-normal text-muted-foreground">
                                        ({vendor.company})
                                    </span>
                                ) : null}
                                {!vendor.is_active ? (
                                    <StatusBadge status="inactive" />
                                ) : null}
                            </h1>
                            <p className="text-[11px] text-muted-foreground">
                                {t('vendors.profile_sub', {
                                    code: `V-${digits.n(vendor.id)}`,
                                    hub: vendor.hub ?? '—',
                                    contact: vendor.phone
                                        ? digits.n(vendor.phone)
                                        : '—',
                                })}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {canPay ? (
                            <Button
                                size="sm"
                                className="h-8 text-xs font-bold"
                                disabled={!payable}
                                onClick={() => setPaying(true)}
                            >
                                <HandCoins aria-hidden="true" />
                                {t('vendors.pay_allocation')}
                            </Button>
                        ) : null}
                        {can('purchases.create') ? (
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="h-8 text-xs font-semibold"
                            >
                                <Link
                                    href={`/app/purchases/create?vendor=${vendor.id}`}
                                >
                                    <Plus aria-hidden="true" />
                                    {t('vendors.new_po')}
                                </Link>
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-8 text-xs font-semibold"
                            onClick={() => {
                                setTab('statement');
                                window.setTimeout(() => window.print(), 50);
                            }}
                        >
                            <Printer aria-hidden="true" />
                            {t('vendors.export_statement')}
                        </Button>
                        {can('vendors.update') ? (
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="h-8 text-xs font-semibold"
                            >
                                <Link href={`/app/vendors/${vendor.uuid}/edit`}>
                                    <Pencil aria-hidden="true" />
                                    {t('actions.edit')}
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <KpiTile
                        label={t('vendors.kpi_purchases_ytd')}
                        value={digits.money(kpis.purchases_ytd)}
                        footnote={t('vendors.kpi_shipments', {
                            count: digits.n(kpis.shipments_ytd),
                        })}
                    />
                    <KpiTile
                        label={t('vendors.kpi_paid')}
                        value={
                            <span className="text-success">
                                {digits.money(kpis.paid_total)}
                            </span>
                        }
                        footnote={t('vendors.kpi_paid_note')}
                    />
                    <KpiTile
                        label={t('vendors.kpi_payable')}
                        value={
                            <span
                                className={cn(
                                    vendor.balance > 0 && 'text-destructive',
                                    vendor.balance < 0 && 'text-success',
                                )}
                            >
                                {digits.money(Math.abs(vendor.balance))}
                            </span>
                        }
                        footnote={
                            vendor.balance < 0
                                ? t('vendors.kpi_advance')
                                : kpis.open_bills > 0
                                  ? t('vendors.kpi_open_bills', {
                                        count: digits.n(kpis.open_bills),
                                    })
                                  : t('vendors.kpi_no_open_bills')
                        }
                    />
                    <KpiTile
                        label={t('vendors.kpi_terms')}
                        value={t('vendors.kpi_terms_value')}
                        footnote={
                            kpis.last_purchase_at
                                ? t('vendors.kpi_terms_note', {
                                      date: digits.n(kpis.last_purchase_at),
                                  })
                                : t('vendors.no_purchases')
                        }
                    />
                </div>

                <div
                    role="tablist"
                    aria-label={t('vendors.details')}
                    className="flex w-fit max-w-full items-center gap-1 overflow-x-auto rounded-xl border bg-surface-sunken p-1 text-xs font-semibold"
                >
                    {TABS.map(({ id, labelKey, icon: Icon }) => (
                        <button
                            key={id}
                            type="button"
                            role="tab"
                            id={`vendor-tab-${id}`}
                            aria-selected={tab === id}
                            aria-controls={`vendor-panel-${id}`}
                            onClick={() => setTab(id)}
                            className={cn(
                                'flex h-7 min-h-7 cursor-pointer items-center gap-1.5 rounded-lg px-3 whitespace-nowrap outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 [@media(pointer:coarse)]:min-h-11',
                                tab === id
                                    ? 'bg-card font-bold text-primary shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Icon className="size-3.5" aria-hidden="true" />
                            <span>{t(labelKey)}</span>
                        </button>
                    ))}
                </div>

                <div
                    role="tabpanel"
                    id={`vendor-panel-${tab}`}
                    aria-labelledby={`vendor-tab-${tab}`}
                    className="text-xs"
                >
                    {tab === 'info' ? (
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <section className="space-y-3 rounded-xl border bg-card p-4 shadow-xs">
                                <h2 className="text-xs font-bold tracking-wider uppercase">
                                    {t('vendors.registration')}
                                </h2>
                                <dl className="divide-y">
                                    {detail(
                                        t('vendors.legal_name'),
                                        vendor.name,
                                    )}
                                    {detail(
                                        t('vendors.company'),
                                        vendor.company,
                                    )}
                                    {detail(
                                        t('vendors.mobile'),
                                        vendor.phone
                                            ? digits.n(vendor.phone)
                                            : null,
                                        true,
                                    )}
                                    {detail(
                                        t('vendors.email_address'),
                                        vendor.email,
                                        true,
                                    )}
                                    {detail(t('vendors.hub'), vendor.hub)}
                                    {detail(
                                        t('vendors.hub_address'),
                                        vendor.address,
                                    )}
                                </dl>
                            </section>
                            <section className="space-y-3 rounded-xl border bg-card p-4 shadow-xs">
                                <h2 className="text-xs font-bold tracking-wider uppercase">
                                    {t('vendors.notes_terms')}
                                </h2>
                                <p
                                    className={cn(
                                        'rounded-xl border bg-surface-sunken p-3 whitespace-pre-wrap',
                                        !vendor.notes &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    {vendor.notes || t('vendors.no_notes')}
                                </p>
                                <dl className="divide-y">
                                    {detail(
                                        t('vendors.kpi_terms'),
                                        t('vendors.kpi_terms_value'),
                                    )}
                                    {detail(
                                        t('vendors.col_balance'),
                                        digits.money(vendor.balance),
                                        true,
                                    )}
                                </dl>
                            </section>
                        </div>
                    ) : null}

                    {tab === 'purchases' ? (
                        <section className="space-y-4 rounded-xl border bg-card p-4 shadow-xs">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <h2 className="text-xs font-bold tracking-wider uppercase">
                                        {t('vendors.purchases_title')}
                                    </h2>
                                    <p className="text-[11px] text-muted-foreground">
                                        {t('vendors.purchases_sub', {
                                            name: vendor.name,
                                        })}
                                        {kpis.all_branches
                                            ? ` · ${t('vendors.all_branches_note')}`
                                            : ''}
                                    </p>
                                </div>
                                {can('purchases.create') ? (
                                    <Button
                                        asChild
                                        size="sm"
                                        className="h-7 text-xs"
                                    >
                                        <Link
                                            href={`/app/purchases/create?vendor=${vendor.id}`}
                                        >
                                            <Plus aria-hidden="true" />
                                            {t('purchases.new')}
                                        </Link>
                                    </Button>
                                ) : null}
                            </div>
                            {purchases.length === 0 ? (
                                <p className="text-muted-foreground">
                                    {t('vendors.no_purchases')}
                                </p>
                            ) : (
                                <div className="overflow-x-auto rounded-xl border">
                                    <table className="w-full text-left text-xs">
                                        <caption className="sr-only">
                                            {t('vendors.purchases_title')}
                                        </caption>
                                        <thead className="border-b bg-surface-sunken text-muted-foreground">
                                            <tr>
                                                <th scope="col" className={TH}>
                                                    {t('vendors.col_grn')}
                                                </th>
                                                <th scope="col" className={TH}>
                                                    {t('purchases.col_date')}
                                                </th>
                                                <th scope="col" className={TH}>
                                                    {t('vendors.col_branch')}
                                                </th>
                                                <th
                                                    scope="col"
                                                    className={cn(
                                                        TH,
                                                        'text-right',
                                                    )}
                                                >
                                                    {t(
                                                        'vendors.col_bill_total',
                                                    )}
                                                </th>
                                                <th
                                                    scope="col"
                                                    className={cn(
                                                        TH,
                                                        'text-right',
                                                    )}
                                                >
                                                    {t('vendors.col_paid')}
                                                </th>
                                                <th
                                                    scope="col"
                                                    className={cn(
                                                        TH,
                                                        'text-right',
                                                    )}
                                                >
                                                    {t(
                                                        'vendors.col_balance_due',
                                                    )}
                                                </th>
                                                <th
                                                    scope="col"
                                                    className={cn(
                                                        TH,
                                                        'text-center',
                                                    )}
                                                >
                                                    {t('common.status')}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {purchases.map((p) => (
                                                <tr
                                                    key={p.id}
                                                    className="hover:bg-surface-sunken/50"
                                                >
                                                    <th
                                                        scope="row"
                                                        className={cn(
                                                            TD,
                                                            'num text-left font-bold text-primary',
                                                        )}
                                                    >
                                                        <Link
                                                            className="underline-offset-4 hover:underline"
                                                            href={`/app/purchases/${p.uuid}`}
                                                        >
                                                            {p.number}
                                                        </Link>
                                                    </th>
                                                    <td
                                                        className={cn(
                                                            TD,
                                                            'num text-muted-foreground',
                                                        )}
                                                    >
                                                        {digits.n(
                                                            p.purchased_at,
                                                        )}
                                                    </td>
                                                    <td className={TD}>
                                                        {p.branch
                                                            ? `${p.branch} [${p.branch_code ?? ''}]`
                                                            : '—'}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            TD,
                                                            'num text-right font-bold',
                                                        )}
                                                    >
                                                        {digits.money(p.total)}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            TD,
                                                            'num text-right text-success',
                                                        )}
                                                    >
                                                        {digits.money(p.paid)}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            TD,
                                                            'num text-right font-bold',
                                                            p.due > 0
                                                                ? 'text-destructive'
                                                                : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {digits.money(p.due)}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            TD,
                                                            'text-center',
                                                        )}
                                                    >
                                                        <StatusBadge
                                                            status={
                                                                p.status ===
                                                                'received'
                                                                    ? p.payment_status
                                                                    : p.status
                                                            }
                                                        />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                    ) : null}

                    {tab === 'payments' ? (
                        <section className="space-y-4 rounded-xl border bg-card p-4 shadow-xs">
                            <div>
                                <h2 className="text-xs font-bold tracking-wider uppercase">
                                    {t('vendors.payments_title')}
                                </h2>
                                <p className="text-[11px] text-muted-foreground">
                                    {t('vendors.payments_sub')}
                                </p>
                            </div>
                            {payments.length === 0 ? (
                                <p className="text-muted-foreground">
                                    {t('vendors.no_payments')}
                                </p>
                            ) : (
                                <div className="overflow-x-auto rounded-xl border">
                                    <table className="w-full text-left text-xs">
                                        <caption className="sr-only">
                                            {t('vendors.payments_title')}
                                        </caption>
                                        <thead className="border-b bg-surface-sunken text-muted-foreground">
                                            <tr>
                                                <th scope="col" className={TH}>
                                                    {t('vendors.col_voucher')}
                                                </th>
                                                <th scope="col" className={TH}>
                                                    {t('purchases.col_date')}
                                                </th>
                                                <th scope="col" className={TH}>
                                                    {t('vendors.col_method')}
                                                </th>
                                                <th
                                                    scope="col"
                                                    className={cn(
                                                        TH,
                                                        'text-right',
                                                    )}
                                                >
                                                    {t(
                                                        'vendors.col_amount_paid',
                                                    )}
                                                </th>
                                                <th scope="col" className={TH}>
                                                    {t('vendors.col_allocated')}
                                                </th>
                                                <th scope="col" className={TH}>
                                                    {t('vendors.col_reference')}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {payments.map((p) => (
                                                <tr
                                                    key={p.id}
                                                    className="hover:bg-surface-sunken/50"
                                                >
                                                    <th
                                                        scope="row"
                                                        className={cn(
                                                            TD,
                                                            'num text-left font-bold',
                                                        )}
                                                    >
                                                        {p.number}
                                                    </th>
                                                    <td
                                                        className={cn(
                                                            TD,
                                                            'num text-muted-foreground',
                                                        )}
                                                    >
                                                        {p.paid_at
                                                            ? digits.n(
                                                                  p.paid_at,
                                                              )
                                                            : '—'}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            TD,
                                                            'font-medium',
                                                        )}
                                                    >
                                                        {p.method ?? '—'}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            TD,
                                                            'num text-right font-bold text-success',
                                                        )}
                                                    >
                                                        {digits.money(p.amount)}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            TD,
                                                            'num text-primary',
                                                        )}
                                                    >
                                                        {p.bills.length > 0
                                                            ? p.bills.join(', ')
                                                            : t(
                                                                  'vendors.unallocated',
                                                              )}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            TD,
                                                            'num text-muted-foreground',
                                                        )}
                                                    >
                                                        {p.reference ?? '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                    ) : null}

                    {tab === 'statement' ? (
                        <section className="space-y-4 rounded-xl border bg-card p-4 shadow-xs">
                            <div>
                                <h2 className="text-xs font-bold tracking-wider uppercase">
                                    {t('vendors.statement_title')}
                                </h2>
                                <p className="text-[11px] text-muted-foreground">
                                    {t('vendors.statement_sub')}
                                </p>
                            </div>
                            {statement.length === 0 ? (
                                <p className="text-muted-foreground">
                                    {t('vendors.no_statement')}
                                </p>
                            ) : (
                                <div className="overflow-x-auto rounded-xl border">
                                    <table className="w-full text-left text-xs">
                                        <caption className="sr-only">
                                            {t('vendors.statement_title')}
                                        </caption>
                                        <thead className="border-b bg-surface-sunken text-muted-foreground">
                                            <tr>
                                                <th scope="col" className={TH}>
                                                    {t('purchases.col_date')}
                                                </th>
                                                <th scope="col" className={TH}>
                                                    {t(
                                                        'vendors.col_particulars',
                                                    )}
                                                </th>
                                                <th
                                                    scope="col"
                                                    className={cn(
                                                        TH,
                                                        'text-right',
                                                    )}
                                                >
                                                    {t('vendors.col_debit')}
                                                </th>
                                                <th
                                                    scope="col"
                                                    className={cn(
                                                        TH,
                                                        'text-right',
                                                    )}
                                                >
                                                    {t('vendors.col_credit')}
                                                </th>
                                                <th
                                                    scope="col"
                                                    className={cn(
                                                        TH,
                                                        'text-right',
                                                    )}
                                                >
                                                    {t('vendors.col_running')}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {statement.map((r) => (
                                                <tr key={r.key}>
                                                    <td
                                                        className={cn(
                                                            TD,
                                                            'num text-muted-foreground',
                                                        )}
                                                    >
                                                        {r.date
                                                            ? digits.n(r.date)
                                                            : '—'}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            TD,
                                                            'num font-medium',
                                                        )}
                                                    >
                                                        {r.href ? (
                                                            <Link
                                                                className="text-primary underline-offset-4 hover:underline"
                                                                href={r.href}
                                                            >
                                                                {r.label}
                                                            </Link>
                                                        ) : (
                                                            r.label
                                                        )}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            TD,
                                                            'num text-right',
                                                        )}
                                                    >
                                                        {r.debit
                                                            ? digits.money(
                                                                  r.debit,
                                                              )
                                                            : ''}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            TD,
                                                            'num text-right text-success',
                                                        )}
                                                    >
                                                        {r.credit
                                                            ? digits.money(
                                                                  r.credit,
                                                              )
                                                            : ''}
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            TD,
                                                            'num text-right font-bold',
                                                            r.running > 0
                                                                ? 'text-destructive'
                                                                : 'text-muted-foreground',
                                                        )}
                                                    >
                                                        {digits.money(
                                                            r.running,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                    ) : null}
                </div>
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
