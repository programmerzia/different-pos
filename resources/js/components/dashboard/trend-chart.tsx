import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';

type Point = { date: string; net: number };

const WIDTH = 720;
const HEIGHT = 200;
const PAD = { top: 8, right: 8, bottom: 22, left: 8 };

/**
 * Daily net sales as plain SVG bars: one series, so no legend; the heading names it. Every bar has a
 * native tooltip, and the same numbers are available as a table.
 */
export function TrendChart({ points }: { points: Point[] }) {
    const { t } = useT();
    const digits = useDigits();
    const [table, setTable] = useState(false);

    const max = Math.max(1, ...points.map((p) => p.net));
    const inner = WIDTH - PAD.left - PAD.right;
    const slot = inner / Math.max(1, points.length);
    const barWidth = Math.max(2, slot - 2);
    const plotHeight = HEIGHT - PAD.top - PAD.bottom;

    return (
        <div className="flex flex-col gap-2">
            {table ? (
                <div className="max-h-56 overflow-auto">
                    <table className="w-full text-sm">
                        <caption className="sr-only">
                            {t('dashboard.trend')}
                        </caption>
                        <tbody>
                            {points.map((p) => (
                                <tr key={p.date} className="border-b">
                                    <th
                                        scope="row"
                                        className="num py-1 text-left font-normal"
                                    >
                                        {digits.n(p.date)}
                                    </th>
                                    <td className="num py-1 text-right">
                                        {digits.money(p.net)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <>
                <span className="sr-only">{t('dashboard.trend')}</span>
                <svg
                    viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
                    aria-hidden="true"
                    className="h-auto w-full"
                >
                    <line
                        x1={PAD.left}
                        x2={WIDTH - PAD.right}
                        y1={HEIGHT - PAD.bottom}
                        y2={HEIGHT - PAD.bottom}
                        className="stroke-border"
                    />
                    {points.map((p, i) => {
                        const h =
                            p.net <= 0
                                ? 0
                                : Math.max(2, (p.net / max) * plotHeight);
                        const x = PAD.left + i * slot + (slot - barWidth) / 2;

                        return (
                            <g key={p.date}>
                                <rect
                                    x={x}
                                    y={HEIGHT - PAD.bottom - h}
                                    width={barWidth}
                                    height={h}
                                    rx={2}
                                    className="fill-primary"
                                >
                                    <title>{`${digits.n(p.date)}: ${digits.money(p.net)}`}</title>
                                </rect>
                                {i % 7 === 0 || i === points.length - 1 ? (
                                    <text
                                        x={x + barWidth / 2}
                                        y={HEIGHT - 6}
                                        textAnchor="middle"
                                        className="fill-muted-foreground text-[10px]"
                                    >
                                        {digits.n(p.date.slice(5))}
                                    </text>
                                ) : null}
                            </g>
                        );
                    })}
                </svg>
                </>
            )}
            <Button
                type="button"
                variant="ghost"
                size="sm"
                className="self-start"
                aria-pressed={table}
                onClick={() => setTable((v) => !v)}
            >
                {t('dashboard.trend_table')}
            </Button>
        </div>
    );
}
