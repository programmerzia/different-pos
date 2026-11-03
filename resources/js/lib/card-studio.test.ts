import { describe, expect, it } from 'vite-plus/test';
import type { CardMember } from '@/components/cards/card-face';
import { cardCode, cid, formatNumber, sinceShort, tierOf } from '@/components/cards/card-face';
import {
    exportUrl,
    moveCursor,
    printUrl,
    queueAll,
    refreshQueue,
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
        expect(printUrl(['a', 'b'], 'cr80')).toBe('/app/membership-cards/print?customers=a%2Cb&layout=cr80');
        expect(printUrl(['a'], 'bleed', true)).toBe('/app/membership-cards/print?customers=a&layout=bleed&crop=1');
        expect(printUrl(['a'], 'a4', false)).toBe('/app/membership-cards/print?customers=a&layout=a4&crop=0');
        expect(exportUrl(['a', 'b'])).toBe('/app/membership-cards/export.csv?customers=a%2Cb');
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
