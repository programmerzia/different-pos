import { describe, expect, it, vi } from 'vite-plus/test';
import { EXPORT_FORMATS, exportItems, exportUrl } from './export-items';

describe('export-items', () => {
    it('builds /export.{format} URLs with the list query minus paging', () => {
        expect(exportUrl('/app/customers', 'csv')).toBe(
            '/app/customers/export.csv',
        );
        expect(
            exportUrl(
                '/app/sales/',
                'xlsx',
                '?q=rahim&page=3&per_page=50&filter[status]=completed',
            ),
        ).toBe('/app/sales/export.xlsx?q=rahim&filter%5Bstatus%5D=completed');
        expect(exportUrl('/app/stock', 'pdf', 'sort=-on_hand')).toBe(
            '/app/stock/export.pdf?sort=-on_hand',
        );
    });

    it('offers Excel, PDF and CSV in the mockup order with translated labels and typed icons', () => {
        const onSelect = vi.fn();
        const items = exportItems((key) => `#${key}`, onSelect);

        expect(items.map((i) => [i.id, i.kind, i.label])).toEqual([
            ['export-xlsx', 'excel', '#table.export_xlsx'],
            ['export-pdf', 'pdf', '#table.export_pdf'],
            ['export-csv', 'csv', '#table.export_csv'],
        ]);
        items[1]?.onSelect();
        expect(onSelect).toHaveBeenCalledWith('pdf');
        expect(EXPORT_FORMATS).toEqual(['csv', 'xlsx', 'pdf']);
    });
});
