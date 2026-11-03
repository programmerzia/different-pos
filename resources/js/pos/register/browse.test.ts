import { describe, expect, it } from 'vite-plus/test';
import {
    BROWSE_CLOSED,
    browseReducer,
    chooseOutcome,
    escapeStep,
    showSearchDropdown,
    type BrowseState,
} from './browse';

const open: BrowseState = { open: true, category: null };

describe('browse surface', () => {
    it('starts closed: the main area shows the cart, not product tiles', () => {
        expect(BROWSE_CLOSED).toEqual({ open: false, category: null });
    });

    it('opens from Browse catalogue and toggles back to the cart', () => {
        const opened = browseReducer(BROWSE_CLOSED, { type: 'open' });
        expect(opened.open).toBe(true);
        expect(browseReducer(opened, { type: 'open' })).toBe(opened);
        expect(browseReducer(opened, { type: 'toggle' })).toEqual(
            BROWSE_CLOSED,
        );
        expect(browseReducer(BROWSE_CLOSED, { type: 'toggle' }).open).toBe(
            true,
        );
    });

    it('a category chip filters the grid and keeps (or puts) it open', () => {
        expect(
            browseReducer(BROWSE_CLOSED, { type: 'category', id: 4 }),
        ).toEqual({ open: true, category: 4 });
        expect(browseReducer(open, { type: 'category', id: null })).toEqual(
            open,
        );
    });

    it('closing (Back to cart, Esc, product added) returns to the cart and forgets the category', () => {
        const filtered = browseReducer(open, { type: 'category', id: 7 });
        expect(browseReducer(filtered, { type: 'close' })).toEqual(
            BROWSE_CLOSED,
        );
        expect(browseReducer(BROWSE_CLOSED, { type: 'close' })).toBe(
            BROWSE_CLOSED,
        );
    });

    it('after the variant picker adds lines the surface closes, so the cart shows the new lines', () => {
        // The page dispatches close on the picker's Add; Cancel leaves the surface as it was.
        const browsing = browseReducer(BROWSE_CLOSED, {
            type: 'category',
            id: 2,
        });
        const afterAdd = browseReducer(browsing, { type: 'close' });
        expect(afterAdd.open).toBe(false);
        expect(chooseOutcome({ kind: 'family', variants: [1, 2] })).toBe(
            'picker',
        );
        expect(chooseOutcome({ kind: 'product', variants: [1] })).toBe('add');
        expect(chooseOutcome({ kind: 'family', variants: [] })).toBe('none');
    });
});

describe('search dropdown', () => {
    it('shows under the field only while text is typed and the browse surface is closed', () => {
        expect(showSearchDropdown('', false)).toBe(false);
        expect(showSearchDropdown('   ', false)).toBe(false);
        expect(showSearchDropdown('pan', false)).toBe(true);
        // With the surface open the typed text filters the grid instead.
        expect(showSearchDropdown('pan', true)).toBe(false);
    });
});

describe('Esc on the register', () => {
    const base = {
        completed: false,
        query: '',
        browseOpen: false,
        hasLines: false,
    };

    it('peels one layer at a time: completed card, search text, browse surface, then clear the bill', () => {
        expect(escapeStep({ ...base, completed: true, query: 'x' })).toBe(
            'new_sale',
        );
        expect(escapeStep({ ...base, query: 'x', browseOpen: true })).toBe(
            'clear_query',
        );
        expect(escapeStep({ ...base, browseOpen: true, hasLines: true })).toBe(
            'close_browse',
        );
        expect(escapeStep({ ...base, hasLines: true })).toBe('confirm_clear');
        expect(escapeStep(base)).toBeNull();
    });
});
