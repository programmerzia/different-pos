import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    FileText,
    HandCoins,
    Printer,
    Repeat,
    Share2,
    Undo2,
} from 'lucide-react';
import { Fragment, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Kbd } from '@/components/kbd';
import { CollectDialog } from '@/components/sales/collect-dialog';
import { VoidSaleDialog } from '@/components/sales/void-dialog';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { actions } from '@/command/actions';
import { navigateTo } from '@/command/navigate';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { apiRequest } from '@/lib/api';
import {
    openPrint,
    salesUrl,
    type CustomerSummary,
    type MoneyMethod,
} from '@/lib/sales';
import { registerSaleActions } from '@/lib/sales-actions';

registerSaleActions();

type Sale = {
    id: number;
    number: string;
    status: string;
    payment_status: string;
    origin: string;
    sold_at: string | null;
    cashier: string | null;
    note: string | null;
    courier_name: string | null;
    courier_tracking: string | null;
    buyer_vat_bin: string | null;
    sync_flags: string[];
    subtotal: number;
    item_discount_total: number;
    order_discount: number;
    tax_total: number;
    shipping: number;
    rounding: number;
    total: number;
    paid: number;
    change_given: number;
    due: number;
    returned_total: number;
    cogs_total: number | null;
    voided_at: string | null;
    voided_by: string | null;
    void_reason: string | null;
    public_url: string | null;
};

type Line = {
    id: number;
    name: string;
    sku: string;
    qty: number;
    qty_returned: number;
    unit_price: number;
    discount: number;
    tax_rate_bp: number;
    tax_amount: number;
    line_total: number;
    cogs: number | null;
    batches:
        | {
              id: number;
              batch: string;
              received_at: string | null;
              qty: number;
              unit_cost: number;
          }[]
        | null;
};

type Props = {
    sale: Sale;
    customer: (CustomerSummary & { balance: number }) | null;
    lines: Line[];
    payments: {
        id: number;
        number: string;
        method: string;
        amount: number;
        payment_amount: number;
        reference: string | null;
        paid_at: string | null;
        voided: boolean;
        credit: boolean;
    }[];
    returns: {
        id: number;
        number: string;
        returned_at: string | null;
        total: number;
        refund_mode: string;
        exchange_sale_id: number | null;
    }[];
    exchange_of: {
        return_id: number;
        return_number: string;
        sale_id: number;
        sale_number: string;
    } | null;
    audit: { event: string; by: string | null; at: string | null }[];
    can: {
        void: boolean;
        collect: boolean;
        return: boolean;
        exchange: boolean;
        share: boolean;
    };
    methods: MoneyMethod[];
};

export default function SaleShow({
    sale,
    customer,
    lines,
    payments,
    returns,
    exchange_of: exchangeOf,
    audit,
    can,
    methods,
}: Props) {
    const { t } = useT();
    const digits = useDigits();
    const [voidOpen, setVoidOpen] = useState(false);
    const [collectOpen, setCollectOpen] = useState(false);
    const [shareUrl, setShareUrl] = useState<string | null>(sale.public_url);

    const share = async () => {
        try {
            const { url } = await apiRequest<{ url: string }>(
                salesUrl.share(sale.id),
                { method: 'POST', body: {} },
            );
            setShareUrl(url);
            await navigator.clipboard?.writeText(url);
            toast.success(t('sales.link_copied'));
        } catch {
            toast.error(t('sales.share_failed'));
        }
    };

    const buttons = [
        {
            id: 'print',
            keys: 'p',
            label: t('sales.print_receipt'),
            icon: Printer,
            show: true,
            run: () => openPrint(salesUrl.receipt(sale.id, '80')),
        },
        {
            id: 'print58',
            keys: 'alt+p',
            label: t('sales.print_58'),
            icon: Printer,
            show: true,
            run: () => openPrint(salesUrl.receipt(sale.id, '58')),
        },
        {
            id: 'a4',
            keys: 'shift+p',
            label: t('sales.print_a4'),
            icon: FileText,
            show: true,
            run: () => openPrint(salesUrl.receipt(sale.id, 'a4')),
        },
        {
            id: 'share',
            keys: 's',
            label: t('sales.share'),
            icon: Share2,
            show: can.share,
            run: () => void share(),
        },
        {
            id: 'collect',
            keys: 'c',
            label: t('sales.collect_due'),
            icon: HandCoins,
            show: can.collect,
            run: () => setCollectOpen(true),
        },
        {
            id: 'return',
            keys: 'r',
            label: t('sales.return'),
            icon: Undo2,
            show: can.return,
            run: () => navigateTo(salesUrl.returnCreate(sale.id)),
        },
        {
            id: 'exchange',
            keys: 'x',
            label: t('sales.exchange'),
            icon: Repeat,
            show: can.exchange,
            run: () => navigateTo(salesUrl.exchangeCreate(sale.id)),
        },
        {
            id: 'void',
            keys: 'v',
            label: t('sales.void'),
            icon: Ban,
            show: can.void,
            run: () => setVoidOpen(true),
        },
    ].filter((b) => b.show);

    useHotkeys('page', [
        ...buttons.map((b) => ({
            id: `sale.show.${b.id}`,
            keys: b.keys,
            labelKey: `sales.hotkey_${b.id}`,
            handler: b.run,
        })),
        {
            id: 'sale.show.back',
            keys: 'alt+arrowleft',
            labelKey: 'hotkeys.back',
            handler: () => navigateTo(salesUrl.index),
        },
    ]);

    // Palette entries for this page's actions while it is open; handlers read the latest render.
    const latest = useRef(buttons);
    useEffect(() => {
        latest.current = buttons;
    });
    const commandKey = buttons.map((b) => b.id).join('|');
    useEffect(
        () =>
            actions.register(
                ...latest.current.map((b) => ({
                    id: `page.sale.${b.id}`,
                    entity: null,
                    labelKey: `sales.hotkey_${b.id}`,
                    icon: b.icon,
                    keys: b.keys,
                    run: () => latest.current.find((x) => x.id === b.id)?.run(),
                })),
            ),
        [commandKey],
    );

    const status =
        sale.status === 'completed' ? sale.payment_status : sale.status;
    const totalsRows: [string, number, string?][] = [
        [t('sales.subtotal'), sale.subtotal],
        ...(sale.item_discount_total
            ? [
                  [
                      t('sales.item_discounts'),
                      -sale.item_discount_total,
                      'text-destructive',
                  ] as [string, number, string],
              ]
            : []),
        ...(sale.order_discount
            ? [
                  [
                      t('sales.order_discount'),
                      -sale.order_discount,
                      'text-destructive',
                  ] as [string, number, string],
              ]
            : []),
        ...(sale.tax_total
            ? [[t('sales.vat'), sale.tax_total] as [string, number]]
            : []),
        ...(sale.shipping
            ? [[t('sales.shipping'), sale.shipping] as [string, number]]
            : []),
        ...(sale.rounding
            ? [[t('sales.rounding'), sale.rounding] as [string, number]]
            : []),
    ];

    return (
        <>
            <Head title={`${t('sales.sale')} ${sale.number}`} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3 border-b pb-3">
                    <div className="flex items-start gap-3">
                        <Button
                            asChild
                            variant="outline"
                            size="icon"
                            className="size-11 md:size-9"
                        >
                            <Link
                                href={salesUrl.index}
                                aria-label={t('sales.back')}
                            >
                                <ArrowLeft aria-hidden="true" />
                            </Link>
                        </Button>
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="num text-xl font-bold">
                                    {sale.number}
                                </h1>
                                <StatusBadge status={status} />
                                {sale.returned_total > 0 ? (
                                    <StatusBadge
                                        status="returned"
                                        tone="info"
                                        label={t('sales.has_returns')}
                                    />
                                ) : null}
                                {sale.sync_flags.map((flag) => (
                                    <StatusBadge
                                        key={flag}
                                        status={flag}
                                        tone="warning"
                                        label={t(`sales.flag_${flag}`)}
                                    />
                                ))}
                            </div>
                            <p className="num text-sm text-muted-foreground">
                                {t('sales.created_by', {
                                    date: digits.n(sale.sold_at ?? ''),
                                    name: sale.cashier ?? '—',
                                })}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {buttons.map((b) => (
                            <Button
                                key={b.id}
                                type="button"
                                variant={
                                    b.id === 'void'
                                        ? 'destructive'
                                        : b.id === 'return'
                                          ? 'default'
                                          : 'outline'
                                }
                                className="min-h-11 md:min-h-9"
                                onClick={b.run}
                            >
                                <b.icon aria-hidden="true" />
                                {b.label}
                                <Kbd
                                    keys={b.keys}
                                    className="ml-1 hidden md:inline-flex"
                                />
                            </Button>
                        ))}
                    </div>
                </div>

                {sale.status === 'voided' ? (
                    <output className="block rounded-lg border border-destructive bg-danger-soft p-3 text-sm">
                        {t('sales.voided_banner', {
                            date: digits.n(sale.voided_at ?? ''),
                            name: sale.voided_by ?? '—',
                            reason: sale.void_reason ?? '',
                        })}
                    </output>
                ) : null}
                {exchangeOf ? (
                    <div
                        role="note"
                        className="rounded-lg border bg-info-soft p-3 text-sm"
                    >
                        {t('sales.replacement_of')}{' '}
                        <Link
                            className="num font-semibold underline"
                            href={salesUrl.returnShow(exchangeOf.return_id)}
                        >
                            {exchangeOf.return_number}
                        </Link>{' '}
                        ·{' '}
                        <Link
                            className="num font-semibold underline"
                            href={salesUrl.show(exchangeOf.sale_id)}
                        >
                            {exchangeOf.sale_number}
                        </Link>
                    </div>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-3">
                    <section
                        className="rounded-lg border bg-card p-4 lg:col-span-2"
                        aria-labelledby="items-heading"
                    >
                        <h2 id="items-heading" className="mb-3 font-semibold">
                            {t('sales.items')}
                        </h2>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="border-b text-muted-foreground">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="py-2 text-left"
                                        >
                                            {t('sales.item')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 text-right"
                                        >
                                            {t('sales.qty')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 text-right"
                                        >
                                            {t('sales.unit_price')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 text-right"
                                        >
                                            {t('sales.line_discount')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 text-right"
                                        >
                                            {t('sales.vat')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 text-right"
                                        >
                                            {t('sales.line_total')}
                                        </th>
                                        {sale.cogs_total !== null ? (
                                            <th
                                                scope="col"
                                                className="py-2 text-right"
                                            >
                                                {t('sales.cogs')}
                                            </th>
                                        ) : null}
                                    </tr>
                                </thead>
                                <tbody>
                                    {lines.map((line) => (
                                        <Fragment key={line.id}>
                                            <tr className="border-b last:border-0">
                                                <td className="py-2">
                                                    <div className="font-medium">
                                                        {line.name}
                                                    </div>
                                                    <div className="num text-xs text-muted-foreground">
                                                        {line.sku}
                                                    </div>
                                                    {line.qty_returned > 0 ? (
                                                        <div className="text-xs text-info">
                                                            {t(
                                                                'sales.returned_qty',
                                                                {
                                                                    qty: digits.qty(
                                                                        line.qty_returned,
                                                                    ),
                                                                },
                                                            )}
                                                        </div>
                                                    ) : null}
                                                </td>
                                                <td className="num py-2 text-right">
                                                    {digits.qty(line.qty)}
                                                </td>
                                                <td className="num py-2 text-right">
                                                    {digits.money(
                                                        line.unit_price,
                                                    )}
                                                </td>
                                                <td className="num py-2 text-right text-destructive">
                                                    {line.discount
                                                        ? `−${digits.money(line.discount)}`
                                                        : '—'}
                                                </td>
                                                <td className="num py-2 text-right">
                                                    {line.tax_amount
                                                        ? digits.money(
                                                              line.tax_amount,
                                                          )
                                                        : '—'}
                                                </td>
                                                <td className="num py-2 text-right font-semibold">
                                                    {digits.money(
                                                        line.line_total,
                                                    )}
                                                </td>
                                                {line.cogs !== null ? (
                                                    <td className="num py-2 text-right text-muted-foreground">
                                                        {digits.money(
                                                            line.cogs,
                                                        )}
                                                    </td>
                                                ) : null}
                                            </tr>
                                            {line.batches &&
                                            line.batches.length > 0 ? (
                                                <tr className="border-b bg-muted/40 text-xs">
                                                    <td
                                                        colSpan={7}
                                                        className="px-2 py-1.5"
                                                    >
                                                        <span className="font-medium">
                                                            {t('sales.batches')}
                                                            :
                                                        </span>{' '}
                                                        {line.batches.map(
                                                            (b) => (
                                                                <span
                                                                    key={b.id}
                                                                    className="num mr-3"
                                                                >
                                                                    {b.batch} ·{' '}
                                                                    {digits.qty(
                                                                        b.qty,
                                                                    )}{' '}
                                                                    ×{' '}
                                                                    {digits.money(
                                                                        b.unit_cost,
                                                                    )}
                                                                    {b.received_at
                                                                        ? ` (${digits.n(b.received_at)})`
                                                                        : ''}
                                                                </span>
                                                            ),
                                                        )}
                                                    </td>
                                                </tr>
                                            ) : null}
                                        </Fragment>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {sale.note ? (
                            <p className="mt-3 text-sm">
                                <span className="font-medium">
                                    {t('sales.note')}:
                                </span>{' '}
                                {sale.note}
                            </p>
                        ) : null}
                        {sale.courier_name ? (
                            <p className="mt-1 text-sm">
                                <span className="font-medium">
                                    {t('sales.courier')}:
                                </span>{' '}
                                {sale.courier_name}{' '}
                                <span className="num">
                                    {sale.courier_tracking ?? ''}
                                </span>
                            </p>
                        ) : null}
                    </section>

                    <div className="flex flex-col gap-4">
                        <section
                            className="rounded-lg border bg-card p-4 text-sm"
                            aria-labelledby="totals-heading"
                        >
                            <h2
                                id="totals-heading"
                                className="mb-2 font-semibold"
                            >
                                {t('sales.breakdown')}
                            </h2>
                            <dl className="grid grid-cols-[1fr_auto] gap-y-1">
                                {totalsRows.map(([label, value, tone]) => (
                                    <Fragment key={label}>
                                        <dt className="text-muted-foreground">
                                            {label}
                                        </dt>
                                        <dd
                                            className={`num text-right ${tone ?? ''}`}
                                        >
                                            {digits.money(value)}
                                        </dd>
                                    </Fragment>
                                ))}
                                <dt className="border-t pt-2 font-bold">
                                    {t('sales.total')}
                                </dt>
                                <dd className="num border-t pt-2 text-right font-bold text-primary">
                                    {digits.money(sale.total)}
                                </dd>
                                <dt className="text-success">
                                    {t('sales.paid')}
                                </dt>
                                <dd className="num text-right text-success">
                                    {digits.money(sale.paid)}
                                </dd>
                                {sale.change_given ? (
                                    <>
                                        <dt className="text-muted-foreground">
                                            {t('sales.change')}
                                        </dt>
                                        <dd className="num text-right">
                                            {digits.money(sale.change_given)}
                                        </dd>
                                    </>
                                ) : null}
                                {sale.returned_total ? (
                                    <>
                                        <dt className="text-muted-foreground">
                                            {t('sales.returned')}
                                        </dt>
                                        <dd className="num text-right">
                                            {digits.money(sale.returned_total)}
                                        </dd>
                                    </>
                                ) : null}
                                {sale.due > 0 ? (
                                    <>
                                        <dt className="border-t pt-1 font-bold text-destructive">
                                            {t('sales.due')}
                                        </dt>
                                        <dd className="num border-t pt-1 text-right font-bold text-destructive">
                                            {digits.money(sale.due)}
                                        </dd>
                                    </>
                                ) : null}
                                {sale.cogs_total !== null ? (
                                    <>
                                        <dt className="text-muted-foreground">
                                            {t('sales.cogs')}
                                        </dt>
                                        <dd className="num text-right text-muted-foreground">
                                            {digits.money(sale.cogs_total)}
                                        </dd>
                                    </>
                                ) : null}
                            </dl>
                        </section>

                        <section
                            className="rounded-lg border bg-card p-4 text-sm"
                            aria-labelledby="customer-heading"
                        >
                            <h2
                                id="customer-heading"
                                className="mb-2 font-semibold"
                            >
                                {t('sales.customer')}
                            </h2>
                            {customer && !customer.walk_in ? (
                                <div className="grid gap-1">
                                    <Link
                                        href={salesUrl.customer(customer.uuid)}
                                        className="font-medium underline-offset-2 hover:underline"
                                    >
                                        {customer.name}
                                    </Link>
                                    {customer.shop_name ? (
                                        <span>{customer.shop_name}</span>
                                    ) : null}
                                    {customer.customer_no ? (
                                        <span className="num text-primary">
                                            #{digits.n(customer.customer_no)}
                                        </span>
                                    ) : null}
                                    {customer.phone ? (
                                        <span className="num text-muted-foreground">
                                            {digits.n(customer.phone)}
                                        </span>
                                    ) : null}
                                    {sale.buyer_vat_bin ? (
                                        <span className="num">
                                            {t('sales.bin')}:{' '}
                                            {sale.buyer_vat_bin}
                                        </span>
                                    ) : null}
                                    <span>
                                        {t('sales.balance')}:{' '}
                                        <span
                                            className={`num font-semibold ${customer.balance > 0 ? 'text-destructive' : ''}`}
                                        >
                                            {digits.money(customer.balance)}
                                        </span>
                                    </span>
                                    <Link
                                        href={salesUrl.dueShow(customer.uuid)}
                                        className="text-primary underline-offset-2 hover:underline"
                                    >
                                        {t('sales.open_dues')}
                                    </Link>
                                </div>
                            ) : (
                                <p className="text-muted-foreground">
                                    {t('sales.walk_in')}
                                </p>
                            )}
                        </section>

                        <section
                            className="rounded-lg border bg-card p-4 text-sm"
                            aria-labelledby="payments-heading"
                        >
                            <h2
                                id="payments-heading"
                                className="mb-2 font-semibold"
                            >
                                {t('sales.payments')}
                            </h2>
                            {payments.length === 0 ? (
                                <p className="text-muted-foreground">
                                    {t('sales.no_payments')}
                                </p>
                            ) : (
                                <ul className="grid gap-2">
                                    {payments.map((p) => (
                                        <li
                                            key={`${p.id}-${p.amount}`}
                                            className={`flex items-center justify-between gap-2 ${p.voided ? 'line-through opacity-60' : ''}`}
                                        >
                                            <span>
                                                <span className="font-medium">
                                                    {p.method}
                                                </span>
                                                {p.reference ? (
                                                    <span className="num ml-1 text-xs text-muted-foreground">
                                                        {p.reference}
                                                    </span>
                                                ) : null}
                                                <span className="num block text-xs text-muted-foreground">
                                                    {p.number} ·{' '}
                                                    {digits.n(p.paid_at ?? '')}
                                                </span>
                                            </span>
                                            <span className="flex items-center gap-1">
                                                <span className="num font-semibold">
                                                    {digits.money(p.amount)}
                                                </span>
                                                {!p.credit && !p.voided ? (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-11 md:size-8"
                                                        aria-label={t(
                                                            'sales.print_money_receipt',
                                                            {
                                                                number: p.number,
                                                            },
                                                        )}
                                                        onClick={() =>
                                                            openPrint(
                                                                salesUrl.paymentReceipt(
                                                                    p.id,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <Printer aria-hidden="true" />
                                                    </Button>
                                                ) : null}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        {returns.length > 0 ? (
                            <section
                                className="rounded-lg border bg-card p-4 text-sm"
                                aria-labelledby="returns-heading"
                            >
                                <h2
                                    id="returns-heading"
                                    className="mb-2 font-semibold"
                                >
                                    {t('sales.linked_returns')}
                                </h2>
                                <ul className="grid gap-2">
                                    {returns.map((r) => (
                                        <li
                                            key={r.id}
                                            className="flex items-center justify-between gap-2"
                                        >
                                            <span>
                                                <Link
                                                    href={salesUrl.returnShow(
                                                        r.id,
                                                    )}
                                                    className="num font-medium text-primary hover:underline"
                                                >
                                                    {r.number}
                                                </Link>
                                                <span className="num block text-xs text-muted-foreground">
                                                    {digits.n(
                                                        r.returned_at ?? '',
                                                    )}{' '}
                                                    ·{' '}
                                                    {t(
                                                        `returns.mode_${r.refund_mode}`,
                                                    )}
                                                </span>
                                                {r.exchange_sale_id ? (
                                                    <Link
                                                        href={salesUrl.show(
                                                            r.exchange_sale_id,
                                                        )}
                                                        className="text-xs text-primary hover:underline"
                                                    >
                                                        {t(
                                                            'sales.view_replacement',
                                                        )}
                                                    </Link>
                                                ) : null}
                                            </span>
                                            <span className="num font-semibold">
                                                {digits.money(r.total)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ) : null}

                        {shareUrl ? (
                            <section
                                className="rounded-lg border bg-card p-4 text-sm"
                                aria-labelledby="share-heading"
                            >
                                <h2
                                    id="share-heading"
                                    className="mb-2 font-semibold"
                                >
                                    {t('sales.share_link')}
                                </h2>
                                <a
                                    href={shareUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="num break-all text-primary underline"
                                >
                                    {shareUrl}
                                </a>
                            </section>
                        ) : null}

                        <section
                            className="rounded-lg border bg-card p-4 text-sm"
                            aria-labelledby="audit-heading"
                        >
                            <h2
                                id="audit-heading"
                                className="mb-2 font-semibold"
                            >
                                {t('sales.audit')}
                            </h2>
                            {audit.length === 0 ? (
                                <p className="text-muted-foreground">
                                    {t('sales.no_audit')}
                                </p>
                            ) : (
                                <ol className="grid gap-1.5">
                                    {audit.map((a, i) => (
                                        <li
                                            key={i}
                                            className="flex justify-between gap-2"
                                        >
                                            <span>
                                                {t(
                                                    `sales.event.${a.event.replace(/\./g, '_')}`,
                                                    { defaultValue: a.event },
                                                )}
                                                {a.by ? (
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        · {a.by}
                                                    </span>
                                                ) : null}
                                            </span>
                                            <span className="num text-xs text-muted-foreground">
                                                {digits.n(a.at ?? '')}
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </section>
                    </div>
                </div>
            </div>
            <VoidSaleDialog
                sale={voidOpen ? { id: sale.id, number: sale.number } : null}
                onOpenChange={setVoidOpen}
            />
            {collectOpen ? (
                <CollectDialog
                    open
                    onOpenChange={setCollectOpen}
                    action={salesUrl.payments(sale.id)}
                    title={t('sales.collect_title', { number: sale.number })}
                    invoices={[
                        {
                            id: sale.id,
                            number: sale.number,
                            sold_at: sale.sold_at,
                            due: sale.due,
                        },
                    ]}
                    methods={methods}
                />
            ) : null}
        </>
    );
}
