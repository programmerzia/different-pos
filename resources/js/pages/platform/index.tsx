import { Head, router, useForm } from '@inertiajs/react';
import { Ban, Building2, LogIn, Play, Plus, Server } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable, type DataColumn } from '@/components/data-table/data-table';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { KpiTile } from '@/components/kpi-tile';
import { NativeSelect } from '@/components/native-select';
import { Page, PageHeader } from '@/components/page-header';
import { DateTime, localStamp } from '@/components/sales/date-time';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { formatBytes } from '@/lib/bytes';
import { cn } from '@/lib/utils';

type TenantRow = {
    id: number;
    name: string;
    slug: string;
    status: 'active' | 'suspended';
    phone: string | null;
    email: string | null;
    timezone: string;
    branches: number;
    users: number;
    last_activity_at: string | null;
    created_at: string | null;
};

type Props = {
    kpis: {
        tenants: number;
        active_tenants: number;
        suspended_tenants: number;
        active_users: number;
        gmv_30d_paisa: number;
        sales_30d: number;
        storage_bytes: number;
    };
    tenants: TenantRow[];
    health: {
        database: {
            ok: boolean;
            driver: string;
            version: string | null;
            error: string | null;
        };
        queue: {
            driver: string;
            pending: number | null;
            failed: number | null;
        };
        cache: { driver: string };
        backup: {
            last_completed_at: string | null;
            last_status: string | null;
            disks: string[];
            encrypted: boolean;
        };
        disk: { free_bytes: number | null; total_bytes: number | null };
    };
    timezones: string[];
};

type Confirm = {
    kind: 'suspend' | 'resume' | 'impersonate';
    tenant: TenantRow;
} | null;

const slugOf = (name: string) =>
    name
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');

function ProvisionDialog({
    open,
    onClose,
    timezones,
}: {
    open: boolean;
    onClose: () => void;
    timezones: string[];
}) {
    const { t } = useT();
    const form = useForm({
        name: '',
        slug: '',
        owner_name: '',
        owner_email: '',
        owner_phone: '',
        timezone: 'Asia/Dhaka',
        locale: 'en',
    });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/platform/tenants', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => (next ? undefined : onClose())}
        >
            <DialogContent className="max-h-[90vh] max-w-140 overflow-y-auto">
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Building2
                                className="size-4 text-primary"
                                aria-hidden="true"
                            />
                            {t('platform.dialog_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('platform.dialog_desc')}
                        </DialogDescription>
                    </DialogHeader>
                    {errors['form'] ? (
                        <p
                            role="alert"
                            className="rounded-md bg-danger-soft px-3 py-2 text-sm text-destructive"
                        >
                            {errors['form']}
                        </p>
                    ) : null}
                    <FormField
                        label={t('platform.field_name')}
                        error={errors['name']}
                    >
                        <Input
                            autoFocus
                            required
                            maxLength={120}
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        label={t('platform.field_slug')}
                        hint={t('platform.field_slug_hint')}
                        error={errors['slug']}
                    >
                        <Input
                            className="font-mono"
                            maxLength={60}
                            placeholder={slugOf(form.data.name)}
                            value={form.data.slug}
                            onChange={(e) =>
                                form.setData(
                                    'slug',
                                    e.target.value.toLowerCase(),
                                )
                            }
                        />
                    </FormField>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label={t('platform.field_owner_name')}
                            error={errors['owner_name']}
                        >
                            <Input
                                required
                                maxLength={120}
                                value={form.data.owner_name}
                                onChange={(e) =>
                                    form.setData('owner_name', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label={t('platform.field_owner_email')}
                            error={errors['owner_email']}
                        >
                            <Input
                                type="email"
                                required
                                maxLength={150}
                                value={form.data.owner_email}
                                onChange={(e) =>
                                    form.setData('owner_email', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label={t('platform.field_owner_phone')}
                            error={errors['owner_phone']}
                        >
                            <Input
                                inputMode="tel"
                                maxLength={20}
                                value={form.data.owner_phone}
                                onChange={(e) =>
                                    form.setData('owner_phone', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label={t('platform.field_timezone')}
                            error={errors['timezone']}
                        >
                            <NativeSelect
                                value={form.data.timezone}
                                onChange={(e) =>
                                    form.setData('timezone', e.target.value)
                                }
                            >
                                {timezones.map((tz) => (
                                    <option key={tz} value={tz}>
                                        {tz}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        <FormField
                            label={t('platform.field_locale')}
                            error={errors['locale']}
                        >
                            <NativeSelect
                                value={form.data.locale}
                                onChange={(e) =>
                                    form.setData('locale', e.target.value)
                                }
                            >
                                <option value="en">English</option>
                                <option value="bn">বাংলা</option>
                            </NativeSelect>
                        </FormField>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11"
                            onClick={onClose}
                        >
                            {t('admin.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            className="min-h-11"
                            disabled={form.processing}
                        >
                            {form.processing ? (
                                <Spinner />
                            ) : (
                                <Plus aria-hidden="true" />
                            )}
                            {t('platform.create')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

type HealthTone = 'default' | 'success' | 'danger' | 'warning';

function HealthTile({
    label,
    value,
    note,
    tone = 'default',
}: {
    label: string;
    value: string;
    note?: string;
    tone?: HealthTone;
}) {
    return (
        <div className="space-y-1 rounded-lg bg-surface-sunken p-3">
            <div className="text-muted-foreground">{label}</div>
            <div
                className={cn(
                    'num font-mono font-bold',
                    tone === 'success' && 'text-success',
                    tone === 'danger' && 'text-destructive',
                    tone === 'warning' && 'text-warning',
                    tone === 'default' && 'text-foreground',
                )}
            >
                {value}
            </div>
            {note ? (
                <div className="text-[10px] text-muted-foreground">{note}</div>
            ) : null}
        </div>
    );
}

/** SaaS Multi-Tenant Platform Console (baseline mockup screen 24): KPIs, tenant registry, infrastructure health. */
export default function PlatformIndex({
    kpis,
    tenants,
    health,
    timezones,
}: Props) {
    const { t } = useT();
    const digits = useDigits();
    const [provision, setProvision] = useState(() => {
        if (typeof window === 'undefined') {
            return false;
        }
        const wanted =
            new URLSearchParams(window.location.search).get('provision') ===
            '1';
        if (wanted) {
            window.history.replaceState(null, '', window.location.pathname);
        }

        return wanted;
    });
    const [confirm, setConfirm] = useState<Confirm>(null);

    useHotkeys('list', [
        {
            id: 'platform.provision',
            keys: 'n',
            labelKey: 'platform.provision',
            handler: () => setProvision(true),
        },
    ]);

    const act = (c: NonNullable<Confirm>) =>
        new Promise<void>((resolve) => {
            router.post(
                `/platform/tenants/${c.tenant.id}/${c.kind}`,
                {},
                { preserveScroll: true, onFinish: () => resolve() },
            );
        });

    const handlers = {
        'tenant.impersonate': (r: TenantRow) =>
            setConfirm({ kind: 'impersonate', tenant: r }),
        'tenant.suspend': (r: TenantRow) =>
            setConfirm({ kind: 'suspend', tenant: r }),
        'tenant.resume': (r: TenantRow) =>
            setConfirm({ kind: 'resume', tenant: r }),
    };

    const columns: DataColumn<TenantRow>[] = [
        {
            id: 'tenant',
            headerKey: 'platform.tenant',
            required: true,
            mobile: 'title',
            cell: (r) => (
                <div>
                    <div className="font-bold text-foreground">{r.name}</div>
                    <div className="font-mono text-[10px] text-muted-foreground">
                        ID: {digits.n(r.id)} · {r.timezone}
                    </div>
                </div>
            ),
        },
        {
            id: 'slug',
            headerKey: 'platform.slug',
            cell: (r) => (
                <span className="font-mono font-medium text-primary">
                    {r.slug}
                </span>
            ),
        },
        {
            id: 'contact',
            headerKey: 'platform.contact',
            cell: (r) => (
                <span className="font-mono text-muted-foreground">
                    {r.phone ? digits.n(r.phone) : (r.email ?? '-')}
                </span>
            ),
        },
        {
            id: 'branches',
            headerKey: 'platform.branches',
            numeric: true,
            cell: (r) => (
                <span className="num font-mono">
                    {t('platform.branches_n', { n: digits.n(r.branches) })}
                </span>
            ),
        },
        {
            id: 'users',
            headerKey: 'platform.users',
            numeric: true,
            cell: (r) => (
                <span className="num font-mono">{digits.n(r.users)}</span>
            ),
        },
        {
            id: 'activity',
            headerKey: 'platform.last_activity',
            cell: (r) =>
                r.last_activity_at ? (
                    <DateTime value={r.last_activity_at} />
                ) : (
                    <span className="text-muted-foreground">
                        {t('platform.never')}
                    </span>
                ),
        },
        {
            id: 'created',
            headerKey: 'platform.created',
            cell: (r) => <DateTime value={r.created_at} />,
        },
        {
            id: 'status',
            headerKey: 'platform.status_col',
            cell: (r) => (
                <StatusBadge
                    status={r.status}
                    tone={r.status === 'active' ? 'success' : 'warning'}
                    label={t(`platform.status.${r.status}`)}
                    shape="tag"
                />
            ),
        },
        {
            id: 'actions',
            headerKey: 'platform.actions',
            align: 'center',
            required: true,
            mobile: 'hidden',
            cell: (r) => (
                <span className="flex items-center justify-end gap-1">
                    {r.status === 'active' ? (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-6 px-2 text-[10px] [@media(pointer:coarse)]:min-h-11"
                                title={t('platform.access_title')}
                                onClick={() =>
                                    handlers['tenant.impersonate'](r)
                                }
                            >
                                <LogIn aria-hidden="true" />
                                {t('platform.access')}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-6 px-2 text-[10px] text-destructive [@media(pointer:coarse)]:min-h-11"
                                onClick={() => handlers['tenant.suspend'](r)}
                            >
                                <Ban aria-hidden="true" />
                                {t('platform.suspend')}
                            </Button>
                        </>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-6 px-2 text-[10px] [@media(pointer:coarse)]:min-h-11"
                            onClick={() => handlers['tenant.resume'](r)}
                        >
                            <Play aria-hidden="true" />
                            {t('platform.resume')}
                        </Button>
                    )}
                </span>
            ),
        },
    ];

    const lastBackup = localStamp(health.backup.last_completed_at);
    const diskKnown =
        health.disk.free_bytes !== null && health.disk.total_bytes !== null;
    const diskLow =
        diskKnown &&
        (health.disk.free_bytes ?? 0) / (health.disk.total_bytes ?? 1) < 0.1;

    return (
        <>
            <Head title={t('platform.title')} />
            <Page>
                <PageHeader
                    title={t('platform.title')}
                    subtitle={t('platform.subtitle')}
                    className="border-b pb-3"
                    badge={
                        <span className="rounded-full bg-highlight/10 px-2 py-0.5 font-mono text-[10px] font-bold text-highlight">
                            {t('platform.badge')}
                        </span>
                    }
                    actions={
                        <Button
                            type="button"
                            onClick={() => setProvision(true)}
                        >
                            <Plus aria-hidden="true" />
                            {t('platform.provision')}
                        </Button>
                    }
                />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <KpiTile
                        label={t('platform.kpi_tenants')}
                        value={t('platform.kpi_tenants_value', {
                            n: digits.n(kpis.active_tenants),
                        })}
                        footnote={t('platform.kpi_tenants_note', {
                            suspended: digits.n(kpis.suspended_tenants),
                            total: digits.n(kpis.tenants),
                        })}
                    />
                    <KpiTile
                        label={t('platform.kpi_users')}
                        value={digits.n(kpis.active_users)}
                        footnote={t('platform.kpi_users_note')}
                    />
                    <KpiTile
                        label={t('platform.kpi_gmv')}
                        value={
                            <span className="text-success">
                                {digits.money(kpis.gmv_30d_paisa)}
                            </span>
                        }
                        footnote={t('platform.kpi_gmv_note', {
                            n: digits.n(kpis.sales_30d),
                        })}
                    />
                    <KpiTile
                        label={t('platform.kpi_storage')}
                        value={formatBytes(kpis.storage_bytes, digits.n)}
                        footnote={t('platform.kpi_storage_note')}
                    />
                </div>

                <section
                    className="space-y-3 rounded-xl border bg-surface p-4 shadow-xs"
                    aria-labelledby="platform-registry"
                >
                    <div>
                        <h2
                            id="platform-registry"
                            className="text-xs font-bold tracking-wider text-foreground uppercase"
                        >
                            {t('platform.registry')}
                        </h2>
                        <span className="text-[11px] text-muted-foreground">
                            {t('platform.registry_desc')}
                        </span>
                    </div>
                    <DataTable<TenantRow>
                        entity="tenant"
                        caption={t('platform.registry')}
                        rows={tenants}
                        columns={columns}
                        meta={{
                            page: 1,
                            perPage: tenants.length || 1,
                            total: tenants.length,
                            lastPage: 1,
                        }}
                        getRowId={(r) => String(r.id)}
                        handlers={handlers}
                        sort={null}
                        onSort={() => undefined}
                        onPage={() => undefined}
                        selectable={false}
                        empty={
                            <EmptyState
                                icon={Building2}
                                title={t('platform.empty')}
                            />
                        }
                    />
                </section>

                <section
                    className="space-y-3 rounded-xl border bg-surface p-4"
                    aria-labelledby="platform-health"
                >
                    <h2
                        id="platform-health"
                        className="flex items-center gap-1.5 text-xs font-bold tracking-wider text-foreground uppercase"
                    >
                        <Server
                            className="size-3.5 text-muted-foreground"
                            aria-hidden="true"
                        />
                        {t('platform.health_title')}
                    </h2>
                    <div className="grid grid-cols-1 gap-3 text-xs md:grid-cols-3 lg:grid-cols-5">
                        <HealthTile
                            label={t('platform.health_db')}
                            value={
                                health.database.ok
                                    ? `${t('platform.health_db_ok')}${health.database.version ? ` · ${digits.n(health.database.version)}` : ''}`
                                    : t('platform.health_db_down')
                            }
                            note={
                                health.database.error ??
                                t('platform.driver', {
                                    name: health.database.driver,
                                })
                            }
                            tone={health.database.ok ? 'success' : 'danger'}
                        />
                        <HealthTile
                            label={t('platform.health_queue')}
                            value={
                                health.queue.pending === null
                                    ? t('platform.health_queue_unknown')
                                    : t('platform.health_queue_pending', {
                                          n: digits.n(health.queue.pending),
                                      })
                            }
                            note={`${t('platform.driver', { name: health.queue.driver })}${
                                health.queue.failed !== null
                                    ? ` · ${t('platform.health_queue_failed', { n: digits.n(health.queue.failed) })}`
                                    : ''
                            }`}
                            tone={health.queue.failed ? 'warning' : 'default'}
                        />
                        <HealthTile
                            label={t('platform.health_cache')}
                            value={health.cache.driver}
                            note={t('platform.driver', {
                                name: health.cache.driver,
                            })}
                        />
                        <HealthTile
                            label={t('platform.health_backup')}
                            value={
                                health.backup.last_completed_at
                                    ? digits.n(
                                          `${lastBackup.date} ${lastBackup.time}`.trim(),
                                      )
                                    : t('platform.health_backup_none')
                            }
                            note={`${t('platform.health_backup_disks', { disks: health.backup.disks.join(', ') })} · ${
                                health.backup.encrypted
                                    ? t('platform.health_backup_encrypted')
                                    : t('platform.health_backup_unencrypted')
                            }`}
                            tone={
                                health.backup.last_completed_at
                                    ? 'success'
                                    : 'warning'
                            }
                        />
                        <HealthTile
                            label={t('platform.health_disk')}
                            value={
                                diskKnown
                                    ? t('platform.health_disk_free', {
                                          free: formatBytes(
                                              health.disk.free_bytes,
                                              digits.n,
                                          ),
                                          total: formatBytes(
                                              health.disk.total_bytes,
                                              digits.n,
                                          ),
                                      })
                                    : t('platform.health_disk_unknown')
                            }
                            tone={diskLow ? 'warning' : 'default'}
                        />
                    </div>
                </section>

                <ProvisionDialog
                    open={provision}
                    onClose={() => setProvision(false)}
                    timezones={timezones}
                />
                {confirm ? (
                    <ConfirmDialog
                        open
                        onOpenChange={(open) =>
                            open ? undefined : setConfirm(null)
                        }
                        title={t(`platform.${confirm.kind}_title`, {
                            name: confirm.tenant.name,
                        })}
                        description={t(`platform.${confirm.kind}_desc`)}
                        confirmLabel={t(
                            confirm.kind === 'impersonate'
                                ? 'platform.access'
                                : `platform.${confirm.kind}`,
                        )}
                        destructive={confirm.kind === 'suspend'}
                        {...(confirm.kind === 'suspend'
                            ? { recordName: confirm.tenant.name }
                            : {})}
                        onConfirm={() => act(confirm)}
                    />
                ) : null}
            </Page>
        </>
    );
}
