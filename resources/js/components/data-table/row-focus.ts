/**
 * Keyboard entry into a list (02_AppFlow §1.3): the DataTable marks its row container with
 * `data-list-rows` and every row with `data-list-row`; the roving row (tabindex 0) is the one to
 * enter on. Used by `J`/`K` with nothing focused, `↓` in the page search and `Alt+↓` from anywhere.
 */
const ROWS = '[data-list-rows]';
const ROW = '[data-list-row]';

/** True when `el` is (inside) a list row, so the table's own keys already apply. */
export function isInListRows(el: Element | null): boolean {
    return el instanceof Element && el.closest(`${ROWS} ${ROW}`) !== null;
}

/** Focuses the list's entry row (the roving one, else the first). False when the page has no rows. */
export function focusListEntryRow(root: ParentNode = document): boolean {
    const row =
        root.querySelector<HTMLElement>(`${ROWS} ${ROW}[tabindex="0"]`) ??
        root.querySelector<HTMLElement>(`${ROWS} ${ROW}`);
    if (!row) {
        return false;
    }
    row.focus();

    return true;
}
