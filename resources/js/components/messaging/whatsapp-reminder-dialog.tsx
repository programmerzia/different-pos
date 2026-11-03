import { Link } from "@inertiajs/react";
import {
    AlertTriangle,
    ExternalLink,
    MessageSquare,
    Send,
    X,
} from "lucide-react";
import { useRef, useState } from "react";
import { toast } from "sonner";
import { renderPreview } from "@/components/messaging/sms-text";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from "@/components/ui/dialog";
import { Kbd } from "@/components/kbd";
import { useDigits } from "@/hooks/use-digits";
import { useHotkeyScope, useHotkeys } from "@/hotkeys/use-hotkeys";
import { useT } from "@/i18n";
import { ApiError } from "@/lib/api";
import { cn } from "@/lib/utils";
import {
    type DirectRecipient,
    handsOff,
    preOpenTab,
    REMINDER_TAGS,
    sendDirect,
} from "@/lib/whatsapp";
import type { DirectSendResult, MessagingProps } from "@/types/messaging";

export type ReminderEvent = "due_reminder" | "payment_received";

type Mode = "single" | "bulk";

/**
 * Mockup "WHATSAPP REMINDER MODAL" (13): WhatsApp-green bar with the customer, phone and due; Single /
 * Bulk dispatch target; insert-tag chips; the editable text in a chat bubble (the template with
 * placeholders, so a bulk send fills each customer's own name and due; the line under it previews the
 * single recipient); Cancel / Send. Sends through POST /app/messages/direct on the WhatsApp channel.
 * With click-to-chat the server returns a wa.me link that opens in a tab pre-opened on the click; when
 * the browser blocked it, the link is shown to click. Bulk is offered only when the provider can send
 * unattended (log / Cloud API), truthfully.
 */
export function WhatsAppReminderDialog({
    open,
    onOpenChange,
    messaging,
    recipient,
    bulk = [],
    event = "due_reminder",
    values = {},
    onSent,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    messaging: MessagingProps;
    /** The customer the dialog was opened for (Single). */
    recipient: DirectRecipient;
    /** The customers a Bulk send reaches (the selected or listed debtors); empty = no bulk option. */
    bulk?: DirectRecipient[];
    event?: ReminderEvent;
    /** Extra placeholder values the server fills (for example {amount} on a thank-you). */
    values?: Record<string, string>;
    onSent?: (result: DirectSendResult) => void;
}) {
    const { t } = useT();
    const digits = useDigits();
    const [mode, setMode] = useState<Mode>("single");
    const [text, setText] = useState(messaging.defaults[event] ?? "");
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [fallback, setFallback] = useState<string | null>(null);
    const area = useRef<HTMLTextAreaElement>(null);
    useHotkeyScope("dialog", open);

    const bulkable = bulk.length > 1 && messaging.whatsapp.bulk;
    const targets = mode === "bulk" && bulkable ? bulk : [recipient];
    const totalDue = targets.reduce((sum, r) => sum + Math.max(0, r.due), 0);
    const configured = messaging.whatsapp.configured;
    const canSend =
        configured && !busy && text.trim() !== "" && targets.length > 0;

    const preview = renderPreview(text, {
        name: recipient.name,
        customer: recipient.name,
        due: digits.money(Math.max(0, recipient.due)),
        balance: digits.money(recipient.due),
        shop: messaging.shop,
        shop_phone: digits.n(messaging.shop_phone),
        last_invoice: t("sms.whatsapp.preview_last_invoice"),
        ...values,
    });

    const insert = (tag: string) => {
        const el = area.current;
        const start = el?.selectionStart ?? text.length;
        const end = el?.selectionEnd ?? text.length;
        const token = `{${tag}}`;
        const next = text.slice(0, start) + token + text.slice(end);
        setText(next);
        requestAnimationFrame(() => {
            el?.focus();
            el?.setSelectionRange(start + token.length, start + token.length);
        });
    };

    const send = async () => {
        if (!canSend) {
            return;
        }
        setBusy(true);
        setError(null);
        const handoff = handsOff(messaging, targets.length);
        const tab = handoff ? preOpenTab() : null;
        try {
            const result = await sendDirect({
                channel: "whatsapp",
                event,
                customers: targets.map((r) => r.uuid),
                body: text,
                values,
            });
            const link = result.handoff[0]?.url ?? null;
            if (link) {
                if (tab) {
                    tab.location.href = link;
                } else {
                    setFallback(link);
                }
                toast.success(
                    t("sms.whatsapp.opened", { name: recipient.name }),
                );
            } else {
                tab?.close();
                toast.success(
                    t("sms.whatsapp.queued", {
                        n: digits.n(result.queued),
                        skipped: digits.n(result.skipped),
                    }),
                );
            }
            onSent?.(result);
            if (!link || tab) {
                onOpenChange(false);
            }
        } catch (e) {
            tab?.close();
            setError(
                e instanceof ApiError && e.detail
                    ? e.detail
                    : t("sms.whatsapp.send_failed"),
            );
        } finally {
            setBusy(false);
        }
    };

    useHotkeys("dialog", [
        {
            id: "whatsapp.send",
            keys: "mod+enter",
            labelKey: "sms.whatsapp.send",
            allowInInput: true,
            handler: (e) => {
                e.preventDefault();
                void send();
            },
        },
    ]);

    const title =
        mode === "bulk" && bulkable
            ? t("sms.whatsapp.bulk_title", { n: digits.n(bulk.length) })
            : recipient.name;
    const subtitle =
        mode === "bulk" && bulkable
            ? t("sms.whatsapp.bulk_due", { amount: digits.money(totalDue) })
            : `${digits.n(recipient.phone ?? "")} · ${t("dues.due_is", { due: digits.money(Math.max(0, recipient.due)) })}`;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => !busy && onOpenChange(next)}
        >
            <DialogContent
                layout="panel"
                size="md"
                className="text-xs"
                onOpenAutoFocus={(e) => {
                    e.preventDefault();
                    area.current?.focus();
                }}
            >
                <div className="flex min-h-0 flex-col">
                    <div className="flex items-center justify-between gap-3 bg-whatsapp p-3.5 text-primary-foreground shadow-xs">
                        <div className="flex items-center gap-2.5">
                            <span
                                aria-hidden="true"
                                className="flex size-8 items-center justify-center rounded-full bg-primary-foreground/20"
                            >
                                <MessageSquare className="size-4" />
                            </span>
                            <div>
                                <DialogTitle className="text-xs font-bold">
                                    {title}
                                </DialogTitle>
                                <DialogDescription className="flex items-center gap-1 text-[10px] text-primary-foreground/80">
                                    <span
                                        aria-hidden="true"
                                        className={cn(
                                            "inline-block size-1.5 rounded-full",
                                            configured
                                                ? "bg-whatsapp-bright"
                                                : "bg-warning",
                                        )}
                                    />
                                    <span className="num">{subtitle}</span>
                                </DialogDescription>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-8 text-primary-foreground/80 hover:bg-primary-foreground/10 hover:text-primary-foreground [@media(pointer:coarse)]:min-h-11"
                            aria-label={t("common.close")}
                            onClick={() => onOpenChange(false)}
                        >
                            <X aria-hidden="true" />
                        </Button>
                    </div>

                    {bulk.length > 1 ? (
                        <div className="flex items-center justify-between gap-2 border-b bg-surface-sunken p-2.5">
                            <span className="text-[11px] font-semibold text-muted-foreground">
                                {t("sms.whatsapp.target")}
                            </span>
                            <fieldset className="m-0 flex min-w-0 rounded-lg border bg-surface p-0.5 text-[11px]">
                                <legend className="sr-only">
                                    {t("sms.whatsapp.target")}
                                </legend>
                                <ModeButton
                                    on={mode === "single"}
                                    onClick={() => setMode("single")}
                                >
                                    {t("sms.whatsapp.single", {
                                        name: recipient.name,
                                    })}
                                </ModeButton>
                                <ModeButton
                                    on={mode === "bulk"}
                                    disabled={!bulkable}
                                    title={
                                        bulkable
                                            ? undefined
                                            : t("sms.whatsapp.bulk_needs_api")
                                    }
                                    onClick={() => setMode("bulk")}
                                >
                                    {t("sms.whatsapp.bulk_all", {
                                        n: digits.n(bulk.length),
                                    })}
                                </ModeButton>
                            </fieldset>
                        </div>
                    ) : null}

                    {!configured ? (
                        <p
                            role="alert"
                            className="m-3 flex items-center gap-2 rounded-lg border border-warning bg-warning-soft p-2.5 text-xs text-foreground"
                        >
                            <AlertTriangle
                                className="size-4 shrink-0 text-warning"
                                aria-hidden="true"
                            />
                            <span className="flex-1">
                                {t("sms.whatsapp.not_configured")}
                            </span>
                            <Link
                                href="/app/settings/sms"
                                className="font-bold text-primary underline"
                            >
                                {t("sms.hub.open_settings")}
                            </Link>
                        </p>
                    ) : null}

                    <div className="flex flex-wrap items-center gap-1.5 px-3 pt-2.5 pb-1">
                        <span className="text-[10px] font-semibold text-muted-foreground">
                            {t("sms.whatsapp.insert_tags")}
                        </span>
                        {REMINDER_TAGS.map((tag) => (
                            <button
                                key={tag}
                                type="button"
                                onClick={() => insert(tag)}
                                aria-label={t("sms.hub.insert_variable", {
                                    name: `{${tag}}`,
                                })}
                                className="rounded border bg-surface-sunken px-2 py-0.5 font-mono text-[10px] text-foreground hover:border-whatsapp [@media(pointer:coarse)]:min-h-11"
                            >
                                + {`{${tag}}`}
                            </button>
                        ))}
                    </div>

                    <div className="flex min-h-40 flex-col justify-end bg-whatsapp-chat p-4">
                        <div className="w-full self-end rounded-2xl rounded-tr-none border border-success/30 bg-whatsapp-bubble p-3 shadow-md">
                            <label htmlFor="whatsapp-text" className="sr-only">
                                {t("sms.whatsapp.text")}
                            </label>
                            <textarea
                                id="whatsapp-text"
                                ref={area}
                                rows={4}
                                maxLength={1000}
                                value={text}
                                placeholder={t("sms.whatsapp.text_ph")}
                                aria-invalid={error ? true : undefined}
                                aria-describedby={
                                    error
                                        ? "whatsapp-error"
                                        : "whatsapp-preview"
                                }
                                onChange={(e) => setText(e.target.value)}
                                className="w-full resize-none border-0 bg-transparent font-sans text-xs leading-relaxed text-foreground outline-none focus-visible:outline-none"
                            />
                            <div className="mt-1 flex items-center justify-end gap-1 text-[10px] text-success">
                                <span className="num">
                                    {digits.n(text.length)}
                                </span>
                                <span aria-hidden="true" className="font-bold">
                                    ✓✓
                                </span>
                            </div>
                        </div>
                        <p
                            id="whatsapp-preview"
                            className="mt-2 line-clamp-3 text-[11px] text-muted-foreground"
                        >
                            <span className="font-semibold">
                                {t("sms.whatsapp.preview_for", {
                                    name: recipient.name,
                                })}
                            </span>{" "}
                            <span className="text-foreground">{preview}</span>
                        </p>
                    </div>

                    {error ? (
                        <p
                            id="whatsapp-error"
                            role="alert"
                            className="px-3 pt-2 text-[11px] text-destructive"
                        >
                            {error}
                        </p>
                    ) : null}
                    {fallback ? (
                        <p className="px-3 pt-2 text-[11px]">
                            <a
                                href={fallback}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 font-bold text-primary underline"
                            >
                                <ExternalLink
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                                {t("sms.whatsapp.open_link")}
                            </a>
                        </p>
                    ) : null}

                    <div className="flex items-center justify-between gap-2 border-t bg-surface-sunken p-3">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-9 font-semibold"
                            disabled={busy}
                            onClick={() => onOpenChange(false)}
                        >
                            {t("confirm.cancel")}
                        </Button>
                        <Button
                            type="button"
                            className="h-9 flex-1 bg-whatsapp-bright font-bold text-foreground hover:bg-whatsapp-bright/90"
                            disabled={!canSend}
                            onClick={() => void send()}
                        >
                            <Send aria-hidden="true" />
                            {mode === "bulk" && bulkable
                                ? t("sms.whatsapp.send_all", {
                                      n: digits.n(bulk.length),
                                  })
                                : event === "payment_received"
                                  ? t("sms.whatsapp.send_thanks")
                                  : t("sms.whatsapp.send")}
                            <Kbd keys="mod+enter" onButton />
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function ModeButton({
    on,
    disabled = false,
    title,
    onClick,
    children,
}: {
    on: boolean;
    disabled?: boolean;
    title?: string | undefined;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            aria-pressed={on}
            aria-disabled={disabled || undefined}
            title={title}
            onClick={() => !disabled && onClick()}
            className={cn(
                "rounded-md px-2.5 py-1 font-semibold transition-all [@media(pointer:coarse)]:min-h-11",
                on
                    ? "bg-whatsapp text-primary-foreground shadow-xs"
                    : "text-muted-foreground hover:text-foreground",
                disabled && "cursor-not-allowed opacity-50",
            )}
        >
            {children}
        </button>
    );
}
