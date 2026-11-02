import { Head, Link } from '@inertiajs/react';
import { LayoutGrid } from 'lucide-react';
import type { ReactNode } from 'react';
import { TrendChart } from '@/components/dashboard/trend-chart';
import { EmptyState } from '@/components/empty-state';
import { KpiTile } from '@/components/kpi-tile';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { dashboard } from '@/routes';
import type { DashboardKpi, DashboardProps } from '@/types/reports';

/** Whole-percent change; undefined when there is nothing to compare with. */
function delta(now: number, before: number): { deltaPercent?: number } {
    return before > 0
        ? { deltaPercent: Math.round(((now - before) / before) * 100) }
        : {};
}

function Panel({
    title,
    children,
    href,
}: {
    title: string;
    children: ReactNode;
    href?: string;
}) {
    const { t } = useT();

    return (
        <section
            className="flex flex-col gap-3 rounded-lg border bg-card text-card-foreground shadow-xs"
            style={{ padding: 'var(--card-pad)' }}
        >
            <div className="flex items-baseline justify-between gap-2">
                <h2 className="font-semibold">{title}</h2>
                {href ? (
                    <Link
                        href={href}
                        className="text-sm text-primary hover:underline"
                    >
                        {t('dashboard.open_report')}
                    </Link>
                ) : null}
            </div>
            {children}
        </section>
    );
}

export default function Dashboard({
    dashboard: data,
}: {
    dashboard: DashboardProps;
}) {
    const { t } = useT();
    const digits = useDigits();
    const { kpis } = data;

    const kpi = (
        key: 'sales' | 'invoices' | 'average' | 'collections',
        value: DashboardKpi,
        format: (n: number) => string,
    ) => (
        <KpiTile
            label={t(`dashboard.kpi.${key}`)}
            value={format(value.today)}
            {...delta(value.today, value.yesterday)}
        />
    );

    const empty = (
        <p className="text-sm text-muted-foreground">
            {t('dashboard.nothing')}
        </p>
    );
    const agingRows: [string, number][] = [
        ['d30', data.aging.d30],
        ['d60', data.aging.d60],
        ['d90', data.aging.d90],
        ['d90_plus', data.aging.d90_plus],
    ];

    return (
        <>
            <Head title={t('dashboard.title')} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <h1 className="text-2xl font-bold">{t('dashboard.title')}</h1>

                <p className="text-sm text-muted-foreground">
                    {t('dashboard.today')} - {t('dashboard.vs_yesterday')}
                </p>
                <div className="grid grid-cols-2 gap-3 xl:grid-cols-6">
                    {kpi('sales', kpis.sales, digits.money)}
                    {kpi('invoices', kpis.invoices, digits.n)}
                    {kpi('average', kpis.average, digits.money)}
                    {kpi('collections', kpis.collections, digits.money)}
                    <KpiTile
                        label={t('dashboard.kpi.open_dues')}
                        value={digits.money(kpis.open_dues)}
                    />
                    <KpiTile
                        label={t('dashboard.kpi.low_stock')}
                        value={digits.n(kpis.low_stock)}
                    />
                </div>

                <p className="text-sm text-muted-foreground">
                    {t('dashboard.month')} - {t('dashboard.vs_last_month')}
                </p>
                <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
                    <KpiTile
                        label={t('dashboard.kpi.sales')}
                        value={digits.money(kpis.sales.month)}
                        {...delta(kpis.sales.month, kpis.sales.last_month)}
                    />
                    <KpiTile
                        label={t('dashboard.kpi.invoices')}
                        value={digits.n(kpis.invoices.month)}
                        {...delta(
                            kpis.invoices.month,
                            kpis.invoices.last_month,
                        )}
                    />
                    <KpiTile
                        label={t('dashboard.kpi.average')}
                        value={digits.money(kpis.average.month)}
                        {...delta(kpis.average.month, kpis.average.last_month)}
                    />
                    <KpiTile
                        label={t('dashboard.kpi.collections')}
                        value={digits.money(kpis.collections.month)}
                        {...delta(
                            kpis.collections.month,
                            kpis.collections.last_month,
                        )}
                    />
                </div>

                <Panel
                    title={t('dashboard.trend')}
                    href="/app/reports/sales-by-day"
                >
                    <TrendChart points={data.trend} />
                </Panel>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel
                        title={t('dashboard.top_customers')}
                        href="/app/reports/top-customers"
                    >
                        {data.top_customers.length === 0 ? (
                            empty
                        ) : (
                            <ol className="flex flex-col gap-1">
                                {data.top_customers.map((c, i) => (
                                    <li
                                        key={`${c.customer}-${i}`}
                                        className="flex justify-between gap-2"
                                    >
                                        <span>
                                            <span className="num text-muted-foreground">
                                                {digits.n(i + 1)}.{' '}
                                            </span>
                                            {c.customer}
                                        </span>
                                        <span className="num font-medium">
                                            {digits.money(c.net)}
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </Panel>
                    <Panel
                        title={t('dashboard.top_products')}
                        href="/app/reports/sales-by-product"
                    >
                        {data.top_products.length === 0 ? (
                            empty
                        ) : (
                            <ol className="flex flex-col gap-1">
                                {data.top_products.map((p, i) => (
                                    <li
                                        key={`${p.product}-${i}`}
                                        className="flex justify-between gap-2"
                                    >
                                        <span>
                                            <span className="num text-muted-foreground">
                                                {digits.n(i + 1)}.{' '}
                                            </span>
                                            {p.product}
                                        </span>
                                        <span className="num font-medium">
                                            {digits.money(p.sales)}
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </Panel>
                    <Panel
                        title={t('dashboard.aging')}
                        href="/app/reports/dues-aging"
                    >
                        {data.aging.total === 0 ? (
                            empty
                        ) : (
                            <table className="w-full text-sm">
                                <tbody>
                                    {agingRows.map(([key, value]) => (
                                        <tr
                                            key={key}
                                            className="border-b last:border-0"
                                        >
                                            <th
                                                scope="row"
                                                className="py-1 text-left font-normal"
                                            >
                                                {t(`reports.col.${key}`)}
                                            </th>
                                            <td className="num py-1 text-right">
                                                {digits.money(value)}
                                            </td>
                                        </tr>
                                    ))}
                                    <tr>
                                        <th
                                            scope="row"
                                            className="py-1 text-left"
                                        >
                                            {t('reports.col.total')}
                                        </th>
                                        <td className="num py-1 text-right font-semibold">
                                            {digits.money(data.aging.total)}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        )}
                    </Panel>
                    <Panel title={t('dashboard.low_stock')} href="/app/stock">
                        {data.low_stock.length === 0 ? (
                            <EmptyState
                                icon={LayoutGrid}
                                title={t('dashboard.nothing')}
                                className="py-4"
                            />
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="text-muted-foreground">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="py-1 text-left font-normal"
                                        >
                                            {t('reports.col.product')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-1 text-right font-normal"
                                        >
                                            {t('dashboard.on_hand')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-1 text-right font-normal"
                                        >
                                            {t('dashboard.reorder')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.low_stock.map((row) => (
                                        <tr
                                            key={row.sku}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-1">
                                                {row.product}
                                            </td>
                                            <td className="num py-1 text-right text-destructive">
                                                {digits.qty(row.on_hand)}
                                            </td>
                                            <td className="num py-1 text-right">
                                                {digits.qty(row.reorder)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </Panel>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
