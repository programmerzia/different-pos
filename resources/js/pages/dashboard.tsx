import { Head } from '@inertiajs/react';
import { LayoutGrid } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { useT } from '@/i18n';
import { dashboard } from '@/routes';

export default function Dashboard() {
    const { t } = useT();

    return (
        <>
            <Head title={t('dashboard.title')} />
            <div className="p-4 md:p-6">
                <h1 className="text-2xl font-bold">{t('dashboard.title')}</h1>
                <EmptyState
                    icon={LayoutGrid}
                    title={t('dashboard.soon')}
                    className="mt-8"
                />
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
