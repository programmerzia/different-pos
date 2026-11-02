import { describe, expect, it } from 'vite-plus/test';
import {
    INITIAL_NAV,
    reduceNavKey,
    type NavKey,
    type NavState,
} from './table-nav';

const k = (key: string, mods: Partial<NavKey> = {}): NavKey => ({
    key,
    shiftKey: false,
    ctrlKey: false,
    metaKey: false,
    altKey: false,
    ...mods,
});
const press = (state: NavState, key: NavKey, count = 5) =>
    reduceNavKey(state, key, count);

describe('table keyboard navigation', () => {
    it('j / ArrowDown move down from the first key press, k / ArrowUp up, clamped', () => {
        let s = press(INITIAL_NAV, k('j')).state;
        expect(s.active).toBe(0);
        s = press(s, k('ArrowDown')).state;
        s = press(s, k('j')).state;
        expect(s.active).toBe(2);
        s = press(s, k('k')).state;
        expect(s.active).toBe(1);
        s = press({ ...s, active: 4 }, k('j')).state;
        expect(s.active).toBe(4);
        s = press({ ...s, active: 0 }, k('ArrowUp')).state;
        expect(s.active).toBe(0);
    });

    it('Home / End jump', () => {
        expect(
            press({ ...INITIAL_NAV, active: 2 }, k('End')).state.active,
        ).toBe(4);
        expect(
            press({ ...INITIAL_NAV, active: 2 }, k('Home')).state.active,
        ).toBe(0);
    });

    it('Enter opens, Ctrl+Enter opens in a new tab, nothing without an active row', () => {
        const at = { ...INITIAL_NAV, active: 1 };
        expect(press(at, k('Enter')).effect).toBe('open');
        expect(press(at, k('Enter', { ctrlKey: true })).effect).toBe(
            'open_new_tab',
        );
        expect(press(INITIAL_NAV, k('Enter')).effect).toBeNull();
    });

    it('Space toggles selection of the active row', () => {
        let s = press({ ...INITIAL_NAV, active: 1 }, k(' ')).state;
        expect([...s.selected]).toEqual([1]);
        s = press(s, k(' ')).state;
        expect(s.selected.size).toBe(0);
    });

    it('Shift+Arrow extends a range from the anchor and shrinks back', () => {
        let s = press(
            { ...INITIAL_NAV, active: 1, anchor: 1 },
            k('ArrowDown', { shiftKey: true }),
        ).state;
        s = press(s, k('ArrowDown', { shiftKey: true })).state;
        expect([...s.selected].sort((a, b) => a - b)).toEqual([1, 2, 3]);
        s = press(s, k('ArrowUp', { shiftKey: true })).state;
        expect([...s.selected].sort((a, b) => a - b)).toEqual([1, 2]);
    });

    it('[ and ] page; Escape clears a selection; Ctrl+A selects all', () => {
        expect(press(INITIAL_NAV, k('['), 0).effect).toBe('prev_page');
        expect(press(INITIAL_NAV, k(']')).effect).toBe('next_page');
        const sel = { active: 1, anchor: 1, selected: new Set([1, 2]) };
        const cleared = press(sel, k('Escape'));
        expect(cleared.state.selected.size).toBe(0);
        expect(cleared.handled).toBe(true);
        expect(
            press(INITIAL_NAV, k('a', { ctrlKey: true })).state.selected.size,
        ).toBe(5);
    });

    it('ignores unrelated keys and an empty table', () => {
        expect(press(INITIAL_NAV, k('x')).handled).toBe(false);
        expect(press(INITIAL_NAV, k('j'), 0).state.active).toBe(-1);
    });
});
