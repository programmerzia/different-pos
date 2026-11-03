import { describe, expect, it } from 'vite-plus/test';
import { csvCell, rowsToCsv } from './rows-csv';

describe('rows-csv', () => {
    it('quotes separators and neutralises spreadsheet formulas', () => {
        expect(csvCell('plain')).toBe('plain');
        expect(csvCell('a,b')).toBe('"a,b"');
        expect(csvCell('say "hi"')).toBe('"say ""hi"""');
        expect(csvCell('=HYPERLINK("x")')).toBe(`"'=HYPERLINK(""x"")"`);
        expect(csvCell('@cmd')).toBe("'@cmd");
        expect(csvCell('+SUM(A1)')).toBe("'+SUM(A1)");
        expect(csvCell('-A1')).toBe("'-A1");
        expect(csvCell('-5+3')).toBe("'-5+3");
        expect(csvCell('\tx')).toBe("'\tx");
    });

    it('keeps plain numbers numeric (negative balances, E.164 phones)', () => {
        expect(csvCell('-50.00')).toBe('-50.00');
        expect(csvCell('-5')).toBe('-5');
        expect(csvCell('1250.50')).toBe('1250.50');
        expect(csvCell('+8801712345678')).toBe('+8801712345678');
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
