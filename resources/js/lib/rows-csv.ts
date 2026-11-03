/**
 * Client-side CSV of rows already on screen (mockup "Export ▾" on the catalog/stock lists).
 * Cells that a spreadsheet would read as a formula (= + - @ tab CR) are prefixed with `'`;
 * plain numbers (`-50.00`, `+8801712345678`) are data and stay numeric.
 */
export type CsvColumn<Row> = { header: string; value: (row: Row) => string };

const FORMULA = /^[=+\-@\t\r]/;
const NUMBER = /^[+-]?\d+(\.\d+)?$/;

export function csvCell(raw: string): string {
    const text = FORMULA.test(raw) && !NUMBER.test(raw) ? `'${raw}` : raw;

    return /[",\n\r]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
}

export function rowsToCsv<Row>(columns: CsvColumn<Row>[], rows: Row[]): string {
    const lines = [
        columns.map((c) => csvCell(c.header)).join(','),
        ...rows.map((r) => columns.map((c) => csvCell(c.value(r))).join(',')),
    ];

    // BOM so Excel opens Bangla text as UTF-8.
    return `﻿${lines.join('\r\n')}\r\n`;
}

export function downloadRowsCsv<Row>(
    filename: string,
    columns: CsvColumn<Row>[],
    rows: Row[],
): void {
    const blob = new Blob([rowsToCsv(columns, rows)], {
        type: 'text/csv;charset=utf-8',
    });
    const href = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = href;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(href);
}
