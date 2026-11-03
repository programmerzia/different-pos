import { Head, Link } from "@inertiajs/react";
import { ArrowLeft, HandCoins, Printer, Send } from "lucide-react";
import { useState } from "react";
import { Kbd } from "@/components/kbd";
import {
    type ReminderEvent,
    WhatsAppReminderDialog,
} from "@/components/messaging/whatsapp-reminder-dialog";
import { CollectDialog } from "@/components/sales/collect-dialog";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { navigateTo } from "@/command/navigate";
import { useDigits } from "@/hooks/use-digits";
import { useHotkeys } from "@/hotkeys/use-hotkeys";
import { useT } from "@/i18n";
import { BUCKETS, bucketTotals, type Bucket } from "@/lib/dues";
import type { MessagingProps } from "@/types/messaging";
import {
    openPrint,
    salesUrl,
    type CustomerSummary,
    type MoneyMethod,
} from "@/lib/sales";

type Invoice = {
    id: number;
    number: string;
    sold_at: string | null;
    total: number;
    paid: number;
    due: number;
    age: number;
    bucket: Bucket;
};

type Props = {
    customer: CustomerSummary & {
        balance: number;
        credit_limit: number | null;
        advance: number;
    };
    invoices: Invoice[];
    payments: {
        id: number;
        number: string;
        paid_at: string | null;
        method: string;
        amount: number;
        reference: string | null;
    }[];
    preview: {
        amount: number;
        allocations: Record<number, number>;
        advance: number;
    } | null;
    receipt: number | null;
    methods: MoneyMethod[];
    can: { collect: boolean };
    messaging: MessagingProps;
    /** The payment just collected (with ?receipt=), for the WhatsApp thank-you's {amount}. */
    last_payment: { id: number; amount: number } | null;
};

const BUCKET_TONE = {
    d0_30: "neutral",
    d31_60: "warning",
    d61_90: "warning",
    d90_plus: "danger",
} as const;

export default function DueShow({
    customer,
    invoices,
    payments,
    receipt,
    methods,
    can,
    messaging,
    last_payment,
}: Props) {
    const { t } = useT();
    const digits = useDigits();
    const [collecting, setCollecting] = useState(false);
    const [whatsApp, setWhatsApp] = useState<ReminderEvent | null>(null);
    const totals = bucketTotals(invoices);
    const totalDue = invoices.reduce((sum, i) => sum + i.due, 0);
    const canWhatsApp = messaging.can_send && !!customer.phone;
    const canRemind = canWhatsApp && totalDue > 0;
    const canThank = canWhatsApp && last_payment !== null;

    useHotkeys("page", [
        ...(can.collect && invoices.length > 0
            ? [
                  {
                      id: "due.collect",
                      keys: "c",
                      labelKey: "dues.collect",
                      handler: () => setCollecting(true),
                  },
              ]
            : []),
        ...(canRemind
            ? [
                  {
                      id: "due.whatsapp",
                      keys: "w",
                      labelKey: "dues.whatsapp_reminder",
                      handler: () => setWhatsApp("due_reminder"),
                  },
              ]
            : []),
        ...(canThank
            ? [
                  {
                      id: "due.whatsapp_thanks",
                      keys: "shift+w",
                      labelKey: "dues.whatsapp_thanks",
                      handler: () => setWhatsApp("payment_received"),
                  },
              ]
            : []),
        ...(receipt
            ? [
                  {
                      id: "due.receipt",
                      keys: "p",
                      labelKey: "dues.print_receipt",
                      handler: () =>
                          openPrint(salesUrl.paymentReceipt(receipt)),
                  },
              ]
            : []),
        {
            id: "due.back",
            keys: "alt+arrowleft",
            labelKey: "hotkeys.back",
            handler: () => navigateTo(salesUrl.dues),
        },
    ]);

    return (
        <>
            <Head title={`${t("dues.title")} · ${customer.name}`} />
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
                                href={salesUrl.dues}
                                aria-label={t("dues.back")}
                            >
                                <ArrowLeft aria-hidden="true" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-xl font-bold">
                                <Link
                                    href={salesUrl.customer(customer.uuid)}
                                    className="hover:underline"
                                >
                                    {customer.name}
                                </Link>
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {customer.shop_name
                                    ? `${customer.shop_name} · `
                                    : ""}
                                {customer.customer_no ? (
                                    <span className="num text-primary">
                                        #{digits.n(customer.customer_no)}{" "}
                                    </span>
                                ) : null}
                                <span className="num">
                                    {digits.n(customer.phone ?? "")}
                                </span>
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {canRemind ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-11 border-success/30 bg-success-soft font-semibold text-success hover:bg-success-soft hover:text-success"
                                onClick={() => setWhatsApp("due_reminder")}
                            >
                                <Send aria-hidden="true" />{" "}
                                {t("dues.whatsapp_reminder")}{" "}
                                <Kbd
                                    keys="w"
                                    className="ml-1 hidden md:inline-flex"
                                />
                            </Button>
                        ) : null}
                        {can.collect && invoices.length > 0 ? (
                            <Button
                                type="button"
                                className="min-h-11"
                                onClick={() => setCollecting(true)}
                            >
                                <HandCoins aria-hidden="true" />{" "}
                                {t("dues.collect")}{" "}
                                <Kbd
                                    keys="c"
                                    className="ml-1 hidden md:inline-flex"
                                />
                            </Button>
                        ) : null}
                    </div>
                </div>

                {receipt ? (
                    <output className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-success bg-success-soft p-3 text-sm">
                        <span>{t("dues.collected_banner")}</span>
                        <span className="flex flex-wrap items-center gap-2">
                            {canThank ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-h-11 border-success/30 bg-surface font-semibold text-success hover:text-success md:min-h-9"
                                    onClick={() =>
                                        setWhatsApp("payment_received")
                                    }
                                >
                                    <Send aria-hidden="true" />{" "}
                                    {t("dues.whatsapp_thanks")}{" "}
                                    <Kbd
                                        keys="shift+w"
                                        className="ml-1 hidden md:inline-flex"
                                    />
                                </Button>
                            ) : null}
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-11 md:min-h-9"
                                onClick={() =>
                                    openPrint(salesUrl.paymentReceipt(receipt))
                                }
                            >
                                <Printer aria-hidden="true" />{" "}
                                {t("dues.print_receipt")}{" "}
                                <Kbd
                                    keys="p"
                                    className="ml-1 hidden md:inline-flex"
                                />
                            </Button>
                        </span>
                    </output>
                ) : null}

                <dl className="grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
                    {[
                        [
                            t("dues.col_total"),
                            digits.money(totalDue),
                            "text-destructive",
                        ],
                        ...BUCKETS.map((b) => [
                            t(`dues.bucket_${b}`),
                            digits.money(totals[b]),
                            "",
                        ]),
                        [
                            t("dues.advance"),
                            digits.money(customer.advance),
                            "text-success",
                        ],
                    ].map(([label, value, tone]) => (
                        <div
                            key={label}
                            className="rounded-lg border bg-card p-3"
                        >
                            <dt className="text-xs text-muted-foreground">
                                {label}
                            </dt>
                            <dd className={`num text-lg font-bold ${tone}`}>
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>
                {customer.credit_limit !== null ? (
                    <p className="text-sm text-muted-foreground">
                        {t("dues.credit_limit", {
                            limit: digits.money(customer.credit_limit),
                            balance: digits.money(customer.balance),
                        })}
                    </p>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-3">
                    <section
                        className="rounded-lg border bg-card p-4 lg:col-span-2"
                        aria-labelledby="open-invoices"
                    >
                        <h2 id="open-invoices" className="mb-3 font-semibold">
                            {t("dues.open_invoices")}
                        </h2>
                        {invoices.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t("dues.no_open")}
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b text-muted-foreground">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="py-2 text-left"
                                            >
                                                {t("dues.invoice")}
                                            </th>
                                            <th
                                                scope="col"
                                                className="py-2 text-left"
                                            >
                                                {t("sales.col_date")}
                                            </th>
                                            <th
                                                scope="col"
                                                className="py-2 text-right"
                                            >
                                                {t("sales.col_total")}
                                            </th>
                                            <th
                                                scope="col"
                                                className="py-2 text-right"
                                            >
                                                {t("sales.col_paid")}
                                            </th>
                                            <th
                                                scope="col"
                                                className="py-2 text-right"
                                            >
                                                {t("dues.due")}
                                            </th>
                                            <th
                                                scope="col"
                                                className="py-2 text-right"
                                            >
                                                {t("dues.age")}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {invoices.map((i) => (
                                            <tr
                                                key={i.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="py-2">
                                                    <Link
                                                        href={salesUrl.show(
                                                            i.id,
                                                        )}
                                                        className="num font-medium text-primary hover:underline"
                                                    >
                                                        {i.number}
                                                    </Link>
                                                </td>
                                                <td className="num py-2 text-muted-foreground">
                                                    {digits.n(i.sold_at ?? "")}
                                                </td>
                                                <td className="num py-2 text-right">
                                                    {digits.money(i.total)}
                                                </td>
                                                <td className="num py-2 text-right">
                                                    {digits.money(i.paid)}
                                                </td>
                                                <td className="num py-2 text-right font-semibold text-destructive">
                                                    {digits.money(i.due)}
                                                </td>
                                                <td className="py-2 text-right">
                                                    <StatusBadge
                                                        status={i.bucket}
                                                        tone={
                                                            BUCKET_TONE[
                                                                i.bucket
                                                            ]
                                                        }
                                                        label={t("dues.days", {
                                                            n: digits.n(i.age),
                                                        })}
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                    <section
                        className="rounded-lg border bg-card p-4 text-sm"
                        aria-labelledby="recent-payments"
                    >
                        <h2 id="recent-payments" className="mb-2 font-semibold">
                            {t("dues.recent_payments")}
                        </h2>
                        {payments.length === 0 ? (
                            <p className="text-muted-foreground">
                                {t("sales.no_payments")}
                            </p>
                        ) : (
                            <ul className="grid gap-2">
                                {payments.map((p) => (
                                    <li
                                        key={p.id}
                                        className="flex items-center justify-between gap-2"
                                    >
                                        <span>
                                            <span className="font-medium">
                                                {p.method}
                                            </span>
                                            <span className="num block text-xs text-muted-foreground">
                                                {p.number} ·{" "}
                                                {digits.n(p.paid_at ?? "")}
                                            </span>
                                        </span>
                                        <span className="flex items-center gap-1">
                                            <span className="num font-semibold">
                                                {digits.money(p.amount)}
                                            </span>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-11 md:size-8"
                                                aria-label={t(
                                                    "sales.print_money_receipt",
                                                    { number: p.number },
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
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </div>
            {collecting ? (
                <CollectDialog
                    open
                    onOpenChange={setCollecting}
                    action={salesUrl.duePayments(customer.uuid)}
                    title={t("dues.collect_from", { name: customer.name })}
                    invoices={invoices.map((i) => ({
                        id: i.id,
                        number: i.number,
                        sold_at: i.sold_at,
                        due: i.due,
                    }))}
                    methods={methods}
                />
            ) : null}
            {whatsApp ? (
                <WhatsAppReminderDialog
                    key={whatsApp}
                    open
                    onOpenChange={(open) => !open && setWhatsApp(null)}
                    messaging={messaging}
                    event={whatsApp}
                    recipient={{
                        uuid: customer.uuid,
                        name: customer.name,
                        phone: customer.phone ?? null,
                        due: totalDue,
                    }}
                    values={
                        whatsApp === "payment_received" && last_payment
                            ? { amount: digits.money(last_payment.amount) }
                            : {}
                    }
                />
            ) : null}
        </>
    );
}
