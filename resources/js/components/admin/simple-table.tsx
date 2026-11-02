import type { ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

export type SimpleColumn<Row> = {
    id: string;
    headerKey: string;
    cell: (row: Row) => ReactNode;
    numeric?: boolean;
};

/** A plain table for short settings lists (no paging). Rows are not clickable; actions are buttons in a column. */
export function SimpleTable<Row extends { id: number }>({
    caption,
    rows,
    columns,
}: {
    caption: string;
    rows: Row[];
    columns: SimpleColumn<Row>[];
}) {
    const { t } = useT();

    if (rows.length === 0) {
        return <EmptyState title={t('table.empty')} />;
    }

    return (
        <div className="overflow-x-auto rounded-lg border border-border">
            <table className="w-full text-sm">
                <caption className="sr-only">{caption}</caption>
                <thead className="bg-muted text-left text-muted-foreground">
                    <tr>
                        {columns.map((c) => (
                            <th
                                key={c.id}
                                scope="col"
                                className={cn(
                                    'px-3 py-2 font-medium',
                                    c.numeric && 'text-right',
                                )}
                            >
                                {t(c.headerKey)}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.id} className="border-t border-border">
                            {columns.map((c) => (
                                <td
                                    key={c.id}
                                    className={cn(
                                        'px-3 py-2',
                                        c.numeric && 'num text-right',
                                    )}
                                >
                                    {c.cell(row)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
