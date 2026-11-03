import { Eye, ScrollText } from 'lucide-react';
import { useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    ColumnChooser,
    DataTable,
    useColumnVisibility,
    type DataColumn,
} from '@/components/data-table/data-table';
import { useServerTable } from '@/components/data-table/server-table';
import { EmptyState } from '@/components/empty-state';
import { Page, PageHeader } from '@/components/page-header';
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
import { DateTime, localStamp } from '@/components/sales/date-time';
import { auditEventLabel } from '@/lib/labels';

type Entry = {
    id: number;
    created_at: string | null;
    event: string | null;
    description: string;
    user: string | null;
    ip: string | null;
    subject_type: string | null;
    subject_id: number | null;
    /** "Role · Manager"; "Role #9" when the subject has no name any more. */
    subject_label: string | null;
    properties: Record<string, unknown>;
    changes: Record<string, unknown>;
};

type Props = {
    events: string[];
    entries: Entry[];
    meta: { page: number; perPage: number; total: number; lastPage: number };
    users: { id: number; name: string }[];
    subjects: { value: string; label: string }[];
};

const stamp = (iso: string | null) => {
    const { date, time } = localStamp(iso);

    return date === '' ? '-' : `${date} ${time}`.trim();
};

export default function Audit({
    entries,
    meta,
    users,
    subjects,
    events,
}: Props) {
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
            cell: (r) => <DateTime value={r.created_at} />,
        },
        {
            id: 'user',
            headerKey: 'audit.user',
            cell: (r) => (
                <span className="font-semibold text-foreground">
                    {r.user ?? t('audit.system')}
                </span>
            ),
        },
        {
            id: 'event',
            headerKey: 'audit.event',
            cell: (r) => (
                <span
                    title={r.event ?? undefined}
                    className="rounded-full border bg-surface-sunken px-2 py-0.5 text-[10px] font-bold"
                >
                    {r.event ? auditEventLabel(t, r.event) : r.description}
                </span>
            ),
        },
        {
            id: 'ip',
            headerKey: 'audit.terminal',
            cell: (r) => (
                <span className="num font-mono text-muted-foreground">
                    {r.ip ? digits.n(r.ip) : '-'}
                </span>
            ),
        },
        {
            id: 'details',
            headerKey: 'audit.details_col',
            mobile: 'hidden',
            required: true,
            cell: (r) => (
                <span className="flex items-center justify-between gap-2">
                    <span className="text-foreground">
                        {r.subject_label ?? r.description}
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-6 px-2 text-[11px] [@media(pointer:coarse)]:min-h-11"
                        onClick={() => setOpen(r)}
                    >
                        <Eye aria-hidden="true" />
                        {t('audit.details')}
                    </Button>
                </span>
            ),
        },
    ];
    const columnState = useColumnVisibility(columns);

    return (
        <>
            <Head title={t('audit.title')} />
            <Page fill>
                <PageHeader
                    title={t('audit.header')}
                    subtitle={t('audit.subtitle')}
                    actions={
                        <ColumnChooser
                            columns={columns}
                            visibility={columnState.visibility}
                            onChange={columnState.setVisibility}
                        />
                    }
                />
                <TableToolbar
                    search={table.searchText}
                    onSearch={table.setSearch}
                    placeholderKey="audit.search"
                    filters={[
                        {
                            key: 'event',
                            labelKey: 'audit.all_actions',
                            value: filters['event'] ?? '',
                            options: events.map((e) => ({
                                value: e,
                                label: auditEventLabel(t, e),
                            })),
                            onChange: (v) => table.setFilter('event', v),
                        },
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
                    <label className="flex items-center gap-1 text-[11px] font-medium text-muted-foreground">
                        <span className="sr-only md:not-sr-only">
                            {t('audit.from')}
                        </span>
                        <Input
                            type="date"
                            className="h-8 w-auto text-xs [@media(pointer:coarse)]:min-h-11"
                            value={filters['from'] ?? ''}
                            onChange={(e) =>
                                table.setFilter('from', e.target.value)
                            }
                        />
                    </label>
                    <label className="flex items-center gap-1 text-[11px] font-medium text-muted-foreground">
                        <span className="sr-only md:not-sr-only">
                            {t('audit.to')}
                        </span>
                        <Input
                            type="date"
                            className="h-8 w-auto text-xs [@media(pointer:coarse)]:min-h-11"
                            value={filters['to'] ?? ''}
                            onChange={(e) =>
                                table.setFilter('to', e.target.value)
                            }
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
                    chooser="none"
                    visibility={columnState.visibility}
                    onVisibilityChange={columnState.setVisibility}
                    onPerPage={table.setPerPage}
                    fill
                    sort={table.state.sort}
                    onSort={table.toggleSort}
                    onPage={table.setPage}
                    loading={table.loading}
                    selectable={false}
                    empty={
                        <EmptyState
                            icon={ScrollText}
                            title={t('audit.empty')}
                        />
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
                                    ? `${open.event ? auditEventLabel(t, open.event) : open.description} · ${digits.n(stamp(open.created_at))}`
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
                                    <dd>{open.subject_label ?? '-'}</dd>
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
            </Page>
        </>
    );
}
