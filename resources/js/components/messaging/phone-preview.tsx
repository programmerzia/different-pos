import { CheckCheck, Send } from "lucide-react";
import { useT } from "@/i18n";

/**
 * Mockup "Live Recipient Phone Simulator": a phone with the shop as the sender and the rendered message
 * as the incoming bubble. Built from theme tokens (no fixed colours), so it follows the colour scheme.
 */
export function PhonePreview({ shop, text }: { shop: string; text: string }) {
    const { t } = useT();
    const initials =
        shop
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((w) => w[0]?.toUpperCase() ?? "")
            .join("") || "·";

    return (
        <div className="flex w-full shrink-0 flex-col items-center rounded-xl border bg-surface p-4 shadow-xs lg:w-80">
            <div className="mb-3 text-xs font-semibold text-muted-foreground">
                {t("sms.hub.preview_title")}
            </div>
            <div className="flex h-105 w-64 flex-col overflow-hidden rounded-3xl border-4 border-border bg-surface-sunken shadow-2xl">
                <div className="flex items-center gap-2 border-b bg-primary p-2.5 text-primary-foreground">
                    <div className="flex size-7 items-center justify-center rounded-full bg-primary-foreground text-xs font-bold text-primary">
                        {initials}
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="truncate text-[11px] font-bold">
                            {shop}
                        </div>
                        <div className="text-[9px] opacity-80">
                            {t("sms.hub.preview_sender")}
                        </div>
                    </div>
                </div>
                <div className="flex flex-1 flex-col justify-end space-y-1 overflow-y-auto px-2 py-3">
                    <div className="max-w-[95%] self-start rounded-lg rounded-tl-none border bg-surface p-2.5 text-[11px] leading-snug whitespace-pre-wrap text-foreground shadow-xs">
                        {text || (
                            <span className="text-muted-foreground italic">
                                {t("sms.hub.preview_empty")}
                            </span>
                        )}
                        <div className="mt-1 flex items-center justify-end gap-1 text-[9px] text-muted-foreground">
                            <span>{t("sms.hub.preview_now")}</span>
                            <CheckCheck
                                className="size-3 text-info"
                                aria-hidden="true"
                            />
                        </div>
                    </div>
                </div>
                <div className="flex items-center gap-2 border-t bg-surface p-2">
                    <div className="flex h-7 flex-1 items-center rounded-full bg-surface-sunken px-3 text-[10px] text-muted-foreground">
                        {t("sms.hub.preview_reply")}
                    </div>
                    <div className="flex size-6 items-center justify-center rounded-full bg-primary text-primary-foreground">
                        <Send className="size-2.75" aria-hidden="true" />
                    </div>
                </div>
            </div>
        </div>
    );
}
