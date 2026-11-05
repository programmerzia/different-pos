import { useState } from 'react';
import {
    axisTicks,
    compactTaka,
    hourWindow,
    labelEvery,
    linePoints,
    niceMax,
    pointsAttr,
} from '@/components/dashboard/chart-helpers';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import type { DashboardTrendPoint } from '@/types/reports';

const PLOT = {
    width: 650,
    height: 200,
    left: 50,
    right: 24,
    top: 20,
    bottom: 20,
};

/** `13` → `1 PM`; `2026-11-03` → `03 Nov` (Latin digits; localise after). */
export function trendLabel(key: string, granularity: 'hour' | 'day'): string {
    if (granularity === 'hour') {
        const hour = Number(key);
        const h12 = hour % 12 === 0 ? 12 : hour % 12;

        return `${h12} ${hour < 12 ? 'AM' : 'PM'}`;
    }

    return new Date(`${key}T00:00:00`).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
    });
}

/**
 * Revenue line over the period (mockup "Revenue Velocity & Sales Trend"): primary line with a soft
 * area, the comparison window as a dashed line, gridlines with compact taka labels. Hourly for one
 * day, daily otherwise. Every point has a native tooltip; the same numbers are available as a table.
 */
export function TrendChart({
    points: all,
    granularity,
}: {
    points: DashboardTrendPoint[];
    granularity: 'hour' | 'day';
}) {
    const { t } = useT();
    const digits = useDigits();
    const [table, setTable] = useState(false);

    const points = granularity === 'hour' ? hourWindow(all) : all;
    const max = niceMax(
        Math.max(0, ...points.flatMap((p) => [p.net, p.previous])),
    );
    const now = linePoints(
        points.map((p) => p.net),
        max,
        PLOT,
    );
    const before = linePoints(
        points.map((p) => p.previous),
        max,
        PLOT,
    );
    const baseY = PLOT.height - PLOT.bottom;
    const showLabel = labelEvery(points.length, 7);
    const dots = labelEvery(points.length, 13);
    const label = (key: string) => digits.n(trendLabel(key, granularity));

    if (table) {
        return (
            <div className="flex flex-col gap-2">
                <div className="max-h-56 overflow-auto">
                    <table className="w-full text-xs">
                        <caption className="sr-only">
                            {t('dashboard.trend.title')}
                        </caption>
                        <thead className="text-muted-foreground">
                            <tr>
                                <th
                                    scope="col"
                                    className="py-1 text-left font-normal"
                                >
                                    {granularity === 'hour'
                                        ? t('dashboard.trend.hour')
                                        : t('dashboard.trend.day')}
                                </th>
                                <th
                                    scope="col"
                                    className="py-1 text-right font-normal"
                                >
                                    {t('dashboard.trend.current')}
                                </th>
                                <th
                                    scope="col"
                                    className="py-1 text-right font-normal"
                                >
                                    {t('dashboard.trend.previous')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {points.map((p) => (
                                <tr
                                    key={p.key}
                                    className="border-b last:border-0"
                                >
                                    <th
                                        scope="row"
                                        className="num py-1 text-left font-normal"
                                    >
                                        {label(p.key)}
                                    </th>
                                    <td className="num py-1 text-right">
                                        {digits.money(p.net)}
                                    </td>
                                    <td className="num py-1 text-right text-muted-foreground">
                                        {digits.money(p.previous)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <TableToggle table={table} onToggle={() => setTable(false)} />
            </div>
        );
    }

    return (
        <div className="flex flex-col">
            <div className="h-56 w-full pt-2 max-md:h-auto">
                <svg
                    viewBox={`0 0 ${PLOT.width} ${PLOT.height}`}
                    className="size-full overflow-visible select-none max-md:h-auto"
                    aria-hidden="true"
                >
                    <defs>
                        <linearGradient
                            id="dash-trend-fill"
                            x1="0%"
                            y1="0%"
                            x2="0%"
                            y2="100%"
                        >
                            <stop
                                offset="0%"
                                stopColor="var(--primary)"
                                stopOpacity="0.3"
                            />
                            <stop
                                offset="100%"
                                stopColor="var(--primary)"
                                stopOpacity="0"
                            />
                        </linearGradient>
                    </defs>
                    {axisTicks(max).map((tick) => {
                        const y = baseY - (tick / max) * (baseY - PLOT.top);

                        return (
                            <g key={tick}>
                                <line
                                    x1={PLOT.left}
                                    x2={PLOT.width - PLOT.right + 4}
                                    y1={y}
                                    y2={y}
                                    stroke="var(--border)"
                                    strokeDasharray={
                                        tick === 0 ? undefined : '3 3'
                                    }
                                />
                                <text
                                    x={PLOT.left - 8}
                                    y={y + 4}
                                    textAnchor="end"
                                    className={`num fill-muted-foreground text-[10px] max-md:text-[18px] ${tick === 0 ? "max-md:hidden" : ""}`}
                                >
                                    {digits.n(compactTaka(tick))}
                                </text>
                            </g>
                        );
                    })}
                    {points.map((p, i) =>
                        showLabel(i) ? (
                            <text
                                key={p.key}
                                x={now[i]?.x}
                                y={PLOT.height - 4}
                                textAnchor="middle"
                                className="fill-muted-foreground font-mono text-[10px] max-md:text-[18px]"
                            >
                                {label(p.key)}
                            </text>
                        ) : null,
                    )}
                    {now.length > 0 ? (
                        <polygon
                            points={`${PLOT.left},${baseY} ${pointsAttr(now)} ${now.at(-1)?.x ?? 0},${baseY}`}
                            fill="url(#dash-trend-fill)"
                        />
                    ) : null}
                    <polyline
                        points={pointsAttr(before)}
                        fill="none"
                        stroke="var(--muted-foreground)"
                        strokeWidth="1"
                        strokeDasharray="4 4"
                        opacity="0.6"
                    />
                    <polyline
                        points={pointsAttr(now)}
                        fill="none"
                        stroke="var(--primary)"
                        strokeWidth="2.5"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                    {now.map((pt, i) =>
                        dots(i) ? (
                            <circle
                                key={points[i]?.key}
                                cx={pt.x}
                                cy={pt.y}
                                r="4"
                                fill="var(--surface)"
                                stroke="var(--primary)"
                                strokeWidth="2"
                            >
                                <title>
                                    {`${label(points[i]?.key ?? '')}: ${digits.money(points[i]?.net ?? 0)} (${t('dashboard.trend.previous')} ${digits.money(points[i]?.previous ?? 0)})`}
                                </title>
                            </circle>
                        ) : null,
                    )}
                </svg>
            </div>
            <TableToggle table={table} onToggle={() => setTable(true)} />
        </div>
    );
}

function TableToggle({
    table,
    onToggle,
}: {
    table: boolean;
    onToggle: () => void;
}) {
    const { t } = useT();

    return (
        <button
            type="button"
            aria-pressed={table}
            onClick={onToggle}
            className="mt-1 self-start rounded text-[10px] font-medium text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none [@media(pointer:coarse)]:min-h-11"
        >
            {table ? t('dashboard.trend.chart') : t('dashboard.trend.table')}
        </button>
    );
}
