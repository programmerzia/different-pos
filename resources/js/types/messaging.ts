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
    /** Paisa per SMS part, for the composer's cost estimate. */
    cost_per_segment: number;
    templates: SmsTemplate[];
};

export type MessageRow = {
    id: number;
    created_at: string | null;
    customer: string | null;
    phone: string;
    event: string;
    body: string;
    status: 'queued' | 'sent' | 'failed';
    error: string | null;
    driver: string;
};
