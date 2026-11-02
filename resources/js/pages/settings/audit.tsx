import { Eye, ScrollText } from 'lucide-react';
import { useState } from 'react';
import { AdminPage } from '@/components/admin/admin-page';
import { DataTable, type DataColumn } from '@/components/data-table/data-table';
import { useServerTable } from '@/components/data-table/server-table';
import { EmptyState } from '@/components/empty-state';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';

type Entry = {
    id: number;
    created_at: string | null;
    event: string | null;
    description: string;
    user: string | null;
    subject_type: string | null;
    subject_id: number | null;
    properties: Record<string, unknown>;
    changes: Record<string, unknown>;
};

type Props = {
    entries: Entry[];
    meta: { page: number; perPage: number; total: number; lastPage: number };
    users: { id: number; name: string }[];
    subjects: { value: string; label: string }[];
};

const stamp = (iso: string | null) =>
    iso ? iso.slice(0, 19).replace('T', ' ') : '-';

export default function Audit({ entries, meta, users, subjects }: Props) {
    const { t } = useT();
    const digits = useDigits();
    const table = useServerTable({ only: ['entries', 'meta'] });
    const [open, setOpen] = useState<Entry | null>(null);
    const filters = table.state.filters;

    const columns: DataColumn<Entry>[] = [
        {
            id: 'when',
            headerKey: 'audit.when',
            sortKey: 'created_at',
            required: true,
            mobile: 'title',
            cell: (r) => (
                <span className="num">{digits.n(stamp(r.created_at))}</span>
            ),
        },
        {
            id: 'user',
            headerKey: 'audit.user',
            cell: (r) => r.user ?? t('audit.system'),
        },
        {
            id: 'event',
            headerKey: 'audit.event',
            cell: (r) => (
                <span className="font-mono text-xs">
                    {r.event ?? r.description}
                </span>
            ),
        },
        {
            id: 'subject',
            headerKey: 'audit.subject',
            cell: (r) =>
                r.subject_type
                    ? `${r.subject_type} #${digits.n(r.subject_id ?? 0)}`
                    : '-',
        },
        {
            id: 'details',
            headerKey: 'audit.details',
            mobile: 'hidden',
            cell: (r) => (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="min-h-11 md:min-h-8"
                    onClick={() => setOpen(r)}
                >
                    <Eye aria-hidden="true" />
                    {t('audit.details')}
                </Button>
            ),
        },
    ];

    return (
        <AdminPage title={t('audit.title')}>
            <TableToolbar
                search={table.searchText}
                onSearch={table.setSearch}
                placeholderKey="audit.search"
                filters={[
                    {
                        key: 'user',
                        labelKey: 'audit.user',
                        value: filters['user'] ?? '',
                        options: users.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        })),
                        onChange: (v) => table.setFilter('user', v),
                    },
                    {
                        key: 'subject',
                        labelKey: 'audit.subject',
                        value: filters['subject'] ?? '',
                        options: subjects,
                        onChange: (v) => table.setFilter('subject', v),
                    },
                ]}
            >
                <label className="flex items-center gap-1 text-sm">
                    <span className="sr-only md:not-sr-only">
                        {t('audit.from')}
                    </span>
                    <Input
                        type="date"
                        className="min-h-11 w-auto md:min-h-9"
                        value={filters['from'] ?? ''}
                        onChange={(e) =>
                            table.setFilter('from', e.target.value)
                        }
                    />
                </label>
                <label className="flex items-center gap-1 text-sm">
                    <span className="sr-only md:not-sr-only">
                        {t('audit.to')}
                    </span>
                    <Input
                        type="date"
                        className="min-h-11 w-auto md:min-h-9"
                        value={filters['to'] ?? ''}
                        onChange={(e) => table.setFilter('to', e.target.value)}
                    />
                </label>
            </TableToolbar>
            <DataTable<Entry>
                entity="audit"
                caption={t('audit.title')}
                rows={entries}
                columns={columns}
                meta={meta}
                getRowId={(r) => String(r.id)}
                handlers={{ 'audit.details': setOpen }}
                sort={table.state.sort}
                onSort={table.toggleSort}
                onPage={table.setPage}
                loading={table.loading}
                selectable={false}
                empty={
                    <EmptyState icon={ScrollText} title={t('audit.empty')} />
                }
            />
            <Sheet
                open={open !== null}
                onOpenChange={(next) => (next ? undefined : setOpen(null))}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>{t('audit.details')}</SheetTitle>
                        <SheetDescription>
                            {open
                                ? `${open.event ?? open.description} · ${digits.n(stamp(open.created_at))}`
                                : ''}
                        </SheetDescription>
                    </SheetHeader>
                    {open ? (
                        <dl className="grid gap-3 px-4 pb-4 text-sm">
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('audit.user')}
                                </dt>
                                <dd>{open.user ?? t('audit.system')}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('audit.subject')}
                                </dt>
                                <dd>
                                    {open.subject_type
                                        ? `${open.subject_type} #${open.subject_id}`
                                        : '-'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('audit.data')}
                                </dt>
                                <dd>
                                    <pre className="mt-1 overflow-x-auto rounded-md bg-muted p-3 font-mono text-xs">
                                        {JSON.stringify(
                                            {
                                                ...open.properties,
                                                ...open.changes,
                                            },
                                            null,
                                            2,
                                        )}
                                    </pre>
                                </dd>
                            </div>
                        </dl>
                    ) : null}
                </SheetContent>
            </Sheet>
        </AdminPage>
    );
}
