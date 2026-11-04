import { useDigits } from "@/hooks/use-digits";
import { useT } from "@/i18n";
import type { DashboardDistricts } from "@/types/reports";

type Bar = {
    key: string;
    label: string;
    sales: number;
    invoices: number;
    tone: string;
    muted?: boolean;
};

/**
 * Net sales per customer district as horizontal bars (same primary bar style as the other
 * dashboard charts). Sales whose customer has no district (walk-in, or no location) are a visible
 * "Not set" row and the smaller districts are folded into "Others", so the rows add up to Net Sales.
 */
export function DistrictBars({ districts }: { districts: DashboardDistricts }) {
    const { t, locale } = useT();
    const { n, money } = useDigits();

    const bars: Bar[] = districts.rows.map((d) => ({
        key: `d${d.id}`,
        label: locale === "bn" && d.name_bn ? d.name_bn : d.name_en,
        sales: d.sales,
        invoices: d.invoices,
        tone: "bg-primary",
    }));
    if (districts.others.districts > 0) {
        bars.push({
            key: "others",
            label: t("dashboard.districts.others", {
                n: n(districts.others.districts),
            }),
            sales: districts.others.sales,
            invoices: districts.others.invoices,
            tone: "bg-muted-foreground",
            muted: true,
        });
    }
    if (districts.unset.invoices > 0) {
        bars.push({
            key: "unset",
            label: t("dashboard.districts.unset"),
            sales: districts.unset.sales,
            invoices: districts.unset.invoices,
            tone: "bg-warning",
            muted: true,
        });
    }

    const max = Math.max(1, ...bars.map((b) => Math.max(0, b.sales)));

    return (
        <ul className="space-y-2 text-xs">
            {bars.map((b) => (
                <li key={b.key}>
                    <div className="flex items-baseline justify-between gap-2">
                        <span
                            className={`min-w-0 truncate ${b.muted ? "italic text-muted-foreground" : "font-semibold text-foreground"}`}
                        >
                            {b.label}
                        </span>
                        <span className="num shrink-0 font-bold text-foreground">
                            {money(b.sales)}
                        </span>
                    </div>
                    <div className="mt-1 flex items-center gap-2">
                        <div
                            className="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-surface-sunken"
                            aria-hidden="true"
                        >
                            <div
                                className={`h-full rounded-full ${b.tone}`}
                                style={{
                                    width: `${(Math.max(0, b.sales) / max) * 100}%`,
                                }}
                            />
                        </div>
                        <span className="shrink-0 text-[10px] text-muted-foreground">
                            {t("dashboard.districts.invoices", {
                                n: n(b.invoices),
                            })}
                        </span>
                    </div>
                </li>
            ))}
        </ul>
    );
}
