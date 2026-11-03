import { ApiError, apiRequest } from "@/lib/api";
import type {
    DirectSendResult,
    MessageChannel,
    MessagingProps,
} from "@/types/messaging";

export const DIRECT_URL = "/app/messages/direct";

/** The dialog's variable chips (mockup): {name} {due} {last_invoice} {shop_phone}; the server fills them per customer. */
export const REMINDER_TAGS = [
    "name",
    "due",
    "last_invoice",
    "shop_phone",
] as const;

export type DirectRecipient = {
    uuid: string;
    name: string;
    phone: string | null;
    /** Open due in paisa (for the dialog's header and the client-side preview). */
    due: number;
};

/**
 * Pre-opens a blank tab synchronously (inside the click, so popup blockers allow it) when the send will
 * hand off to click-to-chat; the tab is pointed at the wa.me link when the server answers, or closed on
 * failure. Returns null when the browser refused (the caller then shows the link to click).
 */
export function preOpenTab(): Window | null {
    if (typeof window === "undefined") {
        return null;
    }

    return window.open("", "_blank", "noopener");
}

/** Sends to an explicit list of customers; the result carries the wa.me link(s) for click-to-chat. */
export async function sendDirect(input: {
    channel: MessageChannel;
    event: string;
    customers: string[];
    body: string | null;
    values?: Record<string, string>;
}): Promise<DirectSendResult> {
    try {
        return await apiRequest<DirectSendResult>(DIRECT_URL, {
            method: "POST",
            body: input,
        });
    } catch (error) {
        if (error instanceof ApiError) {
            throw error;
        }
        throw new ApiError(0, error instanceof Error ? error.message : "");
    }
}

/** Whether the WhatsApp send for `count` recipients will be a click-to-chat handoff (one tab per request). */
export function handsOff(messaging: MessagingProps, count: number): boolean {
    return (
        messaging.whatsapp.configured && !messaging.whatsapp.bulk && count === 1
    );
}
