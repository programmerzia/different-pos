import { describe, expect, it } from 'vite-plus/test';
import { csvCell, rowsToCsv } from './rows-csv';

describe('rows-csv', () => {
    it('quotes separators and neutralises spreadsheet formulas', () => {
        expect(csvCell('plain')).toBe('plain');
        expect(csvCell('a,b')).toBe('"a,b"');
        expect(csvCell('say "hi"')).toBe('"say ""hi"""');
        expect(csvCell('=HYPERLINK("x")')).toBe(`"'=HYPERLINK(""x"")"`);
        expect(csvCell('-5')).toBe("'-5");
        expect(csvCell('@cmd')).toBe("'@cmd");
    });

    it('writes a BOM, a header and CRLF rows', () => {
        const csv = rowsToCsv(
            [
                { header: 'SKU', value: (r: { sku: string }) => r.sku },
                { header: 'Name', value: () => 'পাঞ্জাবি' },
            ],
            [{ sku: 'P-1' }],
        );

        expect(csv).toBe('﻿SKU,Name\r\nP-1,পাঞ্জাবি\r\n');
    });
});
