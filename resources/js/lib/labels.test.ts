import { afterEach, describe, expect, it } from 'vite-plus/test';
import { i18n } from '@/i18n';
import { auditEventLabel, permissionLabel } from '@/lib/labels';

const t = i18n.t.bind(i18n);

afterEach(async () => {
    await i18n.changeLanguage('en');
});

describe('permissionLabel', () => {
    it('translates a permission code in both languages', async () => {
        expect(permissionLabel(t, 'pos.sell')).toBe(
            i18n.t('roles.perm.pos_sell'),
        );
        await i18n.changeLanguage('bn');
        expect(permissionLabel(i18n.t.bind(i18n), 'pos.sell')).toMatch(/[ঀ-৿]/);
    });

    it('falls back to the code', () => {
        expect(permissionLabel(t, 'nope.unknown')).toBe('nope.unknown');
    });
});

describe('auditEventLabel', () => {
    it('translates event codes in EN and BN', async () => {
        expect(auditEventLabel(t, 'sale.voided')).toBe('Sale voided');
        expect(auditEventLabel(t, 'created')).toBe('Created');
        await i18n.changeLanguage('bn');
        expect(auditEventLabel(i18n.t.bind(i18n), 'sale.voided')).toMatch(
            /[ঀ-৿]/,
        );
    });

    it('falls back to the code for unknown events and for group names', () => {
        expect(auditEventLabel(t, 'thing.happened')).toBe('thing.happened');
        expect(auditEventLabel(t, 'sale')).toBe('sale');
    });
});
