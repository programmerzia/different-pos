import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarClock,
    Database,
    FileText,
    MessageSquare,
    RotateCw,
    Send,
    Settings,
} from 'lucide-react';
import { type ReactNode, useEffect, useState } from 'react';
import { DataTable, type DataColumn } from '@/components/data-table/data-table';
import { useServerTable } from '@/components/data-table/server-table';
import { EmptyState } from '@/components/empty-state';
import { Composer, type ComposerConfig } from '@/components/messaging/composer';
import { smsCost, smsSegments } from '@/components/messaging/sms-text';
import { Page, PageHeader } from '@/components/page-header';
import { StatusBadge, type Tone } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { actions, type AppAction } from '@/command/actions';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';
import type { MessageRow } from '@/types/messaging';
import type { TableMetaProp } from '@/types/customers';

type Props = {
    rows: MessageRow[];
    meta: TableMetaProp;
    canResend: boolean;
    costPerSegment: number;
    composer: ComposerConfig | null;
};

type Tab = 'composer' | 'templates' | 'log';

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

const tabClass =
    'flex h-7 items-center gap-1.5 rounded-lg px-3 transition-colors [@media(pointer:coarse)]:min-h-11';
const tabOn = 'bg-surface font-bold text-primary shadow-xs';
const tabOff = 'text-muted-foreground hover:text-foreground';

function initialTab(url: string, canSend: boolean): Tab {
    const params = new URLSearchParams(
        url.includes('?') ? url.slice(url.indexOf('?')) : '',
    );
    const asked = params.get('tab');
    if (
        asked === 'log' ||
        (canSend && (asked === 'composer' || asked === 'templates'))
    ) {
        return asked;
    }
    const listing = [...params.keys()].some(
        (k) =>
            k === 'q' || k === 'page' || k === 'sort' || k.startsWith('filter'),
    );

    return canSend && !listing ? 'composer' : 'log';
}

/**
 * WhatsApp & SMS hub (baseline mockup screen 10): Composer & Dispatch, Templates Library, Scheduled
 * (later phase), Message Log and a link to the SMS settings. Sending needs settings.manage; the log
 * needs customers.view (02_AppFlow §2 row 55a).
 */
export default function MessagesIndex({
    rows,
    meta,
    canResend,
    costPerSegment,
    composer,
}: Props) {
    const { t } = useT();
    const digits = useDigits();
    const table = useServerTable({ only: ['rows', 'meta'] });
    const [tab, setTab] = useState<Tab>(() =>
        initialTab(
            typeof window === 'undefined' ? '' : window.location.search,
            composer !== null,
        ),
    );
    const [draft, setDraft] = useState({ body: '', seq: 0 });

    useEffect(
        () => actions.register(resendAction as unknown as AppAction<never>),
        [],
    );

    const tabs: {
        id: Tab | 'scheduled' | 'settings';
        label: string;
        icon: typeof Send;
        show: boolean;
    }[] = [
        {
            id: 'composer',
            label: t('sms.hub.tab_composer'),
            icon: Send,
            show: composer !== null,
        },
        {
            id: 'templates',
            label: t('sms.hub.tab_templates'),
            icon: FileText,
            show: composer !== null,
        },
        {
            id: 'scheduled',
            label: t('sms.hub.tab_scheduled'),
            icon: CalendarClock,
            show: true,
        },
        { id: 'log', label: t('sms.hub.tab_log'), icon: Database, show: true },
        {
            id: 'settings',
            label: t('sms.hub.tab_settings'),
            icon: Settings,
            show: composer !== null,
        },
    ];
    const live = tabs.filter(
        (x) =>
            x.show &&
            (x.id === 'composer' || x.id === 'templates' || x.id === 'log'),
    );

    useHotkeys(
        'list',
        live.map((x, i) => ({
            id: `messages.tab.${x.id}`,
            keys: `alt+${i + 1}`,
            labelKey: `sms.hub.tab_${x.id}`,
            handler: (event: KeyboardEvent) => {
                event.preventDefault();
                setTab(x.id as Tab);
            },
        })),
    );

    const resend = (row: MessageRow) =>
        router.post(
            `/app/messages/${row.id}/resend`,
            {},
            { preserveScroll: true },
        );
    const handlers = canResend ? { 'message.resend': resend } : {};

    const columns: DataColumn<MessageRow>[] = [
        {
            id: 'created_at',
            headerKey: 'sms.log.col.time',
            sortKey: 'created_at',
            cell: (r) => (
                <span className="num font-mono text-muted-foreground">
                    {r.created_at
                        ? digits.n(
                              r.created_at.slice(0, 16).replace('T', ' · '),
                          )
                        : '-'}
                </span>
            ),
        },
        {
            id: 'customer',
            headerKey: 'sms.log.col.customer',
            required: true,
            mobile: 'title',
            cell: (r) => (
                <span className="font-semibold">{r.customer ?? '-'}</span>
            ),
        },
        {
            id: 'phone',
            headerKey: 'sms.log.col.phone',
            cell: (r) => (
                <span className="num font-mono text-muted-foreground">
                    {digits.n(r.phone)}
                </span>
            ),
        },
        {
            id: 'channel',
            headerKey: 'sms.log.col.channel',
            cell: (r) => (
                <span className="rounded-full bg-primary-soft px-2 py-0.5 text-[10px] font-bold text-primary">
                    {t('sms.hub.sms')} · {t(`sms.event.${r.event}`)}
                </span>
            ),
        },
        {
            id: 'body',
            headerKey: 'sms.log.col.message',
            cell: (r) => (
                <span
                    className="line-clamp-1 max-w-xs text-foreground"
                    title={r.body}
                >
                    {r.body}
                </span>
            ),
        },
        {
            id: 'cost',
            headerKey: 'sms.log.col.cost',
            numeric: true,
            cell: (r) => (
                <span className="num font-semibold text-foreground">
                    {digits.money(
                        smsCost(
                            smsSegments(r.body).segments,
                            1,
                            costPerSegment,
                        ),
                    )}
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
                        <span className="text-[10px] text-destructive">
                            {r.error}
                        </span>
                    ) : null}
                </div>
            ),
        },
        {
            id: 'actions',
            headerKey: 'admin.actions',
            required: true,
            cell: (r) =>
                canResend && r.status === 'failed' ? (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-6 bg-surface-sunken px-2 text-[10px] font-semibold text-primary [@media(pointer:coarse)]:min-h-11"
                        onClick={(e) => {
                            e.stopPropagation();
                            resend(r);
                        }}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                                e.stopPropagation();
                            }
                        }}
                    >
                        {t('sms.log.resend')}
                    </Button>
                ) : null,
        },
    ];

    let body: ReactNode = null;
    if (tab === 'composer') {
        body = null;
    } else if (tab === 'templates' && composer) {
        body = (
            <section aria-labelledby="tpl-title" className="space-y-4">
                <div>
                    <h2
                        id="tpl-title"
                        className="text-xs font-bold tracking-wider text-foreground uppercase"
                    >
                        {t('sms.hub.templates_title')}
                    </h2>
                    <p className="text-[11px] text-muted-foreground">
                        {t('sms.hub.templates_hint')}
                    </p>
                </div>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {composer.templates.map((tpl) => (
                        <article
                            key={tpl.key}
                            className="flex flex-col justify-between space-y-3 rounded-xl border bg-surface p-4 shadow-xs"
                        >
                            <div>
                                <div className="flex items-center justify-between">
                                    <span
                                        className={cn(
                                            'rounded-full px-2 py-0.5 text-[10px] font-bold',
                                            tpl.enabled
                                                ? 'bg-success-soft text-success'
                                                : 'bg-surface-sunken text-muted-foreground',
                                        )}
                                    >
                                        {tpl.enabled
                                            ? t('sms.hub.tpl_auto_on')
                                            : t('sms.hub.tpl_auto_off')}
                                    </span>
                                    <span className="font-mono text-[10px] font-semibold text-primary">
                                        {tpl.key}
                                    </span>
                                </div>
                                <h3 className="mt-2 text-xs font-bold text-foreground">
                                    {t(`sms.template.${tpl.key}`)}
                                </h3>
                                <p className="mt-2 line-clamp-3 rounded-lg border bg-surface-sunken p-2 font-mono text-[11px] leading-relaxed text-foreground">
                                    {tpl.body}
                                </p>
                            </div>
                            <div className="flex gap-2 border-t pt-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    className="h-7 flex-1 [@media(pointer:coarse)]:min-h-11"
                                    onClick={() => {
                                        setDraft((d) => ({
                                            body: tpl.body,
                                            seq: d.seq + 1,
                                        }));
                                        setTab('composer');
                                    }}
                                >
                                    <Send aria-hidden="true" />
                                    {t('sms.hub.use_template')}
                                </Button>
                            </div>
                        </article>
                    ))}
                </div>
            </section>
        );
    } else {
        body = (
            <div className="flex min-h-0 flex-1 flex-col gap-3">
                <TableToolbar
                    search={table.searchText}
                    onSearch={table.setSearch}
                    placeholderKey="sms.log.search"
                    chips={[
                        {
                            key: 'status',
                            label: t('sms.log.col.status'),
                            value: table.state.filters['status'] ?? '',
                            options: [
                                { value: '', label: t('sms.log.all_statuses') },
                                ...(['queued', 'sent', 'failed'] as const).map(
                                    (s) => ({
                                        value: s,
                                        label: t(`sms.status.${s}`),
                                    }),
                                ),
                            ],
                            onChange: (v) => table.setFilter('status', v),
                        },
                    ]}
                />
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
                    onPerPage={table.setPerPage}
                    loading={table.loading}
                    chooser="none"
                    fill
                    empty={
                        <EmptyState
                            icon={MessageSquare}
                            title={t('sms.log.empty')}
                            description={t('sms.log.empty_hint')}
                        />
                    }
                />
            </div>
        );
    }

    return (
        <>
            <Head title={t('sms.hub.title')} />
            <Page fill={tab === 'log'}>
                <PageHeader
                    title={t('sms.hub.title')}
                    subtitle={t('sms.hub.subtitle')}
                    className="border-b pb-3"
                    actions={
                        <>
                            <span className="flex items-center gap-1.5 rounded-full bg-surface-sunken px-2.5 py-1 text-xs font-semibold text-muted-foreground">
                                <span
                                    className="size-2 rounded-full bg-muted-foreground"
                                    aria-hidden="true"
                                />
                                {t('sms.hub.whatsapp_soon')}
                            </span>
                            {composer ? (
                                <span
                                    className={cn(
                                        'flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold',
                                        composer.configured
                                            ? 'bg-primary-soft text-primary'
                                            : 'bg-warning-soft text-warning',
                                    )}
                                >
                                    {t('sms.hub.gateway', {
                                        driver: t(
                                            `sms.driver.${composer.driver}`,
                                        ),
                                    })}
                                </span>
                            ) : null}
                        </>
                    }
                />

                <nav
                    aria-label={t('sms.hub.tabs')}
                    className="flex w-fit max-w-full shrink-0 flex-wrap items-center gap-1 rounded-xl border bg-surface-sunken p-1 text-xs font-semibold"
                >
                    {tabs
                        .filter((x) => x.show)
                        .map((x) => {
                            const Icon = x.icon;
                            const inner = (
                                <>
                                    <Icon
                                        className="size-3.25"
                                        aria-hidden="true"
                                    />
                                    <span>{x.label}</span>
                                </>
                            );
                            if (x.id === 'settings') {
                                return (
                                    <Link
                                        key={x.id}
                                        href="/app/settings/sms"
                                        className={cn(tabClass, tabOff)}
                                    >
                                        {inner}
                                    </Link>
                                );
                            }
                            if (x.id === 'scheduled') {
                                return (
                                    <button
                                        key={x.id}
                                        type="button"
                                        aria-disabled="true"
                                        title={t('sms.hub.next_phase')}
                                        className={cn(
                                            tabClass,
                                            tabOff,
                                            'cursor-not-allowed opacity-60',
                                        )}
                                    >
                                        {inner}
                                        <span className="rounded bg-surface px-1 text-[9px] font-bold uppercase">
                                            {t('sms.hub.next_phase')}
                                        </span>
                                    </button>
                                );
                            }

                            return (
                                <button
                                    key={x.id}
                                    type="button"
                                    aria-pressed={tab === x.id}
                                    className={cn(
                                        tabClass,
                                        tab === x.id ? tabOn : tabOff,
                                    )}
                                    onClick={() => setTab(x.id as Tab)}
                                >
                                    {inner}
                                </button>
                            );
                        })}
                </nav>

                {composer ? (
                    // Stays mounted so a half-written message survives a look at the log.
                    <div className={tab === 'composer' ? 'contents' : 'hidden'}>
                        <Composer
                            config={composer}
                            active={tab === 'composer'}
                            draft={draft}
                        />
                    </div>
                ) : null}
                {body}
            </Page>
        </>
    );
}
