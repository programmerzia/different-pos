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

/** A plain table for short settings lists (no paging), in the mockup's settings-table look. Rows are not clickable; actions are buttons in a column. */
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
        <div className="overflow-x-auto rounded-xl border border-border bg-surface shadow-xs">
            <table className="w-full text-left text-xs">
                <caption className="sr-only">{caption}</caption>
                <thead className="border-b bg-surface-sunken font-semibold text-muted-foreground">
                    <tr>
                        {columns.map((c) => (
                            <th
                                key={c.id}
                                scope="col"
                                className={cn(
                                    'px-3 py-2.5 font-semibold',
                                    c.numeric && 'text-right',
                                )}
                            >
                                {t(c.headerKey)}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-border">
                    {rows.map((row) => (
                        <tr
                            key={row.id}
                            className="transition-colors hover:bg-surface-sunken/50"
                        >
                            {columns.map((c) => (
                                <td
                                    key={c.id}
                                    className={cn(
                                        'px-3 py-2.5',
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
