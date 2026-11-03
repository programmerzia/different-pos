import type { ExportItem } from '@/components/page-header';

/** The three download formats every list and report export offers (server `TabularExport`). */
export type ExportFormat = 'csv' | 'xlsx' | 'pdf';

export const EXPORT_FORMATS: readonly ExportFormat[] = ['csv', 'xlsx', 'pdf'];

const KIND: Record<ExportFormat, NonNullable<ExportItem['kind']>> = {
    csv: 'csv',
    xlsx: 'excel',
    pdf: 'pdf',
};

/**
 * `/app/customers` + `csv` + `?q=x` → `/app/customers/export.csv?q=x`. `query` may start with `?`
 * or be a bare `a=b` string; `page` and `per_page` are the screen's concern and are dropped.
 */
export function exportUrl(
    base: string,
    format: ExportFormat,
    query: string = '',
): string {
    const params = new URLSearchParams(query.replace(/^\?/, ''));
    params.delete('page');
    params.delete('per_page');
    const search = params.toString();

    return `${base.replace(/\/$/, '')}/export.${format}${search ? `?${search}` : ''}`;
}

/** The ExportMenu's items in the mockup order: Excel first, then PDF, then CSV. */
export function exportItems(
    t: (key: string) => string,
    onSelect: (format: ExportFormat) => void,
    formats: readonly ExportFormat[] = ['xlsx', 'pdf', 'csv'],
): ExportItem[] {
    return formats.map((format) => ({
        id: `export-${format}`,
        kind: KIND[format],
        label: t(`table.export_${format}`),
        onSelect: () => onSelect(format),
    }));
}
