import { describe, expect, it } from 'vitest';
import en from '@/i18n/en.json';
import bn from '@/i18n/bn.json';
import { activeNav, NAV, NAV_SECTIONS } from './nav';

const lookup = (dict: unknown, key: string): unknown =>
    key
        .split('.')
        .reduce<unknown>(
            (o, k) => (o as Record<string, unknown> | undefined)?.[k],
            dict,
        );

describe('NAV', () => {
    it('has the baseline mockup sections in order', () => {
        expect(NAV_SECTIONS.map((s) => s.id)).toEqual([
            'sales',
            'customers',
            'catalog',
            'analytics',
            'system',
        ]);
    });

    it('has unique ids and unique G-chords', () => {
        const ids = NAV.map((n) => n.id);
        expect(new Set(ids).size).toBe(ids.length);
        const chords = NAV.flatMap((n) => (n.chord ? [n.chord] : []));
        expect(new Set(chords).size).toBe(chords.length);
        expect(chords).toEqual(
            expect.arrayContaining([
                's',
                't',
                'u',
                'c',
                'a',
                'w',
                'i',
                'k',
                'p',
                'v',
                'd',
                'r',
                ',',
            ]),
        );
    });

    it('gates every item and translates it in EN and BN', () => {
        const keys = [
            ...NAV_SECTIONS.map((s) => s.labelKey),
            ...NAV.flatMap((n) => [
                n.labelKey,
                ...(n.children ?? []).map((c) => c.labelKey),
            ]),
        ];
        for (const n of NAV) {
            expect(n.permission, n.id).toBeDefined();
        }
        for (const k of keys) {
            if (k.startsWith('nav.')) {
                expect(lookup(en, k), `en ${k}`).toBeTypeOf('string');
                expect(lookup(bn, k), `bn ${k}`).toBeTypeOf('string');
            }
        }
    });
});

describe('activeNav', () => {
    it('matches the dashboard only on /app itself', () => {
        expect(activeNav('/app')?.groupId).toBe('dashboard');
        expect(activeNav('/app/sales')?.groupId).toBe('sales');
        expect(activeNav('/app/sales/12')?.groupId).toBe('sales');
    });

    it('prefers the longest prefix, so one item is active at a time', () => {
        expect(activeNav('/app/customers/statement')?.groupId).toBe('statement');
        expect(activeNav('/app/customers/abc-uuid')?.groupId).toBe('customers');
        expect(activeNav('/app/settings/users/3/edit')?.groupId).toBe('users');
        expect(activeNav('/app/settings/roles')).toEqual({
            groupId: 'settings',
            leafId: 'settings.roles',
        });
    });

    it('lights the item a hidden page is folded into', () => {
        expect(activeNav('/app/adjustments/create')?.groupId).toBe('stock');
    });

    it('does not match on a shared string prefix', () => {
        expect(activeNav('/app/salesx')).toBeNull();
        expect(activeNav('/somewhere')).toBeNull();
    });
});
