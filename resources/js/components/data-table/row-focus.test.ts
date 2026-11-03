import { afterEach, describe, expect, it } from 'vite-plus/test';
import {
    focusListEntryRow,
    isInListRows,
} from '@/components/data-table/row-focus';

afterEach(() => {
    document.body.innerHTML = '';
});

const list = (roving: number) => {
    document.body.innerHTML = `
        <input id="page-search" />
        <table data-list-rows=""><tbody>
            ${[0, 1, 2]
                .map(
                    (i) =>
                        `<tr data-list-row="" id="r${i}" tabindex="${i === roving ? 0 : -1}"><td><button id="b${i}">x</button></td></tr>`,
                )
                .join('')}
        </tbody></table>`;
};

describe('list keyboard entry', () => {
    it('focuses the roving row, or the first one', () => {
        list(1);
        expect(focusListEntryRow()).toBe(true);
        expect(document.activeElement?.id).toBe('r1');

        list(-1);
        expect(focusListEntryRow()).toBe(true);
        expect(document.activeElement?.id).toBe('r0');
    });

    it('does nothing without rows', () => {
        document.body.innerHTML = '<input id="page-search" />';
        expect(focusListEntryRow()).toBe(false);
    });

    it('knows whether focus is already on (or inside) a row', () => {
        list(0);
        expect(isInListRows(document.getElementById('page-search'))).toBe(
            false,
        );
        expect(isInListRows(document.getElementById('r2'))).toBe(true);
        expect(isInListRows(document.getElementById('b1'))).toBe(true);
        expect(isInListRows(null)).toBe(false);
    });
});
