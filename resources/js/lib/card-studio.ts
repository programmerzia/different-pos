/**
 * Membership card studio helpers: the print queue (the member selection), the preview mode, the member picker's keyboard cursor, A4 sheet
 * paging and the press URLs (CardIdsRequest: ids, layout, crop).
 */
import { apiRequest } from '@/lib/api';
import type { CardMember } from '@/components/cards/card-face';

/** Members per server page (MembershipCardController::PER_PAGE) and cards per A4 sheet. */
export const PER_PAGE = 40;
export const PER_SHEET = 10;

export type StudioLayout = 'cr80' | 'a4';
export type PrintLayout = 'cr80' | 'bleed' | 'a4';

/** Adds the member to the queue, or takes them out when already queued. */
export function toggleQueued(queue: readonly CardMember[], member: CardMember): CardMember[] {
    return queue.some((m) => m.uuid === member.uuid)
        ? queue.filter((m) => m.uuid !== member.uuid)
        : [...queue, member];
}

/** Queues every member of the page not queued yet (order kept). */
export function queueAll(queue: readonly CardMember[], page: readonly CardMember[]): CardMember[] {
    const queued = new Set(queue.map((m) => m.uuid));

    return [...queue, ...page.filter((m) => !queued.has(m.uuid))];
}

/** Refreshes queued members from a newer server page (card status, print count) without reordering. */
export function refreshQueue(queue: readonly CardMember[], page: readonly CardMember[]): CardMember[] {
    const fresh = new Map(page.map((m) => [m.uuid, m]));

    return queue.map((m) => fresh.get(m.uuid) ?? m);
}

export type SelectionView =
    | { mode: 'single'; member: CardMember | null }
    | { mode: 'gallery'; members: CardMember[] };

/**
 * What the preview shows for a selection (= the print queue): several members (or All members) are
 * a gallery, one member is the single flip card, none falls back to the member last picked.
 */
export function selectionView(selection: readonly CardMember[], fallback: CardMember | null): SelectionView {
    if (selection.length > 1) {
        return { mode: 'gallery', members: [...selection] };
    }

    return { mode: 'single', member: selection[0] ?? fallback };
}

/** Up/Down in the picker list: clamped, -1 when the list is empty. */
export function moveCursor(current: number, delta: number, length: number): number {
    if (length === 0) {
        return -1;
    }

    return Math.min(Math.max(0, current + delta), length - 1);
}

export const sheetCount = (count: number, perSheet = PER_SHEET): number =>
    Math.max(1, Math.ceil(count / perSheet));

/** The members on one A4 sheet (0-based), ten at a time. */
export function sheetMembers<T>(rows: readonly T[], sheet: number, perSheet = PER_SHEET): T[] {
    return rows.slice(sheet * perSheet, (sheet + 1) * perSheet);
}

/** POST cards.prepare: issues a missing card per customer and answers with the printable card ids. */
export async function prepareCards(uuids: readonly string[]): Promise<number[]> {
    const { card_ids } = await apiRequest<{ card_ids: number[] }>('/app/membership-cards/prepare', {
        method: 'POST',
        body: { customers: uuids },
    });

    return card_ids;
}

/** Print-ready HTML (GET) for existing card ids; the GET never issues a card. */
export function printUrl(ids: readonly number[], layout: PrintLayout, crop = false): string {
    const query = new URLSearchParams({ ids: ids.join(','), layout });
    if (crop || layout === 'a4') {
        query.set('crop', crop ? '1' : '0');
    }

    return `/app/membership-cards/print?${query.toString()}`;
}

export const exportUrl = (ids: readonly number[]): string =>
    `/app/membership-cards/export.csv?${new URLSearchParams({ ids: ids.join(',') }).toString()}`;
