import { Head, Link, router } from "@inertiajs/react";
import {
    ArrowLeft,
    BadgeDollarSign,
    FileText,
    MapPin,
    MessageSquare,
    Pencil,
    Send,
    ShieldCheck,
    ShoppingCart,
} from "lucide-react";
import { type ReactNode, useRef, useState } from "react";
import type { CardBrand } from "@/components/cards/card-face";
import { ConfirmDialog } from "@/components/confirm-dialog";
import {
    ResetCustomerPinDialog,
    SetCustomerPinDialog,
    UnlockCustomerPinDialog,
} from "@/components/customers/customer-pin-dialogs";
import { CardPanel } from "@/components/customers/card-panel";
import { WhatsAppReminderDialog } from "@/components/messaging/whatsapp-reminder-dialog";
import { CustomerDialog } from "@/components/customers/customer-dialog";
import { EmptyState } from "@/components/empty-state";
import { Page } from "@/components/page-header";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { useDigits, type Digits } from "@/hooks/use-digits";
import { useHotkeys } from "@/hotkeys/use-hotkeys";
import type { MessagingProps } from "@/types/messaging";
import { useT } from "@/i18n";
import { geoLabel, geoName } from "@/lib/geo";
import { displayPhone } from "@/lib/phone";
import { cn } from "@/lib/utils";
import type {
    CustomerCard,
    CustomerDetail,
    CustomerKpis,
    GroupOption,
} from "@/types/customers";
import { addressStatus } from "@/lib/customer-address";

type SaleRow = {
    id: number;
    number: string | null;
    sold_at: string;
    status: string;
    payment_status: string;
    total: number;
    paid: number;
    due: number;
};
type ReturnRow = {
    id: number;
    number: string;
    returned_at: string;
    refund_mode: string;
    total: number;
};
type PaymentRow = {
    id: number;
    number: string;
    paid_at: string;
    direction: string;
    method: string | null;
    amount: number;
    allocated: number;
    voided: boolean;
};
type MessageRow = {
    id: number;
    event: string;
    body: string;
    status: string;
    created_at: string | null;
};

type Props = {
    customer: CustomerDetail;
    card: CustomerCard | null;
    brand: CardBrand;
    kpis: CustomerKpis;
    sales: SaleRow[];
    returns: ReturnRow[];
    payments: PaymentRow[];
    messages: MessageRow[];
    groups: GroupOption[];
    can: Record<string, boolean>;
    messaging: MessagingProps;
    /** Opened as /app/customers/{uuid}/edit (deep link): the edit dialog starts open. */
    editing: boolean;
};

const TABS = ["overview", "sales", "returns", "payments", "messages"] as const;
type Tab = (typeof TABS)[number];

const RECENT = 5;

const initial = (name: string): string =>
    name.trim().charAt(0).toUpperCase() || "?";

/**
 * Customer 360 profile (baseline mockup "SCREEN 8"): banner card with the actions, 4 KPI tiles, the
 * tab strip (overview = address, identity & credit, recent purchases) and the membership card panel
 * on the right. Edit opens the customer dialog in place.
 */
export default function CustomerShow({
    customer,
    card,
    brand,
    kpis,
    sales,
    returns,
    payments,
    messages,
    groups,
    can,
    messaging,
    editing,
}: Props) {
    const { t, locale } = useT();
    const digits = useDigits();
    const [tab, setTab] = useState<Tab>("overview");
    const [editOpen, setEditOpen] = useState(editing);
    const [confirmSms, setConfirmSms] = useState(false);
    const [whatsApp, setWhatsApp] = useState(false);
    const [pinDialog, setPinDialog] = useState<
        "set" | "reset" | "unlock" | null
    >(null);
    const canSetPin = canEditPin(can, customer);
    const canResetPin = can["reset_pin"] === true && !customer.is_walk_in;
    const tabRefs = useRef<Record<string, HTMLButtonElement | null>>({});
    const owes = customer.balance > 0;
    const canEdit = can["update"] === true && !customer.is_walk_in;
    const canSms = can["sms"] === true && !!customer.phone;
    const canWhatsApp =
        messaging.can_send && !!customer.phone && !customer.is_walk_in;
    const collectHref = `/app/dues?customer=${customer.uuid}`;

    const openEdit = () => {
        if (canEdit) {
            setEditOpen(true);
        }
    };
    const closeEdit = () => {
        setEditOpen(false);
        if (window.location.pathname.endsWith("/edit")) {
            router.get(
                `/app/customers/${customer.uuid}`,
                {},
                { replace: true, preserveState: true, preserveScroll: true },
            );
        }
    };

    useHotkeys("page", [
        {
            id: "customer.new_sale",
            keys: "n",
            labelKey: "customers.actions.new_sale",
            handler: () => {
                if (can["sell"] && customer.is_active) {
                    router.visit(`/app/pos?customer=${customer.uuid}`);
                }
            },
        },
        {
            id: "customer.edit",
            keys: "e",
            labelKey: "hotkeys.edit",
            handler: openEdit,
        },
        {
            id: "customer.pin",
            keys: "p",
            labelKey: "hotkeys.customer_pin",
            handler: () => {
                if (customer.pin_status === "pending" && canSetPin) {
                    setPinDialog("set");
                } else if (customer.pin_status !== "pending" && canResetPin) {
                    setPinDialog("reset");
                }
            },
        },
        {
            id: "customer.collect",
            keys: "c",
            labelKey: "customers.actions.collect_due",
            handler: () => {
                if (can["collect"] && owes) {
                    router.visit(collectHref);
                }
            },
        },
        {
            id: "customer.sms",
            keys: "m",
            labelKey: "customers.actions.sms",
            handler: () => {
                if (canSms) {
                    setConfirmSms(true);
                }
            },
        },
        {
            id: "customer.whatsapp",
            keys: "w",
            labelKey: "customers.actions.whatsapp",
            handler: () => {
                if (canWhatsApp) {
                    setWhatsApp(true);
                }
            },
        },
        {
            id: "customer.statement",
            keys: "t",
            labelKey: "customers.actions.statement",
            handler: () =>
                router.visit(`/app/customers/${customer.uuid}/statement`),
        },
    ]);

    const onTabKey = (event: React.KeyboardEvent, index: number) => {
        const step =
            event.key === "ArrowRight" ? 1 : event.key === "ArrowLeft" ? -1 : 0;
        if (step === 0) {
            return;
        }
        event.preventDefault();
        const next = TABS[(index + step + TABS.length) % TABS.length] as Tab;
        setTab(next);
        tabRefs.current[next]?.focus();
    };

    const address = [
        customer.address_line,
        customer.area,
        geoLabel(customer.location, locale),
        customer.post_code,
    ]
        .filter(Boolean)
        .join(", ");
    const pct =
        kpis.credit_limit && kpis.credit_limit > 0
            ? Math.min(
                  100,
                  Math.round((kpis.credit_used / kpis.credit_limit) * 100),
              )
            : 0;

    return (
        <>
            <Head title={customer.name} />
            <Page className="gap-4">
                <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div className="min-w-0 space-y-4">
                        {/* Banner */}
                        <section
                            aria-label={t("customers.profile.banner")}
                            className="flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-surface p-4"
                        >
                            <div className="flex min-w-0 items-center gap-3">
                                <Link
                                    href="/app/customers"
                                    aria-label={t("customers.profile.back")}
                                    title={t("customers.profile.back")}
                                    className="flex size-8 shrink-0 items-center justify-center rounded-lg border bg-surface-sunken text-foreground hover:bg-surface [@media(pointer:coarse)]:size-11"
                                >
                                    <ArrowLeft
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                                <div
                                    aria-hidden="true"
                                    className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary text-lg font-bold text-primary-foreground"
                                >
                                    {initial(customer.name)}
                                </div>
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h1 className="text-lg font-bold text-foreground">
                                            {customer.name}
                                        </h1>
                                        {customer.name_bn ? (
                                            <span
                                                lang="bn"
                                                className="text-xs text-muted-foreground"
                                            >
                                                ({customer.name_bn})
                                            </span>
                                        ) : null}
                                        {customer.group ? (
                                            <StatusBadge
                                                status="group"
                                                tone="primary"
                                                label={customer.group}
                                            />
                                        ) : null}
                                        {!customer.is_active ? (
                                            <StatusBadge
                                                status="inactive"
                                                label={t("customers.inactive")}
                                            />
                                        ) : null}
                                    </div>
                                    <div className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                        {customer.customer_no ? (
                                            <span>
                                                {t("customers.profile.card")}{" "}
                                                <strong className="num font-mono">
                                                    {digits.n(
                                                        customer.customer_no,
                                                    )}
                                                </strong>
                                            </span>
                                        ) : null}
                                        {customer.phone ? (
                                            <>
                                                <span aria-hidden="true">
                                                    ·
                                                </span>
                                                <span>
                                                    {t(
                                                        "customers.profile.phone",
                                                    )}{" "}
                                                    <strong className="num font-mono">
                                                        {digits.n(
                                                            displayPhone(
                                                                customer.phone,
                                                            ),
                                                        )}
                                                    </strong>
                                                </span>
                                            </>
                                        ) : null}
                                        {customer.shop_name ? (
                                            <>
                                                <span aria-hidden="true">
                                                    ·
                                                </span>
                                                <span>
                                                    {t(
                                                        "customers.profile.shop",
                                                    )}{" "}
                                                    <strong className="text-foreground">
                                                        {customer.shop_name}
                                                    </strong>
                                                </span>
                                            </>
                                        ) : null}
                                        {address ? (
                                            <>
                                                <span aria-hidden="true">
                                                    ·
                                                </span>
                                                <span>
                                                    {t(
                                                        "customers.profile.address",
                                                    )}{" "}
                                                    <strong className="text-foreground">
                                                        {digits.n(address)}
                                                    </strong>
                                                </span>
                                            </>
                                        ) : null}
                                    </div>
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center justify-end gap-2">
                                {can["collect"] && owes ? (
                                    <Button asChild>
                                        <Link href={collectHref}>
                                            <BadgeDollarSign aria-hidden="true" />
                                            {t("customers.actions.collect_due")}
                                        </Link>
                                    </Button>
                                ) : null}
                                {canWhatsApp ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="border-success/30 bg-success-soft text-success hover:bg-success-soft hover:text-success"
                                        onClick={() => setWhatsApp(true)}
                                    >
                                        <Send aria-hidden="true" />
                                        {t("customers.actions.whatsapp")}
                                    </Button>
                                ) : null}
                                {canSms ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="bg-surface-sunken font-semibold hover:border-primary"
                                        onClick={() => setConfirmSms(true)}
                                    >
                                        <MessageSquare aria-hidden="true" />
                                        {t("customers.actions.sms")}
                                    </Button>
                                ) : null}
                                {can["sell"] && customer.is_active ? (
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="bg-surface-sunken font-semibold hover:border-primary"
                                    >
                                        <Link
                                            href={`/app/pos?customer=${customer.uuid}`}
                                        >
                                            <ShoppingCart aria-hidden="true" />
                                            {t("customers.actions.new_sale")}
                                        </Link>
                                    </Button>
                                ) : null}
                                <Button
                                    asChild
                                    variant="outline"
                                    className="bg-surface-sunken font-semibold hover:border-primary"
                                >
                                    <Link
                                        href={`/app/customers/${customer.uuid}/statement`}
                                    >
                                        <FileText aria-hidden="true" />
                                        {t("customers.actions.statement")}
                                    </Link>
                                </Button>
                                {canEdit ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="bg-surface-sunken font-semibold hover:border-primary"
                                        onClick={openEdit}
                                    >
                                        <Pencil aria-hidden="true" />
                                        {t("actions.edit")}
                                    </Button>
                                ) : null}
                            </div>
                        </section>

                        {/* KPI tiles (mockup: plain tiles, 11 px label, 16 px value) */}
                        <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                            <Tile label={t("customers.kpi.lifetime")}>
                                <span className="num">
                                    {kpis.lifetime_spend === null
                                        ? "-"
                                        : digits.money(kpis.lifetime_spend)}
                                </span>
                            </Tile>
                            <Tile label={t("customers.kpi.visits")}>
                                <span className="num font-mono">
                                    {kpis.visits === null
                                        ? "-"
                                        : t("customers.kpi.visits_value", {
                                              n: digits.n(kpis.visits),
                                          })}
                                </span>
                            </Tile>
                            <Tile label={t("customers.kpi.due")}>
                                <span
                                    className={cn(
                                        "num font-mono",
                                        kpis.due > 0
                                            ? "text-destructive"
                                            : "text-success",
                                    )}
                                >
                                    {digits.money(kpis.due)}
                                </span>
                            </Tile>
                            <Tile label={t("customers.kpi.security")}>
                                <span className="text-xs font-semibold">
                                    <span
                                        className={
                                            card?.status === "active"
                                                ? "text-success"
                                                : "text-muted-foreground"
                                        }
                                    >
                                        {card
                                            ? t(`cards.status.${card.status}`)
                                            : t("customers.kpi.no_card")}
                                    </span>
                                    {" · "}
                                    <PinStatusText customer={customer} />
                                </span>
                                <span className="mt-1.5 flex flex-wrap gap-1">
                                    {customer.pin_status === "pending" &&
                                        canSetPin && (
                                            <PinButton
                                                onClick={() =>
                                                    setPinDialog("set")
                                                }
                                            >
                                                {t("customers.actions.set_pin")}
                                            </PinButton>
                                        )}
                                    {customer.pin_status === "locked" &&
                                        canResetPin && (
                                            <PinButton
                                                onClick={() =>
                                                    setPinDialog("unlock")
                                                }
                                            >
                                                {t(
                                                    "customers.actions.unlock_pin",
                                                )}
                                            </PinButton>
                                        )}
                                    {customer.pin_status !== "pending" &&
                                        canResetPin && (
                                            <PinButton
                                                onClick={() =>
                                                    setPinDialog("reset")
                                                }
                                            >
                                                {t(
                                                    "customers.actions.reset_pin",
                                                )}
                                            </PinButton>
                                        )}
                                </span>
                            </Tile>
                        </div>

                        <section
                            aria-label={t("customers.tabs.label")}
                            className="min-w-0 space-y-4"
                        >
                            <div
                                role="tablist"
                                aria-label={t("customers.tabs.label")}
                                className="flex gap-4 border-b text-xs font-medium text-muted-foreground"
                            >
                                {TABS.map((id, index) => (
                                    <button
                                        key={id}
                                        ref={(el) => {
                                            tabRefs.current[id] = el;
                                        }}
                                        role="tab"
                                        type="button"
                                        id={`tab-${id}`}
                                        aria-selected={tab === id}
                                        aria-controls={`panel-${id}`}
                                        tabIndex={tab === id ? 0 : -1}
                                        className={cn(
                                            "-mb-px border-b-2 pb-2 transition-colors [@media(pointer:coarse)]:min-h-11",
                                            tab === id
                                                ? "border-primary font-bold text-primary"
                                                : "border-transparent hover:text-foreground",
                                        )}
                                        onClick={() => setTab(id)}
                                        onKeyDown={(e) => onTabKey(e, index)}
                                    >
                                        {t(`customers.tabs.${id}`)}
                                    </button>
                                ))}
                            </div>
                            <div
                                role="tabpanel"
                                id={`panel-${tab}`}
                                aria-labelledby={`tab-${tab}`}
                            >
                                {tab === "overview" ? (
                                    <Overview
                                        customer={customer}
                                        kpis={kpis}
                                        pct={pct}
                                        sales={sales}
                                        collectHref={
                                            can["collect"] ? collectHref : null
                                        }
                                        digits={digits}
                                    />
                                ) : null}
                                {tab === "sales" ? (
                                    <SalesTab rows={sales} digits={digits} />
                                ) : null}
                                {tab === "returns" ? (
                                    <ReturnsTab
                                        rows={returns}
                                        digits={digits}
                                    />
                                ) : null}
                                {tab === "payments" ? (
                                    <PaymentsTab
                                        rows={payments}
                                        digits={digits}
                                    />
                                ) : null}
                                {tab === "messages" ? (
                                    <MessagesTab
                                        rows={messages}
                                        digits={digits}
                                    />
                                ) : null}
                            </div>
                        </section>
                    </div>

                    <aside className="lg:sticky lg:top-0">
                        <CardPanel
                            customerUuid={customer.uuid}
                            card={card}
                            customer={customer}
                            brand={brand}
                            canIssue={can["issue_card"] === true}
                            canViewCards={can["cards"] === true}
                        />
                    </aside>
                </div>
            </Page>

            <CustomerDialog
                open={editOpen}
                customer={customer}
                groups={groups}
                onClose={closeEdit}
            />
            {whatsApp ? (
                <WhatsAppReminderDialog
                    open
                    onOpenChange={(open) => !open && setWhatsApp(false)}
                    messaging={messaging}
                    recipient={{
                        uuid: customer.uuid,
                        name: customer.name,
                        phone: customer.phone,
                        due: customer.balance,
                    }}
                />
            ) : null}
            {pinDialog === "set" && (
                <SetCustomerPinDialog
                    customer={customer}
                    onClose={() => setPinDialog(null)}
                />
            )}
            {pinDialog === "reset" && (
                <ResetCustomerPinDialog
                    customer={customer}
                    onClose={() => setPinDialog(null)}
                />
            )}
            {pinDialog === "unlock" && (
                <UnlockCustomerPinDialog
                    customer={customer}
                    onClose={() => setPinDialog(null)}
                />
            )}
            <ConfirmDialog
                open={confirmSms}
                onOpenChange={setConfirmSms}
                title={t("customers.sms.title")}
                description={t("customers.sms.body", {
                    phone: digits.n(displayPhone(customer.phone)),
                })}
                confirmLabel={t("customers.sms.send")}
                onConfirm={() =>
                    router.post(
                        `/app/customers/${customer.uuid}/statement/sms`,
                        {},
                        { preserveScroll: true },
                    )
                }
            />
        </>
    );
}

const canEditPin = (
    can: Record<string, boolean>,
    customer: CustomerDetail,
): boolean => can["update"] === true && !customer.is_walk_in;

/** "Active (4-digit)" is the mockup wording; the digit count is not known (hash only), so we say "Active". */
function PinStatusText({ customer }: { customer: CustomerDetail }) {
    const { t } = useT();
    const digits = useDigits();
    if (customer.pin_status === "locked") {
        return (
            <span className="text-danger">
                {t("customers.pin.status_locked", {
                    time: customer.pin_locked_until
                        ? digits.n(customer.pin_locked_until.slice(11, 16))
                        : "",
                })}
            </span>
        );
    }
    if (customer.pin_status === "set") {
        return <span className="text-success">{t("status.active")}</span>;
    }

    return (
        <span className="text-warning">
            {t("customers.pin.status_pending")}
        </span>
    );
}

function PinButton({
    onClick,
    children,
}: {
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="touch-target rounded border bg-surface-sunken px-2 py-0.5 text-[10px] font-semibold text-foreground hover:bg-border"
        >
            {children}
        </button>
    );
}

function Tile({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="min-w-0 rounded-xl border bg-surface p-3">
            <div className="truncate text-[11px] text-muted-foreground">
                {label}
            </div>
            <div className="mt-1 text-base font-bold [overflow-wrap:anywhere] text-foreground">
                {children}
            </div>
        </div>
    );
}

/** Decorative bar: the used / limit figures next to it carry the meaning. */
function CreditMeter({ pct }: { pct: number }) {
    return (
        <div
            aria-hidden="true"
            className="mt-1.5 h-2 w-full overflow-hidden rounded-full border bg-surface-sunken"
        >
            <div
                style={{ width: `${pct}%` }}
                className={cn(
                    "h-full transition-all",
                    pct > 80
                        ? "bg-destructive"
                        : pct > 50
                          ? "bg-warning"
                          : "bg-success",
                )}
            />
        </div>
    );
}

/** Mockup panel shell: bordered card with a titled header row. */
function Panel({
    title,
    icon,
    aside,
    children,
}: {
    title: string;
    icon?: ReactNode;
    aside?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="space-y-3 rounded-xl border bg-surface p-4 shadow-xs">
            <div className="flex items-center justify-between gap-2 border-b pb-2">
                <h2 className="flex items-center gap-1.5 text-xs font-bold text-foreground">
                    {icon}
                    {title}
                </h2>
                {aside}
            </div>
            {children}
        </section>
    );
}

function Fact({
    label,
    children,
    className,
}: {
    label: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className="min-w-0">
            <div className="text-[10px] font-bold text-muted-foreground uppercase">
                {label}
            </div>
            <div
                className={cn(
                    "font-semibold break-words text-foreground",
                    className,
                )}
            >
                {children || "-"}
            </div>
        </div>
    );
}

function Overview({
    customer,
    kpis,
    pct,
    sales,
    collectHref,
    digits,
}: {
    customer: CustomerDetail;
    kpis: CustomerKpis;
    pct: number;
    sales: SaleRow[];
    collectHref: string | null;
    digits: Digits;
}) {
    const { t, locale } = useT();
    const recent = sales.slice(0, RECENT);
    const open = sales.filter((s) => s.due > 0 && s.status !== "voided").length;
    const discount = customer.group_discount_bp;
    const address = addressStatus(customer);

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <Panel
                    title={t("customers.profile.address_title")}
                    icon={
                        <MapPin
                            className="size-3.75 text-primary"
                            aria-hidden="true"
                        />
                    }
                    aside={
                        <StatusBadge
                            status={
                                address === "verified" ? "verified" : "pending"
                            }
                            tone={
                                address === "verified" ? "success" : "neutral"
                            }
                            label={t(`customers.profile.address_${address}`)}
                        />
                    }
                >
                    <div className="space-y-2 text-xs">
                        <div className="grid grid-cols-3 gap-2">
                            <Fact label={t("location.division")}>
                                {geoName(customer.location.division, locale)}
                            </Fact>
                            <Fact label={t("location.district")}>
                                {geoName(customer.location.district, locale)}
                            </Fact>
                            <Fact label={t("location.upazila")}>
                                {geoName(customer.location.upazila, locale)}
                            </Fact>
                        </div>
                        <div className="grid grid-cols-3 gap-2">
                            <Fact
                                label={t("customers.dialog.area")}
                                className="col-span-2"
                            >
                                {customer.area}
                            </Fact>
                            <Fact
                                label={t("customers.dialog.post_code")}
                                className="num font-mono"
                            >
                                {customer.post_code
                                    ? digits.n(customer.post_code)
                                    : null}
                            </Fact>
                        </div>
                        <div className="border-t pt-2">
                            <div className="text-[10px] font-bold text-muted-foreground uppercase">
                                {t("customers.dialog.street")}
                            </div>
                            <div className="mt-0.5 rounded-lg border bg-surface-sunken p-2 font-semibold text-foreground">
                                {customer.address_line || "-"}
                            </div>
                        </div>
                        {customer.notes ? (
                            <div>
                                <div className="text-[10px] font-bold text-muted-foreground uppercase">
                                    {t("customers.form.notes")}
                                </div>
                                <div className="mt-0.5 text-[11px] text-foreground italic">
                                    {customer.notes}
                                </div>
                            </div>
                        ) : null}
                    </div>
                </Panel>

                <Panel
                    title={t("customers.profile.identity_title")}
                    icon={
                        <ShieldCheck
                            className="size-3.75 text-success"
                            aria-hidden="true"
                        />
                    }
                    aside={
                        customer.customer_no ? (
                            <span className="num rounded-sm border bg-surface-sunken px-2 py-0.5 font-mono text-[11px] font-bold">
                                #{digits.n(customer.customer_no)}
                            </span>
                        ) : null
                    }
                >
                    <div className="space-y-2.5 text-xs">
                        <div className="grid grid-cols-2 gap-2">
                            <Fact label={t("customers.form.shop_name")}>
                                {customer.shop_name}
                            </Fact>
                            <Fact
                                label={t("customers.profile.group")}
                                className="text-primary"
                            >
                                {customer.group}
                            </Fact>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <Fact
                                label={t("customers.profile.primary_whatsapp")}
                                className="num font-mono font-bold"
                            >
                                {customer.phone
                                    ? digits.n(displayPhone(customer.phone))
                                    : null}
                            </Fact>
                            <Fact
                                label={t("customers.profile.discount")}
                                className="font-bold text-success"
                            >
                                {discount > 0
                                    ? t("customers.profile.discount_auto", {
                                          pct: digits.n(String(discount / 100)),
                                      })
                                    : t("customers.profile.discount_none")}
                            </Fact>
                        </div>
                        {customer.alt_phone || customer.email ? (
                            <div className="grid grid-cols-2 gap-2">
                                <Fact
                                    label={t("customers.dialog.alt_phone")}
                                    className="num font-mono"
                                >
                                    {customer.alt_phone
                                        ? digits.n(
                                              displayPhone(customer.alt_phone),
                                          )
                                        : null}
                                </Fact>
                                <Fact label={t("customers.dialog.email")}>
                                    {customer.email}
                                </Fact>
                            </div>
                        ) : null}
                        {customer.dob ||
                        customer.anniversary_date ||
                        customer.member_since ? (
                            <div className="grid grid-cols-3 gap-2">
                                <Fact
                                    label={t("customers.profile.member_since")}
                                    className="num"
                                >
                                    {customer.member_since
                                        ? digits.n(customer.member_since)
                                        : null}
                                </Fact>
                                <Fact
                                    label={t("customers.form.dob")}
                                    className="num"
                                >
                                    {customer.dob
                                        ? digits.n(customer.dob)
                                        : null}
                                </Fact>
                                <Fact
                                    label={t("customers.form.anniversary")}
                                    className="num"
                                >
                                    {customer.anniversary_date
                                        ? digits.n(customer.anniversary_date)
                                        : null}
                                </Fact>
                            </div>
                        ) : null}

                        <div className="space-y-1.5 border-t pt-2">
                            <div className="flex items-center justify-between text-[11px]">
                                <span className="font-medium text-muted-foreground">
                                    {t("customers.profile.utilisation")}
                                </span>
                                <span className="num font-mono font-bold text-foreground">
                                    {digits.money(kpis.credit_used)} /{" "}
                                    {kpis.credit_limit === null
                                        ? t("customers.kpi.no_limit")
                                        : digits.money(kpis.credit_limit)}
                                </span>
                            </div>
                            <CreditMeter pct={pct} />
                            <div className="flex justify-between text-[10px] text-muted-foreground">
                                <span>
                                    {t("customers.profile.available")}{" "}
                                    <strong className="num">
                                        {kpis.credit_available === null
                                            ? "-"
                                            : digits.money(
                                                  kpis.credit_available,
                                              )}
                                    </strong>
                                </span>
                                <span>
                                    {t("customers.profile.pin")}{" "}
                                    <strong>
                                        <PinStatusText customer={customer} />
                                    </strong>
                                </span>
                            </div>
                        </div>
                    </div>
                </Panel>
            </div>

            <section className="rounded-xl border bg-surface p-4 shadow-xs">
                <div className="mb-3 flex items-center justify-between text-xs font-bold text-foreground">
                    <h2>{t("customers.profile.recent_title")}</h2>
                    <span className="text-[11px] font-normal text-muted-foreground">
                        {t("customers.profile.open_invoices", {
                            n: digits.n(open),
                        })}
                    </span>
                </div>
                {recent.length === 0 ? (
                    <p className="text-xs text-muted-foreground">
                        {t("customers.tabs.empty")}
                    </p>
                ) : (
                    <ul className="space-y-2 text-xs">
                        {recent.map((s) => {
                            const pending = s.due > 0 && s.status !== "voided";

                            return (
                                <li
                                    key={s.id}
                                    className={cn(
                                        "flex items-center justify-between gap-3 rounded-lg border p-3",
                                        pending
                                            ? "border-warning/30 bg-warning-soft"
                                            : "bg-surface-sunken",
                                    )}
                                >
                                    <div className="min-w-0">
                                        <div
                                            className={cn(
                                                "flex flex-wrap items-center gap-2 font-mono font-bold",
                                                pending
                                                    ? "text-warning"
                                                    : "text-primary",
                                            )}
                                        >
                                            <span className="num">
                                                {s.number
                                                    ? digits.n(s.number)
                                                    : "-"}
                                            </span>
                                            {pending ? (
                                                <StatusBadge
                                                    status="due"
                                                    tone="warning"
                                                    shape="tag"
                                                    label={t(
                                                        "customers.profile.due_pending",
                                                        {
                                                            amount: digits.money(
                                                                s.due,
                                                            ),
                                                        },
                                                    )}
                                                />
                                            ) : (
                                                <StatusBadge
                                                    status={
                                                        s.status === "voided"
                                                            ? "void"
                                                            : "paid"
                                                    }
                                                    shape="tag"
                                                    label={
                                                        s.status === "voided"
                                                            ? t("status.void")
                                                            : t(
                                                                  "customers.profile.settled",
                                                              )
                                                    }
                                                />
                                            )}
                                        </div>
                                        <div className="num mt-0.5 text-[11px] text-muted-foreground">
                                            {t("customers.profile.paid_of", {
                                                date: digits.n(
                                                    s.sold_at.slice(0, 10),
                                                ),
                                                paid: digits.money(s.paid),
                                                total: digits.money(s.total),
                                            })}
                                        </div>
                                    </div>
                                    {pending && collectHref ? (
                                        <Button asChild size="sm">
                                            <Link href={collectHref}>
                                                {t("customers.profile.pay_due")}
                                            </Link>
                                        </Button>
                                    ) : (
                                        <span className="num text-sm font-bold text-foreground">
                                            {digits.money(s.total)}
                                        </span>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </section>
        </div>
    );
}

const Th = ({ children, num }: { children: ReactNode; num?: boolean }) => (
    <th
        scope="col"
        className={cn("px-3 py-2 font-semibold", num && "text-right")}
    >
        {children}
    </th>
);
const Td = ({ children, num }: { children: ReactNode; num?: boolean }) => (
    <td className={cn("px-3 py-2", num && "num text-right")}>{children}</td>
);

function Grid({ head, children }: { head: ReactNode; children: ReactNode }) {
    return (
        <div className="overflow-x-auto rounded-xl border bg-surface shadow-xs">
            <table className="w-full border-collapse text-left text-xs">
                <thead className="border-b bg-surface-sunken text-[11px] text-muted-foreground">
                    <tr>{head}</tr>
                </thead>
                <tbody className="divide-y">{children}</tbody>
            </table>
        </div>
    );
}

function Empty() {
    const { t } = useT();

    return <EmptyState title={t("customers.tabs.empty")} />;
}

function SalesTab({ rows, digits }: { rows: SaleRow[]; digits: Digits }) {
    const { t } = useT();
    if (rows.length === 0) {
        return <Empty />;
    }

    return (
        <Grid
            head={
                <>
                    <Th>{t("customers.statement.col.date")}</Th>
                    <Th>{t("customers.statement.col.invoice")}</Th>
                    <Th num>{t("customers.statement.col.total")}</Th>
                    <Th num>{t("customers.statement.col.paid")}</Th>
                    <Th num>{t("customers.statement.col.due")}</Th>
                    <Th>{t("customers.statement.col.status")}</Th>
                </>
            }
        >
            {rows.map((r) => (
                <tr key={r.id}>
                    <Td>{digits.n(r.sold_at.slice(0, 10))}</Td>
                    <Td>
                        <span className="font-mono font-semibold text-primary">
                            {r.number ? digits.n(r.number) : "-"}
                        </span>
                    </Td>
                    <Td num>{digits.money(r.total)}</Td>
                    <Td num>{digits.money(r.paid)}</Td>
                    <Td num>{digits.money(r.due)}</Td>
                    <Td>
                        <StatusBadge
                            status={
                                r.status === "voided"
                                    ? "void"
                                    : r.payment_status
                            }
                        />
                    </Td>
                </tr>
            ))}
        </Grid>
    );
}

function ReturnsTab({ rows, digits }: { rows: ReturnRow[]; digits: Digits }) {
    const { t } = useT();
    if (rows.length === 0) {
        return <Empty />;
    }

    return (
        <Grid
            head={
                <>
                    <Th>{t("customers.statement.col.date")}</Th>
                    <Th>{t("customers.tabs.number")}</Th>
                    <Th>{t("customers.tabs.refund_mode")}</Th>
                    <Th num>{t("customers.statement.col.total")}</Th>
                </>
            }
        >
            {rows.map((r) => (
                <tr key={r.id}>
                    <Td>{digits.n(r.returned_at.slice(0, 10))}</Td>
                    <Td>{digits.n(r.number)}</Td>
                    <Td>{t(`customers.refund.${r.refund_mode}`)}</Td>
                    <Td num>{digits.money(r.total)}</Td>
                </tr>
            ))}
        </Grid>
    );
}

function PaymentsTab({ rows, digits }: { rows: PaymentRow[]; digits: Digits }) {
    const { t } = useT();
    if (rows.length === 0) {
        return <Empty />;
    }

    return (
        <Grid
            head={
                <>
                    <Th>{t("customers.statement.col.date")}</Th>
                    <Th>{t("customers.tabs.number")}</Th>
                    <Th>{t("customers.tabs.method")}</Th>
                    <Th num>{t("customers.tabs.amount")}</Th>
                    <Th num>{t("customers.tabs.allocated")}</Th>
                </>
            }
        >
            {rows.map((r) => (
                <tr key={r.id} className={cn(r.voided && "line-through")}>
                    <Td>{digits.n(r.paid_at.slice(0, 10))}</Td>
                    <Td>{digits.n(r.number)}</Td>
                    <Td>{r.method ?? "-"}</Td>
                    <Td num>
                        {r.direction === "out" ? "-" : ""}
                        {digits.money(r.amount)}
                    </Td>
                    <Td num>{digits.money(r.allocated)}</Td>
                </tr>
            ))}
        </Grid>
    );
}

function MessagesTab({ rows, digits }: { rows: MessageRow[]; digits: Digits }) {
    const { t } = useT();
    if (rows.length === 0) {
        return <Empty />;
    }

    return (
        <Grid
            head={
                <>
                    <Th>{t("customers.statement.col.date")}</Th>
                    <Th>{t("customers.tabs.event")}</Th>
                    <Th>{t("customers.tabs.message")}</Th>
                    <Th>{t("customers.statement.col.status")}</Th>
                </>
            }
        >
            {rows.map((r) => (
                <tr key={r.id}>
                    <Td>
                        {r.created_at
                            ? digits.n(r.created_at.slice(0, 10))
                            : "-"}
                    </Td>
                    <Td>{r.event}</Td>
                    <Td>{r.body}</Td>
                    <Td>{r.status}</Td>
                </tr>
            ))}
        </Grid>
    );
}
