import {
    HEAT_DAYS,
    HEAT_SLOTS,
    heatGrid,
    heatOpacity,
} from '@/components/dashboard/chart-helpers';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import type { DashboardHeatCell } from '@/types/reports';

/** Number of sales by weekday × two-hour slot (mockup "Hourly Sales Heat-Map"). */
export function HeatMap({ cells }: { cells: DashboardHeatCell[] }) {
    const { t } = useT();
    const digits = useDigits();
    const { grid, max } = heatGrid(cells);
    const slot = (hour: number) => t(`dashboard.heat.slot_${hour}`);

    return (
        <table className="w-full table-fixed border-separate border-spacing-1 text-[10px]">
            <caption className="sr-only">{t('dashboard.heat.title')}</caption>
            <thead>
                <tr className="font-mono text-[9px] text-muted-foreground">
                    <th scope="col" className="text-left font-normal">
                        {t('dashboard.heat.day')}
                    </th>
                    {HEAT_SLOTS.map((h) => (
                        <th
                            key={h}
                            scope="col"
                            className="text-center font-normal"
                        >
                            {slot(h)}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody>
                {HEAT_DAYS.map((dow, r) => (
                    <tr key={dow}>
                        <th
                            scope="row"
                            className="text-left font-mono font-normal text-muted-foreground"
                        >
                            {t(`dashboard.heat.dow_${dow}`)}
                        </th>
                        {HEAT_SLOTS.map((h, c) => {
                            const count = grid[r]?.[c] ?? 0;
                            const text = `${t(`dashboard.heat.dow_${dow}`)} ${slot(h)}: ${t('dashboard.heat.sales', { count, n: digits.n(count) })}`;

                            return (
                                <td key={h} className="p-0">
                                    <div
                                        title={text}
                                        aria-hidden="true"
                                        style={{
                                            opacity: heatOpacity(count, max),
                                        }}
                                        className="h-5 rounded bg-primary"
                                    />
                                    <span className="sr-only">{text}</span>
                                </td>
                            );
                        })}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

/** The Low ▫▫▫ High legend of the heat-map header. */
export function HeatLegend() {
    const { t } = useT();

    return (
        <span className="flex items-center gap-1 font-sans text-[10px] text-muted-foreground">
            <span>{t('dashboard.heat.low')}</span>
            <span className="size-2 rounded bg-primary opacity-20" />
            <span className="size-2 rounded bg-primary opacity-50" />
            <span className="size-2 rounded bg-primary opacity-90" />
            <span>{t('dashboard.heat.high')}</span>
        </span>
    );
}
