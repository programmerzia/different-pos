import { Head, router } from '@inertiajs/react';
import { Database, Download, HardDrive, ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { DataTable, type DataColumn } from '@/components/data-table/data-table';
import { EmptyState } from '@/components/empty-state';
import { Page, PageHeader } from '@/components/page-header';
import { DateTime } from '@/components/sales/date-time';
import { StatusBadge, type Tone } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { apiRequest } from '@/lib/api';
import { formatBytes } from '@/lib/bytes';

type BackupRow = {
    id: number;
    filename: string | null;
    status: 'queued' | 'running' | 'completed' | 'failed';
    trigger: 'manual' | 'scheduled';
    disk: string | null;
    size_bytes: number | null;
    started_at: string | null;
    finished_at: string | null;
    created_at: string | null;
    duration_seconds: number | null;
    error: string | null;
    triggered_by: string | null;
    download_url: string | null;
};

type Policy = {
    daily_at: string;
    timezone: string;
    retention_days: number;
    disks: string[];
    encrypted: boolean;
    includes_files: boolean;
};

type Props = {
    backups: BackupRow[];
    pending: number | null;
    policy: Policy;
    can_run: boolean;
};

const TONE: Record<BackupRow['status'], Tone> = {
    queued: 'warning',
    running: 'info',
    completed: 'success',
    failed: 'danger',
};

/** Backup & Disaster Recovery (baseline mockup screen 22): run now, live status, archive history, policy. */
export default function BackupIndex({
    backups,
    pending,
    policy,
    can_run,
}: Props) {
    const { t } = useT();
    const digits = useDigits();
    const [live, setLive] = useState<BackupRow | null>(null);
    const [busy, setBusy] = useState(false);
    const autoRan = useRef(false);

    const current = live ?? backups.find((b) => b.id === pending) ?? null;
    const inFlight =
        current !== null &&
        (current.status === 'queued' || current.status === 'running');

    const run = () => {
        if (!can_run || busy || inFlight) {
            return;
        }
        setBusy(true);
        router.post(
            '/app/settings/backups',
            {},
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    };

    // Poll the pending row every 2 s until it settles, then reload the list.
    useEffect(() => {
        if (pending === null) {
            setLive(null);

            return;
        }
        const controller = new AbortController();
        const timer = window.setInterval(async () => {
            try {
                const row = await apiRequest<BackupRow>(
                    `/app/api/backups/${pending}`,
                    { signal: controller.signal },
                );
                setLive(row);
                if (row.status === 'completed' || row.status === 'failed') {
                    window.clearInterval(timer);
                    router.reload({ only: ['backups', 'pending'] });
                }
            } catch {
                // Offline or signed out: the next tick tries again.
            }
        }, 2000);

        return () => {
            window.clearInterval(timer);
            controller.abort();
        };
    }, [pending]);

    // The palette command "Backup Database Now" lands here with ?run=1.
    useEffect(() => {
        if (autoRan.current || !can_run) {
            return;
        }
        if (new URLSearchParams(window.location.search).get('run') === '1') {
            autoRan.current = true;
            window.history.replaceState(null, '', window.location.pathname);
            run();
        }
        // Runs once on mount by design.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useHotkeys('list', [
        {
            id: 'backup.run',
            keys: 'b',
            labelKey: 'backup.run_now',
            handler: run,
        },
    ]);

    const duration = (s: number | null) =>
        s === null
            ? '-'
            : s >= 60
              ? t('backup.minutes', { n: digits.n(Math.round(s / 60)) })
              : t('backup.seconds', { n: digits.n(s) });

    const columns: DataColumn<BackupRow>[] = [
        {
            id: 'filename',
            headerKey: 'backup.filename',
            required: true,
            mobile: 'title',
            cell: (r) => (
                <span className="font-mono font-medium text-primary">
                    {r.filename ??
                        (r.error ? (
                            <span className="text-destructive">{r.error}</span>
                        ) : (
                            t(`backup.status.${r.status}`)
                        ))}
                </span>
            ),
        },
        {
            id: 'size',
            headerKey: 'backup.size',
            numeric: true,
            cell: (r) => (
                <span className="num font-mono">
                    {formatBytes(r.size_bytes, digits.n)}
                </span>
            ),
        },
        {
            id: 'when',
            headerKey: 'backup.timestamp',
            cell: (r) => <DateTime value={r.finished_at ?? r.created_at} />,
        },
        {
            id: 'destination',
            headerKey: 'backup.destination',
            cell: (r) => (
                <span className="text-muted-foreground">
                    {r.disk ?? '-'} ·{' '}
                    {r.trigger === 'scheduled'
                        ? t('backup.scheduled')
                        : t('backup.manual')}
                </span>
            ),
        },
        {
            id: 'status',
            headerKey: 'backup.status_col',
            cell: (r) => (
                <StatusBadge
                    status={r.status}
                    tone={TONE[r.status]}
                    label={t(`backup.status.${r.status}`)}
                />
            ),
        },
        {
            id: 'duration',
            headerKey: 'backup.duration',
            cell: (r) => (
                <span className="num">{duration(r.duration_seconds)}</span>
            ),
        },
        {
            id: 'by',
            headerKey: 'backup.triggered_by',
            cell: (r) => r.triggered_by ?? t('backup.system'),
        },
        {
            id: 'actions',
            headerKey: 'backup.actions',
            align: 'center',
            required: true,
            mobile: 'hidden',
            cell: (r) =>
                r.download_url ? (
                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="h-6 px-2 text-[10px] [@media(pointer:coarse)]:min-h-11"
                    >
                        <a href={r.download_url}>
                            <Download aria-hidden="true" />
                            {t('backup.download')}
                        </a>
                    </Button>
                ) : r.status === 'completed' && r.disk !== 'local' ? (
                    <span className="text-[10px] text-muted-foreground">
                        {t('backup.cloud_only')}
                    </span>
                ) : null,
        },
    ];

    const latest = backups.find((b) => b.download_url !== null) ?? null;

    return (
        <>
            <Head title={t('backup.title')} />
            <Page className="mx-auto w-full max-w-4xl">
                <PageHeader
                    title={t('backup.title')}
                    subtitle={t('backup.subtitle')}
                    className="border-b pb-3"
                    actions={
                        can_run ? (
                            <Button
                                type="button"
                                onClick={run}
                                disabled={busy || inFlight}
                            >
                                {inFlight || busy ? (
                                    <Spinner />
                                ) : (
                                    <Database aria-hidden="true" />
                                )}
                                {inFlight
                                    ? t('backup.archiving')
                                    : t('backup.run_now')}
                            </Button>
                        ) : null
                    }
                />

                {inFlight ? (
                    <output className="space-y-2 rounded-xl border bg-surface p-4">
                        <div className="flex justify-between text-xs font-semibold">
                            <span>{t('backup.progress_title')}</span>
                            <span className="font-mono text-muted-foreground">
                                {t(`backup.status.${current.status}`)}
                            </span>
                        </div>
                        <div className="h-2 w-full overflow-hidden rounded-full bg-surface-sunken">
                            <div className="h-full w-1/3 animate-pulse rounded-full bg-primary" />
                        </div>
                        <p className="text-[11px] text-muted-foreground">
                            {t('backup.progress_hint')}
                        </p>
                    </output>
                ) : null}

                <div className="grid gap-3 md:grid-cols-2">
                    <section
                        className="space-y-1.5 rounded-xl border bg-surface p-4 text-xs"
                        aria-labelledby="backup-policy"
                    >
                        <h2
                            id="backup-policy"
                            className="flex items-center gap-1.5 text-xs font-bold tracking-wider uppercase"
                        >
                            <HardDrive
                                className="size-3.5 text-muted-foreground"
                                aria-hidden="true"
                            />
                            {t('backup.policy_title')}
                        </h2>
                        <ul className="space-y-1 text-muted-foreground">
                            <li>
                                {t('backup.policy_schedule', {
                                    time: digits.n(policy.daily_at),
                                    tz: policy.timezone,
                                })}
                            </li>
                            <li>
                                {t('backup.policy_retention', {
                                    days: digits.n(policy.retention_days),
                                })}
                            </li>
                            <li>
                                {t('backup.policy_disks', {
                                    disks: policy.disks.join(', '),
                                })}
                            </li>
                            <li>
                                {policy.includes_files
                                    ? t('backup.policy_files')
                                    : t('backup.policy_db_only')}
                            </li>
                            <li
                                className={
                                    policy.encrypted
                                        ? 'text-success'
                                        : 'text-warning'
                                }
                            >
                                <ShieldCheck
                                    className="mr-1 inline size-3.5"
                                    aria-hidden="true"
                                />
                                {policy.encrypted
                                    ? t('backup.policy_encrypted')
                                    : t('backup.policy_unencrypted')}
                            </li>
                        </ul>
                        <p className="pt-1 text-[11px] text-muted-foreground">
                            {t('backup.scope_note')}
                        </p>
                    </section>
                    <section
                        className="flex items-center justify-between gap-3 rounded-xl border bg-surface p-4"
                        aria-labelledby="backup-export"
                    >
                        <div>
                            <h2
                                id="backup-export"
                                className="text-xs font-bold"
                            >
                                {t('backup.export_title')}
                            </h2>
                            <p className="text-[11px] text-muted-foreground">
                                {t('backup.export_desc')}
                            </p>
                        </div>
                        {can_run ? (
                            latest?.download_url ? (
                                <Button
                                    asChild
                                    variant="secondary"
                                    size="sm"
                                    className="shrink-0 [@media(pointer:coarse)]:min-h-11"
                                >
                                    <a href={latest.download_url}>
                                        <Download aria-hidden="true" />
                                        {t('backup.export_latest')}
                                    </a>
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    size="sm"
                                    className="shrink-0 [@media(pointer:coarse)]:min-h-11"
                                    onClick={run}
                                    disabled={busy || inFlight}
                                >
                                    <Database aria-hidden="true" />
                                    {t('backup.run_now')}
                                </Button>
                            )
                        ) : null}
                    </section>
                </div>

                <div className="overflow-hidden rounded-xl border bg-surface shadow-xs">
                    <div className="border-b bg-surface-sunken p-3 text-xs font-bold">
                        {t('backup.history')}
                    </div>
                    <DataTable<BackupRow>
                        entity="backup"
                        caption={t('backup.history')}
                        rows={backups}
                        columns={columns}
                        meta={{
                            page: 1,
                            perPage: backups.length || 1,
                            total: backups.length,
                            lastPage: 1,
                        }}
                        getRowId={(r) => String(r.id)}
                        handlers={{
                            'backup.download': (r) => {
                                if (r.download_url) {
                                    window.location.assign(r.download_url);
                                }
                            },
                        }}
                        sort={null}
                        onSort={() => undefined}
                        onPage={() => undefined}
                        selectable={false}
                        empty={
                            <EmptyState
                                icon={Database}
                                title={t('backup.empty')}
                            />
                        }
                    />
                </div>
            </Page>
        </>
    );
}
