import { Head, Link, router } from '@inertiajs/react';
import { RefreshCw, ShoppingBag, Wallet } from 'lucide-react';
import { useMemo, useState } from 'react';
import { BranchBars } from '@/components/dashboard/branch-bars';
import {
    deltaPercent,
    sharePercent,
} from '@/components/dashboard/chart-helpers';
import { HeatLegend, HeatMap } from '@/components/dashboard/heat-map';
import { PaymentDonut } from '@/components/dashboard/payment-donut';
import { SetupChecklist } from '@/components/dashboard/setup-checklist';
import { TrendChart } from '@/components/dashboard/trend-chart';
import {
    DashboardContext,
    PERIOD_KEYS,
    Rank,
    Widget,
    WidgetLink,
} from '@/components/dashboard/widget';
import { KpiTile } from '@/components/kpi-tile';
import { NativeSelect } from '@/components/native-select';
import { Page, PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { downloadRowsCsv } from '@/lib/rows-csv';
import { dashboard as dashboardRoute } from '@/routes';
import type { DashboardPageProps, DashboardPeriodKey } from '@/types/reports';

const selectClass = 'h-8 w-auto text-xs font-semibold';

/** `2026-11-03` → `03 Nov` (Latin digits; localise after). */
function shortDate(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
    });
}

/** Free-form delta for KPIs where a rise is bad (returns): ▲ red, ▼ green. */
function inverseDelta(
    pct: number | undefined,
    n: (v: number | string) => string,
) {
    if (pct === undefined) {
        return {};
    }

    return {
        delta: `${pct >= 0 ? '▲' : '▼'} ${n(Math.abs(pct))}%`,
        deltaTone: pct > 0 ? ('danger' as const) : ('success' as const),
    };
}

export default function Dashboard({
    dashboard: data,
    filters,
    branches,
    currentBranch,
    can,
}: DashboardPageProps) {
    const { t } = useT();
    const digits = useDigits();
    const { kpis } = data;
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    const visit = (next: Partial<typeof filters>) => {
        const merged = { ...filters, ...next };
        const query: Record<string, string> = {};
        if (merged.period !== 'today') {
            query['period'] = merged.period;
        }
        if (merged.period === 'custom') {
            query['from'] = merged.from;
            query['to'] = merged.to;
        }
        if (merged.branch !== 'current') {
            query['branch'] = merged.branch;
        }
        router.get(dashboardRoute().url, query, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const refresh = () => router.reload({ only: ['dashboard'] });
    const setPeriod = (period: DashboardPeriodKey) => visit({ period });
    const controls = useMemo(
        () => ({ period: filters.period, setPeriod, refresh }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [filters.period, filters.branch, filters.from, filters.to],
    );

    useHotkeys('list', [
        {
            id: 'dashboard.refresh',
            keys: 'r',
            labelKey: 'dashboard.refresh',
            handler: refresh,
        },
    ]);

    const n = digits.n;
    const money = digits.money;
    const kpiDelta = (k: { value: number; previous: number }) => {
        const pct = deltaPercent(k.value, k.previous);

        return pct === undefined ? {} : { deltaPercent: pct };
    };
    const margin = kpis.profit
        ? sharePercent(kpis.profit.value, kpis.profit.revenue)
        : 0;
    const exportName = (name: string) =>
        `${name}-${filters.from}_${filters.to}.csv`;
    const agingRows: {
        key: 'd30' | 'd60' | 'd90' | 'd90_plus';
        tone: string;
    }[] = [
        { key: 'd30', tone: 'bg-primary' },
        { key: 'd60', tone: 'bg-warning' },
        { key: 'd90', tone: 'bg-highlight' },
        { key: 'd90_plus', tone: 'bg-destructive' },
    ];
    const periodLabel = t(`dashboard.period.${filters.period}`);
    const empty = (
        <p className="py-4 text-center text-xs text-muted-foreground">
            {t('dashboard.nothing')}
        </p>
    );

    return (
        <DashboardContext.Provider value={controls}>
            <Head title={t('dashboard.title')} />
            <Page className="gap-4">
                <PageHeader
                    className="border-b pb-3 md:flex-row md:items-center"
                    title={t('dashboard.title')}
                    badge={
                        <span
                            className="rounded-full border border-primary bg-primary-soft px-2 py-0.5 text-[10px] font-bold text-primary"
                            title={t('dashboard.updated', {
                                time: n(
                                    new Date(
                                        data.generated_at,
                                    ).toLocaleTimeString('en-GB', {
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    }),
                                ),
                            })}
                        >
                            {t('dashboard.live')}
                        </span>
                    }
                    subtitle={t('dashboard.subtitle')}
                    actions={
                        <>
                            <label className="flex items-center gap-1">
                                <span className="text-[11px] font-medium text-muted-foreground">
                                    {t('dashboard.period_label')}
                                </span>
                                <NativeSelect
                                    className={selectClass}
                                    value={filters.period}
                                    onChange={(e) =>
                                        setPeriod(
                                            e.target
                                                .value as DashboardPeriodKey,
                                        )
                                    }
                                >
                                    {PERIOD_KEYS.map((key) => (
                                        <option key={key} value={key}>
                                            {t(`dashboard.period.${key}`)}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </label>
                            {filters.period === 'custom' ? (
                                <div className="flex items-center gap-1">
                                    <input
                                        type="date"
                                        aria-label={t('dashboard.from')}
                                        value={from}
                                        max={to}
                                        onChange={(e) =>
                                            setFrom(e.target.value)
                                        }
                                        onBlur={() =>
                                            from &&
                                            from !== filters.from &&
                                            visit({ from, to })
                                        }
                                        className="h-8 rounded-lg border bg-surface-sunken px-2 text-xs text-foreground"
                                    />
                                    <span className="text-[10px] text-muted-foreground">
                                        {t('dashboard.to')}
                                    </span>
                                    <input
                                        type="date"
                                        aria-label={t('dashboard.to')}
                                        value={to}
                                        min={from}
                                        onChange={(e) => setTo(e.target.value)}
                                        onBlur={() =>
                                            to &&
                                            to !== filters.to &&
                                            visit({ from, to })
                                        }
                                        className="h-8 rounded-lg border bg-surface-sunken px-2 text-xs text-foreground"
                                    />
                                </div>
                            ) : null}
                            <label className="flex items-center gap-1">
                                <span className="text-[11px] font-medium text-muted-foreground">
                                    {t('dashboard.branch_label')}
                                </span>
                                {can.allBranches ? (
                                    <NativeSelect
                                        className={selectClass}
                                        value={
                                            filters.branch ===
                                            String(currentBranch.id)
                                                ? 'current'
                                                : filters.branch
                                        }
                                        onChange={(e) =>
                                            visit({ branch: e.target.value })
                                        }
                                    >
                                        <option value="all">
                                            {t('dashboard.all_branches')}
                                        </option>
                                        {branches.map((b) => (
                                            <option
                                                key={b.id}
                                                value={
                                                    b.id === currentBranch.id
                                                        ? 'current'
                                                        : String(b.id)
                                                }
                                            >
                                                {`${b.name} [${b.code}]`}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                ) : (
                                    <NativeSelect
                                        className={selectClass}
                                        value="current"
                                        disabled
                                    >
                                        <option value="current">{`${currentBranch.name} [${currentBranch.code}]`}</option>
                                    </NativeSelect>
                                )}
                            </label>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={refresh}
                                title={t('dashboard.refresh_all')}
                            >
                                <RefreshCw aria-hidden="true" />
                                <span className="max-sm:sr-only">
                                    {t('dashboard.refresh')}
                                </span>
                            </Button>
                        </>
                    }
                />

                <SetupChecklist setup={data.setup} />

                <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5 2xl:grid-cols-9">
                    <KpiTile
                        label={t('dashboard.kpi.sales')}
                        value={money(kpis.sales.value)}
                        {...kpiDelta(kpis.sales)}
                        footnote={t('dashboard.kpi.sales_note', {
                            n: n(kpis.invoices.value),
                        })}
                    />
                    {kpis.profit ? (
                        <KpiTile
                            label={t('dashboard.kpi.profit')}
                            value={money(kpis.profit.value)}
                            {...kpiDelta(kpis.profit)}
                            footnote={t('dashboard.kpi.profit_note', {
                                percent: n(margin),
                            })}
                        />
                    ) : null}
                    <KpiTile
                        label={t('dashboard.kpi.invoices')}
                        value={n(kpis.invoices.value)}
                        {...kpiDelta(kpis.invoices)}
                        footnote={t('dashboard.kpi.invoices_note')}
                    />
                    <KpiTile
                        label={t('dashboard.kpi.average')}
                        value={money(kpis.average.value)}
                        {...kpiDelta(kpis.average)}
                        footnote={t('dashboard.kpi.average_note')}
                    />
                    <KpiTile
                        label={t('dashboard.kpi.dues')}
                        value={money(kpis.dues.value)}
                        delta={t('dashboard.kpi.now')}
                        deltaTone="muted"
                        footnote={t('dashboard.kpi.dues_note', {
                            n: n(kpis.dues.debtors),
                        })}
                    />
                    <KpiTile
                        label={t('dashboard.kpi.collections')}
                        value={money(kpis.collections.value)}
                        {...kpiDelta(kpis.collections)}
                        footnote={t('dashboard.kpi.collections_note')}
                    />
                    <KpiTile
                        label={t('dashboard.kpi.returns')}
                        value={money(kpis.returns.value)}
                        {...inverseDelta(
                            deltaPercent(
                                kpis.returns.value,
                                kpis.returns.previous,
                            ),
                            n,
                        )}
                        footnote={t('dashboard.kpi.returns_note', {
                            n: n(kpis.returns.count),
                        })}
                    />
                    {kpis.stock_value ? (
                        <KpiTile
                            label={t('dashboard.kpi.stock_value')}
                            value={money(kpis.stock_value.value)}
                            delta={t('dashboard.kpi.now')}
                            deltaTone="muted"
                            footnote={t('dashboard.kpi.stock_value_note', {
                                n: n(kpis.stock_value.skus),
                            })}
                        />
                    ) : null}
                    <KpiTile
                        label={t('dashboard.kpi.low_stock')}
                        value={t('dashboard.kpi.skus', {
                            n: n(kpis.low_stock.value),
                        })}
                        delta={t('dashboard.kpi.now')}
                        deltaTone={
                            kpis.low_stock.value > 0 ? 'danger' : 'muted'
                        }
                        footnote={t('dashboard.kpi.low_stock_note')}
                    />
                </div>

                <div className="grid grid-cols-1 gap-3 lg:grid-cols-3">
                    <Widget
                        className="lg:col-span-2"
                        title={t('dashboard.trend.title')}
                        subtitle={
                            data.trend.granularity === 'hour'
                                ? t('dashboard.trend.subtitle_hour')
                                : t('dashboard.trend.subtitle_day')
                        }
                        meta={
                            <span className="flex flex-wrap items-center justify-end gap-2">
                                <span className="flex items-center gap-1 text-foreground">
                                    <span className="inline-block size-2.5 rounded-full bg-primary" />
                                    <span className="num">{`${periodLabel} (${money(kpis.sales.value)})`}</span>
                                </span>
                                <span className="flex items-center gap-1">
                                    <span className="inline-block h-0.5 w-2.5 bg-muted-foreground opacity-60" />
                                    <span className="num">{`${t('dashboard.trend.previous')} (${money(kpis.sales.previous)})`}</span>
                                </span>
                            </span>
                        }
                        reportHref={`/app/reports/sales-by-day?from=${filters.from}&to=${filters.to}`}
                        onExport={() =>
                            downloadRowsCsv(
                                exportName('sales-trend'),
                                [
                                    {
                                        header: t('dashboard.trend.day'),
                                        value: (p) => p.key,
                                    },
                                    {
                                        header: t('dashboard.trend.current'),
                                        value: (p) => String(p.net / 100),
                                    },
                                    {
                                        header: t('dashboard.trend.previous'),
                                        value: (p) => String(p.previous / 100),
                                    },
                                ],
                                data.trend.points,
                            )
                        }
                    >
                        <TrendChart
                            points={data.trend.points}
                            granularity={data.trend.granularity}
                        />
                    </Widget>

                    <Widget
                        title={t('dashboard.mix.title')}
                        subtitle={t('dashboard.mix.subtitle')}
                        meta={t('dashboard.mix.total')}
                        reportHref={`/app/reports/sales-by-tender?from=${filters.from}&to=${filters.to}`}
                        onExport={() =>
                            downloadRowsCsv(
                                exportName('payment-mix'),
                                [
                                    {
                                        header: t('dashboard.mix.method'),
                                        value: (m) => m.method,
                                    },
                                    {
                                        header: t('dashboard.mix.amount'),
                                        value: (m) => String(m.amount / 100),
                                    },
                                ],
                                data.payment_mix,
                            )
                        }
                    >
                        <PaymentDonut mix={data.payment_mix} />
                    </Widget>
                </div>

                <div
                    className={`grid grid-cols-1 gap-3 ${data.branches ? 'lg:grid-cols-3' : 'lg:grid-cols-2'}`}
                >
                    <Widget
                        title={t('dashboard.heat.title')}
                        subtitle={t('dashboard.heat.subtitle', {
                            from: n(shortDate(data.heatmap.from)),
                            to: n(shortDate(data.heatmap.to)),
                        })}
                        meta={<HeatLegend />}
                        reportHref={`/app/reports/sales-by-day?from=${data.heatmap.from}&to=${data.heatmap.to}`}
                    >
                        <HeatMap cells={data.heatmap.cells} />
                    </Widget>

                    {data.branches ? (
                        <Widget
                            title={t('dashboard.branches.title')}
                            subtitle={
                                data.branches[0]?.profit !== undefined
                                    ? t('dashboard.branches.subtitle')
                                    : t('dashboard.branches.subtitle_no_profit')
                            }
                            meta={t('dashboard.branches.count', {
                                n: n(data.branches.length),
                            })}
                            reportHref={`/app/reports/sales-by-day?from=${filters.from}&to=${filters.to}&branch=all`}
                            onExport={() =>
                                downloadRowsCsv(
                                    exportName('branches'),
                                    [
                                        {
                                            header: t(
                                                'dashboard.branches.branch',
                                            ),
                                            value: (b) => `${b.code} ${b.name}`,
                                        },
                                        {
                                            header: t(
                                                'dashboard.branches.sales',
                                            ),
                                            value: (b) => String(b.sales / 100),
                                        },
                                        {
                                            header: t(
                                                'dashboard.branches.previous',
                                            ),
                                            value: (b) =>
                                                String(b.previous / 100),
                                        },
                                        ...(can.profit
                                            ? [
                                                  {
                                                      header: t(
                                                          'dashboard.branches.profit',
                                                      ),
                                                      value: (b: {
                                                          profit?: number;
                                                      }) =>
                                                          String(
                                                              (b.profit ?? 0) /
                                                                  100,
                                                          ),
                                                  },
                                              ]
                                            : []),
                                    ],
                                    data.branches ?? [],
                                )
                            }
                        >
                            <BranchBars rows={data.branches} />
                        </Widget>
                    ) : null}

                    <Widget
                        title={t('dashboard.aging.title')}
                        subtitle={t('dashboard.aging.subtitle')}
                        meta={money(data.aging.total)}
                        reportHref="/app/reports/dues-aging"
                        footer={
                            <WidgetLink href="/app/reports/dues-aging">
                                {t('dashboard.aging.link')}
                            </WidgetLink>
                        }
                    >
                        <div className="space-y-2.5 pt-1 text-xs">
                            {agingRows.map((row) => {
                                const value = data.aging[row.key];
                                const pct = sharePercent(
                                    value,
                                    data.aging.total,
                                );

                                return (
                                    <div key={row.key} className="space-y-1">
                                        <div className="flex items-center justify-between gap-2 text-[11px]">
                                            <span className="font-semibold text-foreground">
                                                {t(`reports.col.${row.key}`)}
                                            </span>
                                            <span className="num font-mono text-muted-foreground">
                                                {`${money(value)} (${n(pct)}%)`}
                                            </span>
                                        </div>
                                        <div className="h-2 w-full overflow-hidden rounded-full bg-surface-sunken">
                                            <div
                                                className={`h-full rounded-full ${row.tone}`}
                                                style={{ width: `${pct}%` }}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </Widget>
                </div>

                <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <Widget
                        title={t('dashboard.top_products.title')}
                        subtitle={t('dashboard.top_products.subtitle')}
                        meta={
                            <span className="font-bold text-primary">
                                {t('dashboard.top5')}
                            </span>
                        }
                        reportHref={`/app/reports/sales-by-product?from=${filters.from}&to=${filters.to}`}
                        footer={
                            <WidgetLink href="/app/products">
                                {t('dashboard.top_products.link')}
                            </WidgetLink>
                        }
                    >
                        {data.top_products.length === 0 ? (
                            empty
                        ) : (
                            <ul className="divide-y border-t text-xs">
                                {data.top_products.map((p, i) => (
                                    <li
                                        key={`${p.sku}-${i}`}
                                        className="flex items-center justify-between gap-2 rounded px-1 py-2 hover:bg-surface-sunken"
                                    >
                                        <span className="flex min-w-0 items-center gap-2">
                                            <Rank n={n(i + 1)} />
                                            <span className="min-w-0">
                                                <span className="block truncate font-semibold text-foreground">
                                                    {p.product}
                                                </span>
                                                <span className="block truncate font-mono text-[10px] text-primary">
                                                    {`${p.sku ?? ''} · ${t('dashboard.top_products.sold', { qty: digits.qty(p.qty) })}`}
                                                </span>
                                            </span>
                                        </span>
                                        <span className="num shrink-0 text-right font-bold text-foreground">
                                            {money(p.sales)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Widget>

                    <Widget
                        title={t('dashboard.top_customers.title')}
                        subtitle={t('dashboard.top_customers.subtitle')}
                        meta={
                            <span className="font-bold text-primary">
                                {t('dashboard.top5')}
                            </span>
                        }
                        reportHref={`/app/reports/top-customers?from=${filters.from}&to=${filters.to}`}
                        footer={
                            <WidgetLink href="/app/customers">
                                {t('dashboard.top_customers.link')}
                            </WidgetLink>
                        }
                    >
                        {data.top_customers.length === 0 ? (
                            empty
                        ) : (
                            <ul className="divide-y border-t text-xs">
                                {data.top_customers.map((c, i) => (
                                    <li
                                        key={`${c.customer_no}-${i}`}
                                        className="flex items-center justify-between gap-2 rounded px-1 py-2 hover:bg-surface-sunken"
                                    >
                                        <span className="flex min-w-0 items-center gap-2">
                                            <Rank n={n(i + 1)} />
                                            <span className="min-w-0">
                                                <span className="block truncate font-semibold text-foreground">
                                                    {c.customer}
                                                </span>
                                                <span className="block truncate font-mono text-[10px] text-muted-foreground">
                                                    {n(c.customer_no ?? '')}
                                                </span>
                                            </span>
                                        </span>
                                        <span className="shrink-0 text-right">
                                            <span className="num block font-bold text-foreground">
                                                {money(c.net)}
                                            </span>
                                            <span className="block text-[9px] text-muted-foreground">
                                                {t(
                                                    'dashboard.top_customers.visits',
                                                    { n: n(c.invoices) },
                                                )}
                                            </span>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Widget>

                    <Widget
                        title={t('dashboard.low_stock.title')}
                        subtitle={t('dashboard.low_stock.subtitle')}
                        subtitleTone="warning"
                        meta={
                            <StatusBadge
                                status="low_stock"
                                shape="tag"
                                className="font-sans font-bold"
                                label={t('dashboard.low_stock.critical', {
                                    n: n(kpis.low_stock.value),
                                })}
                            />
                        }
                        reportHref="/app/stock"
                        footer={
                            <WidgetLink href="/app/stock">
                                {t('dashboard.low_stock.link')}
                            </WidgetLink>
                        }
                    >
                        {data.low_stock.length === 0 ? (
                            empty
                        ) : (
                            <ul className="divide-y border-t text-xs">
                                {data.low_stock.map((row) => (
                                    <li
                                        key={`${row.branch}-${row.sku}`}
                                        className="flex items-center justify-between gap-2 rounded px-1 py-2 hover:bg-surface-sunken"
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate font-semibold text-foreground">
                                                {row.product}
                                            </span>
                                            <span className="block truncate font-mono text-[10px] text-destructive">
                                                {t('dashboard.low_stock.line', {
                                                    on_hand: digits.qty(
                                                        row.on_hand,
                                                    ),
                                                    min: digits.qty(
                                                        row.reorder,
                                                    ),
                                                })}
                                                {row.cost !== undefined
                                                    ? ` · ${t('dashboard.low_stock.unit', { cost: money(row.cost) })}`
                                                    : ''}
                                            </span>
                                        </span>
                                        <Link
                                            href="/app/purchases/create"
                                            className="flex h-6 shrink-0 items-center gap-1 rounded bg-primary px-2.5 text-[10px] font-bold text-primary-foreground shadow-xs hover:bg-primary/90 [@media(pointer:coarse)]:min-h-11"
                                        >
                                            <ShoppingBag
                                                className="size-3"
                                                aria-hidden="true"
                                            />
                                            {t('dashboard.low_stock.reorder')}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Widget>

                    <Widget
                        title={t('dashboard.recent.title')}
                        subtitle={t('dashboard.recent.subtitle')}
                        meta={t('dashboard.live_short')}
                        reportHref="/app/sales"
                        footer={
                            <WidgetLink href="/app/sales">
                                {t('dashboard.recent.link')}
                            </WidgetLink>
                        }
                    >
                        {data.recent_sales.length === 0 ? (
                            empty
                        ) : (
                            <ul className="divide-y border-t text-xs">
                                {data.recent_sales.map((sale) => (
                                    <li
                                        key={sale.id}
                                        className="flex items-center justify-between gap-2 rounded px-1 py-2 hover:bg-surface-sunken"
                                    >
                                        <span className="min-w-0">
                                            <Link
                                                href={`/app/sales/${sale.id}`}
                                                className="block truncate font-mono font-bold text-primary hover:underline"
                                            >
                                                {n(sale.number ?? '')}
                                            </Link>
                                            <span className="block truncate text-[10px] text-muted-foreground">
                                                {`${sale.walk_in ? t('dashboard.recent.walk_in') : (sale.customer ?? '')} · ${n(new Date(`${sale.sold_at.replace(' ', 'T')}Z`).toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }))}`}
                                            </span>
                                        </span>
                                        <span className="shrink-0 text-right">
                                            <span className="num block font-bold text-foreground">
                                                {money(sale.total)}
                                            </span>
                                            {sale.payment_status ? (
                                                <StatusBadge
                                                    status={sale.payment_status}
                                                    shape="tag"
                                                />
                                            ) : null}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Widget>

                    <Widget
                        className="md:col-span-2 xl:col-span-2"
                        title={t('dashboard.debtors.title')}
                        subtitle={t('dashboard.debtors.subtitle')}
                        subtitleTone="danger"
                        meta={
                            <span className="num font-sans font-bold text-destructive">
                                {t('dashboard.debtors.total', {
                                    amount: money(kpis.dues.value),
                                })}
                            </span>
                        }
                        reportHref="/app/reports/dues-aging"
                        footer={
                            <WidgetLink href="/app/dues">
                                {t('dashboard.debtors.link')}
                            </WidgetLink>
                        }
                    >
                        {data.debtors.length === 0 ? (
                            empty
                        ) : (
                            <ul className="divide-y border-t text-xs">
                                {data.debtors.map((d) => (
                                    <li
                                        key={d.id}
                                        className="flex items-center justify-between gap-2 rounded px-1 py-2 hover:bg-surface-sunken"
                                    >
                                        <span className="min-w-0">
                                            <span className="flex items-center gap-2 font-semibold text-foreground">
                                                <span className="truncate">
                                                    {d.customer}
                                                </span>
                                                {d.customer_no ? (
                                                    <span className="shrink-0 font-mono text-[10px] text-muted-foreground">
                                                        ({n(d.customer_no)})
                                                    </span>
                                                ) : null}
                                            </span>
                                            <span className="block truncate text-[10px] text-muted-foreground">
                                                {d.phone
                                                    ? `${t('dashboard.debtors.phone', { phone: n(d.phone) })} · `
                                                    : ''}
                                                <span className="font-medium text-destructive">
                                                    {t(
                                                        'dashboard.debtors.days',
                                                        { n: n(d.days) },
                                                    )}
                                                </span>
                                            </span>
                                        </span>
                                        <span className="flex shrink-0 items-center gap-3">
                                            <span className="num text-right font-mono font-bold text-destructive">
                                                {money(d.due)}
                                            </span>
                                            <Link
                                                href={`/app/dues/${d.id}`}
                                                className="flex h-6 items-center gap-1 rounded border border-primary bg-primary-soft px-2.5 text-[10px] font-bold text-primary transition-colors hover:bg-primary hover:text-primary-foreground [@media(pointer:coarse)]:min-h-11"
                                            >
                                                <Wallet
                                                    className="size-3"
                                                    aria-hidden="true"
                                                />
                                                {t('dashboard.debtors.collect')}
                                            </Link>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Widget>
                </div>
            </Page>
        </DashboardContext.Provider>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboardRoute() }],
};
