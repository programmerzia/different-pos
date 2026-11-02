import { describe, expect, it, vi } from 'vite-plus/test';
import {
    HotkeyConflictError,
    HotkeyRegistry,
    type HotkeyDef,
} from './registry';

const def = (
    over: Partial<HotkeyDef> & Pick<HotkeyDef, 'id' | 'keys'>,
): HotkeyDef => ({
    scope: 'global',
    labelKey: 'x',
    handler: () => undefined,
    ...over,
});

describe('HotkeyRegistry conflicts', () => {
    it('throws in strict (dev) mode when two ids share keys in the same scope', () => {
        const r = new HotkeyRegistry(() => true);
        r.register(def({ id: 'a', keys: 'mod+k' }));
        expect(() =>
            r.register(def({ id: 'b', keys: 'ctrl+k' })),
        ).not.toThrow(); // mod != ctrl: distinct bindings
        expect(() => r.register(def({ id: 'c', keys: 'mod+k' }))).toThrow(
            HotkeyConflictError,
        );
    });

    it('treats a chord prefix as a conflict', () => {
        const r = new HotkeyRegistry(() => true);
        r.register(def({ id: 'go.sales', keys: 'g s' }));
        expect(() => r.register(def({ id: 'plain.g', keys: 'g' }))).toThrow(
            HotkeyConflictError,
        );
    });

    it('lets the same key live in two different page scopes', () => {
        const r = new HotkeyRegistry(() => true);
        r.register(def({ id: 'list.new', keys: 'n', scope: 'list' }));
        expect(() =>
            r.register(def({ id: 'form.n', keys: 'n', scope: 'form' })),
        ).not.toThrow();
    });

    it('global overlaps every scope', () => {
        const r = new HotkeyRegistry(() => true);
        r.register(def({ id: 'g.n', keys: 'n' }));
        expect(() =>
            r.register(def({ id: 'list.n', keys: 'n', scope: 'list' })),
        ).toThrow(HotkeyConflictError);
    });

    it('re-registering the same id replaces it (hot reload / strict mode)', () => {
        const r = new HotkeyRegistry(() => true);
        r.register(def({ id: 'a', keys: 'x' }));
        expect(() => r.register(def({ id: 'a', keys: 'x' }))).not.toThrow();
        expect(r.size()).toBe(1);
    });

    it('warns and ignores the second one in production', () => {
        const warn = vi
            .spyOn(console, 'warn')
            .mockImplementation(() => undefined);
        const r = new HotkeyRegistry(() => false);
        r.register(def({ id: 'a', keys: 'x' }));
        r.register(def({ id: 'b', keys: 'x' }));
        expect(warn).toHaveBeenCalledOnce();
        expect(r.get('b')).toBeUndefined();
        warn.mockRestore();
    });

    it('unregister removes only its own registration', () => {
        const r = new HotkeyRegistry(() => true);
        const off = r.register(def({ id: 'a', keys: 'x' }));
        off();
        expect(r.size()).toBe(0);
    });
});

describe('scopes', () => {
    it('only the top scope (plus global) is active; a dialog hides page and global keys', () => {
        const r = new HotkeyRegistry(() => true);
        r.register(def({ id: 'g', keys: 'f1' }));
        r.register(def({ id: 'palette', keys: 'mod+k', inDialog: true }));
        r.register(def({ id: 'l', keys: 'n', scope: 'list' }));
        const popList = r.pushScope('list');
        expect(
            r
                .active()
                .map((e) => e.id)
                .sort(),
        ).toEqual(['g', 'l', 'palette']);
        const popDialog = r.pushScope('dialog');
        expect(r.active().map((e) => e.id)).toEqual(['palette']);
        popDialog();
        popList();
        expect(
            r
                .active()
                .map((e) => e.id)
                .sort(),
        ).toEqual(['g', 'palette']);
    });

    it('display-only entries are listed but never active', () => {
        const r = new HotkeyRegistry(() => true);
        r.register(def({ id: 'sidebar', keys: 'mod+b', display: true }));
        expect(r.active()).toEqual([]);
        expect(r.listVisible().map((e) => e.id)).toEqual(['sidebar']);
    });
});
