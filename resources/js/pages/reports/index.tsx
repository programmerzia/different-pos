import { Head, Link } from '@inertiajs/react';
import { BarChart3 } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { useT } from '@/i18n';

type Props = { groups: Record<string, { name: string }[]> };

export default function ReportsIndex({ groups }: Props) {
    const { t } = useT();
    const entries = Object.entries(groups);

    return (
        <>
            <Head title={t('reports.title')} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <h1 className="text-2xl font-bold">{t('reports.title')}</h1>
                {entries.length === 0 ? (
                    <EmptyState icon={BarChart3} title={t('reports.no_rows')} />
                ) : (
                    entries.map(([group, reports]) => (
                        <section
                            key={group}
                            aria-labelledby={`reports-${group}`}
                            className="flex flex-col gap-3"
                        >
                            <h2
                                id={`reports-${group}`}
                                className="text-lg font-semibold"
                            >
                                {t(`reports.groups.${group}`)}
                            </h2>
                            <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                {reports.map((report) => (
                                    <li key={report.name}>
                                        <Link
                                            href={`/app/reports/${report.name}`}
                                            className="block min-h-11 rounded-lg border bg-card p-4 text-card-foreground shadow-xs hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            <span className="font-medium">
                                                {t(
                                                    `reports.items.${report.name}.title`,
                                                )}
                                            </span>
                                            <span className="mt-1 block text-sm text-muted-foreground">
                                                {t(
                                                    `reports.items.${report.name}.desc`,
                                                )}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))
                )}
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reports', href: '/app/reports' }],
};
