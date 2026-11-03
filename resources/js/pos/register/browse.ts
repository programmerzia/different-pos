/**
 * The register's catalogue browse surface (owner feedback 2026-11-03): the main area shows the bill
 * ledger; the product grid is only a surface opened on purpose (Browse catalogue, Alt+G) over it.
 * Typing in the scan field shows a dropdown of hits under the field instead, unless the browse surface
 * is already open, which then filters its grid by the typed text.
 */
export type BrowseState = {
    open: boolean;
    /** Category chip picked inside the browse surface (null = All items). */
    category: number | null;
};

export type BrowseAction =
    | { type: 'open' }
    | { type: 'toggle' }
    | { type: 'category'; id: number | null }
    /** Back to cart, Esc, or a product added (directly or through the variant picker). */
    | { type: 'close' };

export const BROWSE_CLOSED: BrowseState = { open: false, category: null };

export function browseReducer(
    state: BrowseState,
    action: BrowseAction,
): BrowseState {
    switch (action.type) {
        case 'open':
            return state.open ? state : { ...state, open: true };
        case 'toggle':
            return state.open ? BROWSE_CLOSED : { ...state, open: true };
        case 'category':
            return { open: true, category: action.id };
        case 'close':
            return state.open || state.category !== null
                ? BROWSE_CLOSED
                : state;
    }
}

/** The search dropdown under the field: text typed, browse surface closed, no completed-sale card. */
export function showSearchDropdown(
    query: string,
    browseOpen: boolean,
): boolean {
    return query.trim() !== '' && !browseOpen;
}

export type EscapeStep =
    | 'new_sale'
    | 'clear_query'
    | 'close_browse'
    | 'confirm_clear'
    | null;

/**
 * What one Esc on the register does, innermost first: leave the completed-sale card, clear the typed
 * search (closes the dropdown), close the browse surface (back to the cart), then ask to clear the bill.
 */
export function escapeStep(s: {
    completed: boolean;
    query: string;
    browseOpen: boolean;
    hasLines: boolean;
}): EscapeStep {
    if (s.completed) {
        return 'new_sale';
    }
    if (s.query !== '') {
        return 'clear_query';
    }
    if (s.browseOpen) {
        return 'close_browse';
    }

    return s.hasLines ? 'confirm_clear' : null;
}

/** Choosing a catalogue item: a plain product (one variant) goes straight onto the bill, a family opens the picker. */
export function chooseOutcome(item: {
    kind: 'product' | 'family';
    variants: unknown[];
}): 'add' | 'picker' | 'none' {
    if (item.variants.length === 0) {
        return 'none';
    }

    return item.kind === 'product' ? 'add' : 'picker';
}
