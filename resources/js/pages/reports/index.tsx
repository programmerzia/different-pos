import { Head, Link } from '@inertiajs/react';
import { FileSpreadsheet } from 'lucide-react';
import { useState } from 'react';
import { NativeSelect } from '@/components/native-select';
import { Page, PageHeader } from '@/components/page-header';
import {
    type CatalogueCard,
    directoryCards,
    POINT_IN_TIME_REPORTS,
    REPORT_CATEGORIES,
    type ReportCategory,
} from '@/components/reports/catalogue';
import {
    fiscalYearLabel,
    REPORT_PERIODS,
    type ReportPeriod,
    reportPeriodRange,
} from '@/components/reports/report-periods';
import { ChipRow } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

type Props = {
    groups: Record<string, { name: string }[]>;
    canExport: boolean;
};

type Card = CatalogueCard & { built: boolean };

export default function ReportsIndex({ groups, canExport }: Props) {
    const { t } = useT();
    const digits = useDigits();
    const [period, setPeriod] = useState<ReportPeriod>('today');
    const [category, setCategory] = useState<ReportCategory | ''>('');
    const [today] = useState(() => new Date());
    const range = reportPeriodRange(period, today);
    const available = Object.values(groups).flatMap((reports) =>
        reports.map((r) => r.name),
    );
    const cards = directoryCards(available, category);
    const main = cards.filter((c) => c.section === 'main');
    const more = cards.filter((c) => c.section === 'more');
    const nextPhase = t('reports.directory.next_phase');

    const href = (report: string) =>
        POINT_IN_TIME_REPORTS.has(report)
            ? `/app/reports/${report}`
            : `/app/reports/${report}?from=${range.from}&to=${range.to}`;

    const renderCard = (card: Card) => {
        const title = t(`reports.directory.items.${card.id}.title`, {
            defaultValue: t(`reports.items.${card.id}.title`),
        });
        const desc = t(`reports.directory.items.${card.id}.desc`, {
            defaultValue: t(`reports.items.${card.id}.desc`),
        });
        const body = (
            <>
                <div>
                    <span className="text-[10px] font-bold tracking-wider text-primary uppercase">
                        {t(`reports.directory.categories.${card.category}`)}
                    </span>
                    <span
                        className={cn(
                            'mt-1 block text-xs font-bold text-foreground',
                            card.built && 'group-hover:text-primary',
                        )}
                    >
                        {title}
                    </span>
                    <span className="mt-0.5 block text-[11px] leading-snug text-muted-foreground">
                        {desc}
                    </span>
                </div>
                <span className="flex items-center justify-between gap-2 border-t pt-2 text-[10px] text-muted-foreground">
                    <span>
                        {t('reports.directory.format')}{' '}
                        <span
                            className={cn(
                                card.built && canExport
                                    ? 'text-foreground'
                                    : 'line-through opacity-60',
                            )}
                        >
                            CSV
                        </span>
                        {' / '}
                        <span
                            className="line-through opacity-60"
                            title={nextPhase}
                        >
                            XLSX
                        </span>
                        {' / '}
                        <span
                            className="line-through opacity-60"
                            title={nextPhase}
                        >
                            PDF
                        </span>
                    </span>
                    {card.built ? (
                        <span className="font-semibold text-primary group-hover:underline">
                            {t('reports.directory.run')} →
                        </span>
                    ) : (
                        <span className="rounded-sm bg-surface-sunken px-1.5 py-0.5 font-semibold">
                            {nextPhase}
                        </span>
                    )}
                </span>
            </>
        );

        return (
            <li key={card.id} className="flex">
                {card.built ? (
                    <Link
                        href={href(card.report)}
                        className="group flex w-full flex-col justify-between space-y-2 rounded-xl border bg-surface p-3.5 transition-all hover:border-primary focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {body}
                    </Link>
                ) : (
                    <div
                        title={nextPhase}
                        aria-disabled="true"
                        className="flex w-full cursor-not-allowed flex-col justify-between space-y-2 rounded-xl border border-dashed bg-surface p-3.5 opacity-70"
                    >
                        {body}
                    </div>
                )}
            </li>
        );
    };

    return (
        <>
            <Head title={t('reports.title')} />
            <Page className="gap-4">
                <PageHeader
                    className="border-b pb-3"
                    title={t('reports.directory.title')}
                    subtitle={t('reports.directory.subtitle')}
                    actions={
                        <>
                            <NativeSelect
                                aria-label={t('reports.directory.period')}
                                className="h-8 w-auto min-w-34 text-xs"
                                value={period}
                                onChange={(e) =>
                                    setPeriod(e.target.value as ReportPeriod)
                                }
                            >
                                {REPORT_PERIODS.map((key) => (
                                    <option key={key} value={key}>
                                        {t(`reports.directory.periods.${key}`, {
                                            year: digits.n(
                                                fiscalYearLabel(today),
                                            ),
                                        })}
                                    </option>
                                ))}
                            </NativeSelect>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    {/* aria-disabled (not disabled) so focus still shows the tooltip. */}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        aria-disabled="true"
                                        aria-describedby="xlsx-next-phase"
                                        className="cursor-not-allowed opacity-50"
                                    >
                                        <FileSpreadsheet aria-hidden="true" />
                                        {t('reports.directory.export_excel')}
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent id="xlsx-next-phase">
                                    {nextPhase}
                                </TooltipContent>
                            </Tooltip>
                        </>
                    }
                />

                <ChipRow
                    group={{
                        key: 'category',
                        label: t('reports.directory.category_filter'),
                        value: category,
                        options: [
                            {
                                value: '',
                                label: t('reports.directory.all_categories'),
                            },
                            ...REPORT_CATEGORIES.map((key) => ({
                                value: key,
                                label: t(`reports.directory.categories.${key}`),
                            })),
                        ],
                        onChange: (value) =>
                            setCategory(value as ReportCategory | ''),
                        scroll: true,
                    }}
                />

                <ul
                    aria-label={t('reports.directory.title')}
                    className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3"
                >
                    {main.map(renderCard)}
                </ul>

                {more.length > 0 ? (
                    <section className="flex flex-col gap-3">
                        <h2 className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                            {t('reports.directory.more')}
                        </h2>
                        <ul
                            aria-label={t('reports.directory.more')}
                            className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3"
                        >
                            {more.map(renderCard)}
                        </ul>
                    </section>
                ) : null}
            </Page>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reports', href: '/app/reports' }],
};
