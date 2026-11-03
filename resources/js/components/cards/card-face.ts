/** Pure helpers behind the membership card artwork (shared with the print view's rules in CardRenderer). */

/** The baseline mockup's two club tiers. */
export type CardTier = 'gold' | 'vip';

export type MemberCardInfo = {
    id: number;
    code: string;
    status: 'active' | 'lost' | 'replaced' | 'revoked';
    issue_no: number;
    print_count: number;
    printed_at: string | null;
};

/** One studio row: every customer is a member; `card` is the active card, else the latest one. */
export type CardMember = {
    uuid: string;
    name: string;
    name_bn: string | null;
    shop_name: string | null;
    phone: string | null;
    /** Formatted, e.g. `1000 0017` (8 digits, the last is the Luhn check digit). */
    customer_no: string;
    member_since: string | null;
    group: string | null;
    card: MemberCardInfo | null;
};

export type CardBrand = {
    name: string;
    logo_url: string | null;
    phone: string | null;
    branch: string | null;
    bin: string | null;
};

/** The mockup's rule: a group whose name contains "VIP" is platinum VIP, everyone else gold privilege. */
export function tierOf(group: string | null | undefined): CardTier {
    return (group ?? '').toUpperCase().includes('VIP') ? 'vip' : 'gold';
}

export const rawNumber = (customerNo: string): string =>
    customerNo.replace(/\D/g, '');

/** `1000 0017` (4 + 4) for an 8-digit number; anything else is shown as given. */
export function formatNumber(customerNo: string): string {
    const raw = rawNumber(customerNo);

    return raw.length === 8 ? `${raw.slice(0, 4)} ${raw.slice(4)}` : customerNo;
}

/** The "CID" box on the back: the last three digits of the number. */
export const cid = (customerNo: string): string =>
    rawNumber(customerNo).slice(-3);

/**
 * The barcode payload the card carries (`MC` + 8-digit number + 2-digit issue). For a member without
 * an active card it is the code the next print will issue.
 */
export function cardCode(member: Pick<CardMember, 'customer_no' | 'card'>): string {
    if (member.card?.status === 'active') {
        return member.card.code;
    }
    const next = (member.card?.issue_no ?? 0) + 1;

    return `MC${rawNumber(member.customer_no).padStart(8, '0')}${String(next).padStart(2, '0')}`;
}

/** `2026-01-05` → `01/26` (the mockup's "valid" slot shows the member-since month: cards do not expire). */
export function sinceShort(date: string | null | undefined): string {
    const m = /^(\d{4})-(\d{2})/.exec(date ?? '');

    return m ? `${m[2]}/${(m[1] as string).slice(2)}` : '--/--';
}

/**
 * The on-screen masked number: every digit but the last four becomes a bullet, grouping kept
 * (`1000 0017` → `•••• 0017`). Print views always show the full number.
 */
export function maskNumber(customerNo: string): string {
    const shown = formatNumber(customerNo);
    const total = rawNumber(shown).length;
    let seen = 0;

    return shown.replace(/\d/g, (d) => (++seen > total - 4 ? d : '•'));
}

/** A QR field: no separator or line break inside, trimmed. */
const qrField = (value: string | null | undefined): string =>
    (value ?? '').replace(/[|\r\n]+/g, ' ').trim();

/**
 * The QR payload on the back: `name|shop|number` (member name, the member's shop name or empty, the
 * 8-digit card number). Same rule as CardRenderer::qrPayload(); the barcode keeps the card code only.
 */
export function qrPayload(member: Pick<CardMember, 'name' | 'shop_name' | 'customer_no'>): string {
    return [qrField(member.name), qrField(member.shop_name), rawNumber(member.customer_no)].join('|');
}
