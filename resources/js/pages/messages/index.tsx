import { Head, router } from '@inertiajs/react';
import { MessageSquare, RotateCw, Search } from 'lucide-react';
import { useEffect, useRef } from 'react';
import {
    DataTable,
    type DataColumn,
} from '@/components/data-table/data-table';
import { useServerTable } from '@/components/data-table/server-table';
import { EmptyState } from '@/components/empty-state';
import { StatusBadge, type Tone } from '@/components/status-badge';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { actions, type AppAction } from '@/command/actions';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import type { MessageRow } from '@/types/messaging';
import type { TableMetaProp } from '@/types/customers';

type Props = { rows: MessageRow[]; meta: TableMetaProp; canResend: boolean };

const ALL = '__all';
const TONE: Record<MessageRow['status'], Tone> = {
    queued: 'info',
    sent: 'success',
    failed: 'danger',
};

const resendAction: AppAction<MessageRow> = {
    id: 'message.resend',
    entity: 'message',
    labelKey: 'sms.log.resend',
    icon: RotateCw,
    keys: 'r',
    permission: 'settings.manage',
    requires: 'handler',
    visible: (row) => row.status === 'failed',
    run: ({ row, handlers }) => handlers?.['message.resend']?.(row),
};

export default function MessagesIndex({ rows, meta, canResend }: Props) {
    const { t } = useT();
    const digits = useDigits();
    const table = useServerTable({ only: ['rows', 'meta'] });
    const searchRef = useRef<HTMLInputElement>(null);

    useEffect(() => actions.register(resendAction as unknown as AppAction<never>), []);
    useEffect(() => {
        if (searchRef.current && document.activeElement !== searchRef.current) {
            searchRef.current.value = table.state.q;
        }
    }, [table.state.q]);
    useHotkeys('list', [
        {
            id: 'list.search',
            keys: '/',
            labelKey: 'hotkeys.search',
            handler: (event) => {
                event.preventDefault();
                searchRef.current?.focus();
            },
        },
    ]);

    const resend = (row: MessageRow) =>
        router.post(`/app/messages/${row.id}/resend`, {}, { preserveScroll: true });
    const handlers = canResend ? { 'message.resend': resend } : {};

    const columns: DataColumn<MessageRow>[] = [
        {
            id: 'created_at',
            headerKey: 'sms.log.col.time',
            sortKey: 'created_at',
            cell: (r) => (
                <span className="num">
                    {r.created_at
                        ? digits.n(r.created_at.slice(0, 16).replace('T', ' '))
                        : '-'}
                </span>
            ),
        },
        {
            id: 'customer',
            headerKey: 'sms.log.col.customer',
            required: true,
            mobile: 'title',
            cell: (r) => r.customer ?? '-',
        },
        {
            id: 'phone',
            headerKey: 'sms.log.col.phone',
            cell: (r) => <span className="num">{digits.n(r.phone)}</span>,
        },
        {
            id: 'event',
            headerKey: 'sms.log.col.event',
            sortKey: 'event',
            cell: (r) => t(`sms.event.${r.event}`),
        },
        {
            id: 'body',
            headerKey: 'sms.log.col.message',
            cell: (r) => (
                <span className="line-clamp-2 max-w-md" title={r.body}>
                    {r.body}
                </span>
            ),
        },
        {
            id: 'status',
            headerKey: 'sms.log.col.status',
            sortKey: 'status',
            cell: (r) => (
                <div className="flex flex-col gap-1">
                    <StatusBadge
                        status={r.status}
                        tone={TONE[r.status]}
                        label={t(`sms.status.${r.status}`)}
                    />
                    {r.error ? (
                        <span className="text-xs text-destructive">
                            {r.error}
                        </span>
                    ) : null}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title={t('sms.log.title')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <h1 className="text-2xl font-bold">{t('sms.log.title')}</h1>
                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative w-full max-w-sm">
                        <Search
                            className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            ref={searchRef}
                            type="search"
                            defaultValue={table.state.q}
                            placeholder={t('sms.log.search')}
                            aria-label={t('sms.log.search')}
                            className="min-h-11 pl-9 md:min-h-9"
                            onChange={(e) => table.setSearch(e.target.value)}
                        />
                    </div>
                    <Select
                        value={table.state.filters['status'] ?? ALL}
                        onValueChange={(v) =>
                            table.setFilter('status', v === ALL ? '' : v)
                        }
                    >
                        <SelectTrigger
                            className="min-h-11 w-44 md:min-h-9"
                            aria-label={t('sms.log.col.status')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                {t('sms.log.all_statuses')}
                            </SelectItem>
                            {(['queued', 'sent', 'failed'] as const).map(
                                (s) => (
                                    <SelectItem key={s} value={s}>
                                        {t(`sms.status.${s}`)}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                </div>
                <DataTable<MessageRow>
                    entity="message"
                    caption={t('sms.log.title')}
                    rows={rows}
                    columns={columns}
                    meta={meta}
                    getRowId={(r) => String(r.id)}
                    handlers={handlers}
                    selectable={false}
                    sort={table.state.sort}
                    onSort={table.toggleSort}
                    onPage={table.setPage}
                    loading={table.loading}
                    empty={
                        <EmptyState
                            icon={MessageSquare}
                            title={t('sms.log.empty')}
                            description={t('sms.log.empty_hint')}
                        />
                    }
                />
            </div>
        </>
    );
}
