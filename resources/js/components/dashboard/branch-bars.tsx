import {
    axisTicks,
    compactTaka,
    niceMax,
} from '@/components/dashboard/chart-helpers';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import type { DashboardBranchRow } from '@/types/reports';

const W = 320;
const H = 160;
const LEFT = 45;
const RIGHT = 10;
const TOP = 20;
const BASE = 140;
const BAR = 14;
const GAP = 3;

/**
 * Grouped bars per branch (mockup "Branch Performance Comparison"): net sales (primary), the
 * previous window (border grey, where the mockup had a target) and gross profit (success, only
 * with reports.profit).
 */
export function BranchBars({ rows }: { rows: DashboardBranchRow[] }) {
    const { t } = useT();
    const digits = useDigits();
    const withProfit = rows.some((r) => r.profit !== undefined);
    const series: { key: 'sales' | 'previous' | 'profit'; colour: string }[] = [
        { key: 'sales', colour: 'var(--primary)' },
        { key: 'previous', colour: 'var(--border)' },
        ...(withProfit
            ? [{ key: 'profit' as const, colour: 'var(--success)' }]
            : []),
    ];
    const max = niceMax(
        Math.max(
            0,
            ...rows.flatMap((r) => [r.sales, r.previous, r.profit ?? 0]),
        ),
        3,
    );
    const slot = (W - LEFT - RIGHT) / Math.max(1, rows.length);
    const groupWidth = series.length * BAR + (series.length - 1) * GAP;
    const height = (v: number) =>
        max <= 0 ? 0 : (Math.max(0, v) / max) * (BASE - TOP);

    return (
        <div className="flex flex-col">
            <div className="h-44 w-full pt-1">
                <svg
                    viewBox={`0 0 ${W} ${H}`}
                    className="size-full select-none"
                    aria-hidden="true"
                >
                    {axisTicks(max, 3).map((tick) => {
                        const y = BASE - height(tick);

                        return (
                            <g key={tick}>
                                <line
                                    x1={LEFT}
                                    x2={W - RIGHT}
                                    y1={y}
                                    y2={y}
                                    stroke="var(--border)"
                                    strokeDasharray={
                                        tick === 0 ? undefined : '2 2'
                                    }
                                />
                                <text
                                    x={LEFT - 7}
                                    y={y + 4}
                                    textAnchor="end"
                                    className="num fill-muted-foreground text-[9px]"
                                >
                                    {digits.n(compactTaka(tick))}
                                </text>
                            </g>
                        );
                    })}
                    {rows.map((row, i) => {
                        const x0 = LEFT + i * slot + (slot - groupWidth) / 2;

                        return (
                            <g key={row.id}>
                                {series.map((s, j) => {
                                    const value = row[s.key] ?? 0;
                                    const h =
                                        value > 0
                                            ? Math.max(1, height(value))
                                            : 0;

                                    return (
                                        <rect
                                            key={s.key}
                                            x={x0 + j * (BAR + GAP)}
                                            y={BASE - h}
                                            width={BAR}
                                            height={h}
                                            rx="2"
                                            fill={s.colour}
                                        >
                                            <title>{`${row.code} ${row.name} · ${t(`dashboard.branches.${s.key}`)}: ${digits.money(value)}`}</title>
                                        </rect>
                                    );
                                })}
                                <text
                                    x={x0 + groupWidth / 2}
                                    y={BASE + 14}
                                    textAnchor="middle"
                                    className="fill-foreground text-[9px] font-bold"
                                >
                                    {`${row.code} ${row.name}`.slice(0, 14)}
                                </text>
                            </g>
                        );
                    })}
                </svg>
            </div>
            <ul className="sr-only">
                {rows.map((row) => (
                    <li key={row.id}>
                        {`${row.code} ${row.name}: ${series.map((s) => `${t(`dashboard.branches.${s.key}`)} ${digits.money(row[s.key] ?? 0)}`).join(', ')}`}
                    </li>
                ))}
            </ul>
            <div
                className="flex items-center justify-center gap-3 border-t pt-2 text-[10px]"
                aria-hidden="true"
            >
                {series.map((s) => (
                    <span key={s.key} className="flex items-center gap-1">
                        <span
                            className="size-2 rounded"
                            style={{ backgroundColor: s.colour }}
                        />
                        <span>{t(`dashboard.branches.${s.key}`)}</span>
                    </span>
                ))}
            </div>
        </div>
    );
}
