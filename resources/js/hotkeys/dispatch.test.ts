import { describe, expect, it } from 'vite-plus/test';
import { dispatchKey, mayFireWhileTyping } from './dispatch';
import type { KeyLike } from './matcher';
import { HotkeyRegistry, type HotkeyDef } from './registry';

const key = (k: string, mods: Partial<KeyLike> = {}): KeyLike => ({
    key: k,
    ctrlKey: false,
    metaKey: false,
    altKey: false,
    shiftKey: false,
    ...mods,
});

function setup(defs: Pick<HotkeyDef, 'id' | 'keys' | 'allowInInput'>[]) {
    const r = new HotkeyRegistry(() => true);
    for (const d of defs) {
        r.register({
            ...d,
            scope: 'global',
            labelKey: 'x',
            handler: () => undefined,
        });
    }

    return r.active();
}

describe('dispatchKey', () => {
    const active = setup([
        { id: 'palette', keys: 'mod+k' },
        { id: 'new', keys: 'n' },
        { id: 'sales', keys: 'g s' },
        { id: 'pos', keys: 'f1' },
        { id: 'esc', keys: 'escape' },
    ]);

    it('runs a chord across two events and resets the buffer', () => {
        const first = dispatchKey(active, [], key('g'), { typing: false });
        expect(first.type).toBe('pending');
        const second = dispatchKey(active, first.buffer, key('s'), {
            typing: false,
        });
        expect(second.type === 'fire' && second.def.id).toBe('sales');
        expect(second.buffer).toEqual([]);
    });

    it('a broken chord restarts with the new key', () => {
        const first = dispatchKey(active, [], key('g'), { typing: false });
        const second = dispatchKey(active, first.buffer, key('n'), {
            typing: false,
        });
        expect(second.type === 'fire' && second.def.id).toBe('new');
    });

    it('never fires single letters or chords while typing', () => {
        expect(dispatchKey(active, [], key('n'), { typing: true }).type).toBe(
            'none',
        );
        expect(dispatchKey(active, [], key('g'), { typing: true }).type).toBe(
            'none',
        );
    });

    it('still fires Ctrl combos, F-keys and Esc while typing', () => {
        expect(
            dispatchKey(active, [], key('k', { ctrlKey: true }), {
                typing: true,
            }).type,
        ).toBe('fire');
        expect(dispatchKey(active, [], key('F1'), { typing: true }).type).toBe(
            'fire',
        );
        expect(
            dispatchKey(active, [], key('Escape'), { typing: true }).type,
        ).toBe('fire');
    });

    it('Enter-style keys fire while typing only when the hotkey opts in', () => {
        const [plain] = setup([{ id: 'open', keys: 'enter' }]);
        const [opted] = setup([
            { id: 'open', keys: 'enter', allowInInput: true },
        ]);
        expect(mayFireWhileTyping(plain!)).toBe(false);
        expect(mayFireWhileTyping(opted!)).toBe(true);
    });
});
