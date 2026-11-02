/**
 * Pure keyboard model for DataTable (rows only; no DOM). 02_AppFlow §1.3:
 * ↑↓ / J K move, Enter open, Space toggle select, Shift+↑↓ extend, [ ] page, Esc clear.
 */
export type NavState = {
    /** Row index that has focus, or -1 before the first key. */
    active: number;
    /** Selected row indexes. */
    selected: ReadonlySet<number>;
    /** Where a Shift-extend started. */
    anchor: number;
};

export type NavKey = {
    key: string;
    shiftKey: boolean;
    ctrlKey: boolean;
    metaKey: boolean;
    altKey: boolean;
};

export type NavEffect =
    | 'open'
    | 'open_new_tab'
    | 'prev_page'
    | 'next_page'
    | 'select_all'
    | null;

export const INITIAL_NAV: NavState = {
    active: -1,
    selected: new Set(),
    anchor: -1,
};

const range = (a: number, b: number): number[] => {
    const [from, to] = a <= b ? [a, b] : [b, a];

    return Array.from({ length: to - from + 1 }, (_, i) => from + i);
};

/** Returns the next state plus a side effect for the caller; `handled` = preventDefault. */
export function reduceNavKey(
    state: NavState,
    event: NavKey,
    count: number,
): { state: NavState; effect: NavEffect; handled: boolean } {
    const none = (s: NavState = state, handled = false) => ({
        state: s,
        effect: null,
        handled,
    });
    if (count === 0) {
        return event.key === '[' || event.key === ']'
            ? {
                  state,
                  effect: event.key === '[' ? 'prev_page' : 'next_page',
                  handled: true,
              }
            : none();
    }
    const clamp = (i: number) => Math.max(0, Math.min(count - 1, i));
    const key = event.key.length === 1 ? event.key.toLowerCase() : event.key;
    const plain = !event.ctrlKey && !event.metaKey && !event.altKey;

    if (
        plain &&
        (key === 'ArrowDown' || key === 'j' || key === 'ArrowUp' || key === 'k')
    ) {
        const down = key === 'ArrowDown' || key === 'j';
        const active =
            state.active < 0 ? 0 : clamp(state.active + (down ? 1 : -1));
        if (event.shiftKey && (key === 'ArrowDown' || key === 'ArrowUp')) {
            const anchor =
                state.anchor < 0 ? Math.max(0, state.active) : state.anchor;

            return none(
                { active, anchor, selected: new Set(range(anchor, active)) },
                true,
            );
        }

        return none({ ...state, active, anchor: active }, true);
    }
    if (plain && key === 'Home') {
        return none({ ...state, active: 0, anchor: 0 }, true);
    }
    if (plain && key === 'End') {
        return none({ ...state, active: count - 1, anchor: count - 1 }, true);
    }
    if (plain && key === ' ' && state.active >= 0) {
        const selected = new Set(state.selected);
        if (selected.has(state.active)) {
            selected.delete(state.active);
        } else {
            selected.add(state.active);
        }

        return none({ ...state, selected, anchor: state.active }, true);
    }
    if (
        key === 'Enter' &&
        state.active >= 0 &&
        !event.altKey &&
        !event.shiftKey
    ) {
        return {
            state,
            effect: event.ctrlKey || event.metaKey ? 'open_new_tab' : 'open',
            handled: true,
        };
    }
    if ((event.ctrlKey || event.metaKey) && key === 'a') {
        return {
            state: { ...state, selected: new Set(range(0, count - 1)) },
            effect: 'select_all',
            handled: true,
        };
    }
    if (plain && key === '[') {
        return { state, effect: 'prev_page', handled: true };
    }
    if (plain && key === ']') {
        return { state, effect: 'next_page', handled: true };
    }
    if (key === 'Escape' && (state.selected.size > 0 || state.active >= 0)) {
        return none(
            state.selected.size > 0
                ? { ...state, selected: new Set(), anchor: state.active }
                : state,
            state.selected.size > 0,
        );
    }

    return none();
}
