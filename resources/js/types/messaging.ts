export type SmsTemplate = {
    key: string;
    enabled: boolean;
    body: string;
    default: string;
    placeholders: string[];
};

export type SmsSettings = {
    driver: string;
    sender_id: string;
    sid: string;
    base_url: string;
    has_token: boolean;
    /** The gateway can deliver (driver plus credentials); never the key itself. */
    configured: boolean;
    /** Paisa per SMS part, for the composer's cost estimate. */
    cost_per_segment: number;
    templates: SmsTemplate[];
};

export type MessageRow = {
    id: number;
    created_at: string | null;
    customer: string | null;
    channel: MessageChannel;
    phone: string;
    event: string;
    body: string;
    status: "queued" | "sent" | "failed" | "handoff";
    error: string | null;
    driver: string;
};

export type MessageChannel = "sms" | "whatsapp";

/** The truthful WhatsApp state: `connected` only when the Cloud API has both credentials. */
export type WhatsAppStatus =
    "connected" | "click_to_chat" | "log" | "incomplete" | "off";

export type WhatsAppConfig = {
    driver: string;
    status: WhatsAppStatus;
    configured: boolean;
    /** Can send unattended (bulk / automatic). Click-to-chat cannot. */
    bulk: boolean;
};

/** The `messaging` page prop (dues list, due drill-down, customer profile). */
export type MessagingProps = {
    can_send: boolean;
    whatsapp: WhatsAppConfig;
    sms: { configured: boolean };
    shop: string;
    shop_phone: string;
    /** Default WhatsApp texts with placeholders, by event (due_reminder, payment_received). */
    defaults: Record<string, string>;
};

export type WhatsAppSettings = {
    driver: string;
    status: WhatsAppStatus;
    phone_number_id: string;
    has_token: boolean;
    template_name: string;
    template_language: string;
};

/** POST /app/messages/direct response. */
export type DirectSendResult = {
    queued: number;
    skipped: number;
    handoff: { customer: string; name: string; phone: string; url: string }[];
};
