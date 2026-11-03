import { describe, expect, it, vi } from 'vite-plus/test';
import { HotkeyRegistry } from '@/hotkeys/registry';
import { POS_KEYS, posHotkeySpecs } from './hotkeys';

/** The shell's global keys (components/shell/shell-hotkeys.tsx) the register must not collide with. */
const GLOBAL = [
    'mod+k',
    '?',
    'f1',
    'mod+b',
    'alt+l',
    'alt+t',
    'alt+b',
    'alt+n',
    'alt+arrowleft',
    'shift+f10',
    'g d',
    'g s',
    'g c',
    'g i',
    'g p',
    'g m',
    'g r',
    'g ,',
];

describe('register hotkeys', () => {
    it('register in the pos scope without conflicts, next to the global shell keys', () => {
        const registry = new HotkeyRegistry(() => true);
        GLOBAL.forEach((keys, i) =>
            registry.register({
                id: `g${i}`,
                keys,
                scope: 'global',
                labelKey: 'x',
                handler: () => undefined,
            }),
        );

        const run = vi.fn();
        expect(() =>
            posHotkeySpecs(run).forEach((spec) =>
                registry.register({ ...spec, scope: 'pos' }),
            ),
        ).not.toThrow();
        expect(registry.size()).toBe(GLOBAL.length + POS_KEYS.length);
    });

    it('are live only while pos is the top scope, and run their command', () => {
        const registry = new HotkeyRegistry(() => true);
        const run = vi.fn();
        posHotkeySpecs(run).forEach((spec) =>
            registry.register({ ...spec, scope: 'pos' }),
        );
        expect(registry.active()).toHaveLength(0);

        const pop = registry.pushScope('pos');
        expect(registry.active().map((h) => h.id)).toContain('pos.pay');
        registry
            .get('pos.pay')
            ?.handler(new KeyboardEvent('keydown', { key: 'F9' }));
        expect(run).toHaveBeenCalledWith('pay', expect.any(KeyboardEvent));

        const popDialog = registry.pushScope('dialog');
        expect(registry.active().some((h) => h.scope === 'pos')).toBe(false);
        popDialog();
        pop();
    });

    it('cover the keys the register needs', () => {
        const keys = Object.fromEntries(
            POS_KEYS.map((k) => [k.command, k.keys]),
        );
        expect(keys).toMatchObject({
            search: 'f2',
            browse: 'alt+g',
            customer: 'f4',
            price_check: 'f5',
            line_discount: 'f6',
            bill_discount: 'shift+f6',
            today: 'f7',
            hold: 'f8',
            pay: 'f9',
            returns: 'f11',
            cancel: 'escape',
        });
    });
});
