import { describe, expect, it } from 'vite-plus/test';
import type { CardMember } from '@/components/cards/card-face';
import { cardCode, cid, formatNumber, maskNumber, qrPayload, sinceShort, tierOf } from '@/components/cards/card-face';
import {
    exportUrl,
    moveCursor,
    printUrl,
    queueAll,
    refreshQueue,
    selectionView,
    sheetCount,
    sheetMembers,
    toggleQueued,
} from './card-studio';

const member = (n: number, extra: Partial<CardMember> = {}): CardMember => ({
    uuid: `u${n}`,
    name: `Member ${n}`,
    name_bn: null,
    shop_name: null,
    phone: null,
    customer_no: '1000 0017',
    member_since: null,
    group: null,
    card: null,
    ...extra,
});

describe('print queue', () => {
    it('toggles a member in and out, keeping order', () => {
        const a = member(1);
        const b = member(2);
        let q = toggleQueued([], a);
        q = toggleQueued(q, b);
        expect(q.map((m) => m.uuid)).toEqual(['u1', 'u2']);
        expect(toggleQueued(q, a).map((m) => m.uuid)).toEqual(['u2']);
    });

    it('queues a whole page without duplicates', () => {
        const page = [member(1), member(2), member(3)];
        expect(queueAll([member(2)], page).map((m) => m.uuid)).toEqual(['u2', 'u1', 'u3']);
        expect(queueAll(page, page)).toHaveLength(3);
    });

    it('refreshes queued members from a newer page', () => {
        const printed = member(1, { card: { id: 9, code: 'MC1000001701', status: 'active', issue_no: 1, print_count: 1, printed_at: null } });
        const q = refreshQueue([member(1), member(5)], [printed, member(2)]);
        expect(q[0]?.card?.print_count).toBe(1);
        expect(q[1]?.uuid).toBe('u5');
    });
});

describe('picker cursor', () => {
    it('clamps to the list and is -1 when empty', () => {
        expect(moveCursor(0, -1, 5)).toBe(0);
        expect(moveCursor(3, 1, 5)).toBe(4);
        expect(moveCursor(4, 1, 5)).toBe(4);
        expect(moveCursor(0, 1, 0)).toBe(-1);
    });
});

describe('A4 sheets', () => {
    it('splits members ten to a sheet', () => {
        const rows = Array.from({ length: 23 }, (_, i) => i);
        expect(sheetCount(23)).toBe(3);
        expect(sheetCount(0)).toBe(1);
        expect(sheetMembers(rows, 0)).toHaveLength(10);
        expect(sheetMembers(rows, 2)).toEqual([20, 21, 22]);
        expect(sheetMembers(rows, 3)).toEqual([]);
    });
});

describe('press URLs', () => {
    it('prints by customer with layout and crop marks', () => {
        expect(printUrl([1, 2], 'cr80')).toBe('/app/membership-cards/print?ids=1%2C2&layout=cr80');
        expect(printUrl([1], 'bleed', true)).toBe('/app/membership-cards/print?ids=1&layout=bleed&crop=1');
        expect(printUrl([1], 'a4', false)).toBe('/app/membership-cards/print?ids=1&layout=a4&crop=0');
        expect(exportUrl([1, 2])).toBe('/app/membership-cards/export.csv?ids=1%2C2');
    });
});

describe('card face rules', () => {
    it('groups the number 4 + 4 and derives the code, CID, tier and since', () => {
        expect(formatNumber('10000017')).toBe('1000 0017');
        expect(formatNumber('1000 0017')).toBe('1000 0017');
        expect(cid('1000 0017')).toBe('017');
        expect(tierOf('Platinum VIP')).toBe('vip');
        expect(tierOf('Whole Sale')).toBe('gold');
        expect(tierOf(null)).toBe('gold');
        expect(sinceShort('2026-01-05')).toBe('01/26');
        expect(sinceShort(null)).toBe('--/--');
        expect(cardCode(member(1))).toBe('MC1000001701');
        expect(cardCode(member(1, { card: { id: 1, code: 'MC1000001703', status: 'active', issue_no: 3, print_count: 0, printed_at: null } }))).toBe('MC1000001703');
        // A lost card is never reused: the next print issues the next number.
        expect(cardCode(member(1, { card: { id: 1, code: 'MC1000001701', status: 'lost', issue_no: 1, print_count: 0, printed_at: null } }))).toBe('MC1000001702');
    });
});

describe('selection view', () => {
    it('falls back to the picked member when nothing is selected', () => {
        const a = member(1);
        expect(selectionView([], a)).toEqual({ mode: 'single', member: a });
        expect(selectionView([], null)).toEqual({ mode: 'single', member: null });
    });

    it('shows one selected member as the single flip card', () => {
        const a = member(1);
        expect(selectionView([a], member(2))).toEqual({ mode: 'single', member: a });
    });

    it('shows several selected members (or all) as a gallery in selection order', () => {
        const [a, b, c] = [member(1), member(2), member(3)];
        expect(selectionView([c, a, b], null)).toEqual({ mode: 'gallery', members: [c, a, b] });
    });
});

describe('masked number and QR payload', () => {
    it('masks all but the last four digits, keeping the grouping', () => {
        expect(maskNumber('1000 0017')).toBe('•••• 0017');
        expect(maskNumber('10009231')).toBe('•••• 9231');
        expect(maskNumber('123')).toBe('123');
    });

    it('encodes name|shop|number, with separators and line breaks removed from the fields', () => {
        expect(qrPayload({ name: 'Rahim Uddin', shop_name: 'Rahim Store', customer_no: '1000 0017' })).toBe(
            'Rahim Uddin|Rahim Store|10000017',
        );
        expect(qrPayload({ name: ' A|B\nC ', shop_name: null, customer_no: '1000 0017' })).toBe('A B C||10000017');
    });
});
