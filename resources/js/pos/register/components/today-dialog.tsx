import { Clock, Printer } from 'lucide-react';
import { useEffect, useState } from 'react';
import { KpiTile } from '@/components/kpi-tile';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { todaySummary } from '../api';
import type { TodaySummary } from '../types';
import { PosDialog } from './pos-dialog';

/** F7: today's sales at this register (or by this cashier), read-only, with reprint. */
export function TodayDialog({
    open,
    registerId,
    receiptUrl,
    onClose,
}: {
    open: boolean;
    registerId: number | null;
    receiptUrl: (id: number) => string | null;
    onClose: () => void;
}) {
    const { t } = useT();

    return (
        <PosDialog
            icon={Clock}
            open={open}
            onClose={onClose}
            title={t('pos.today.title')}
            className="sm:max-w-2xl"
        >
            <TodayBody registerId={registerId} receiptUrl={receiptUrl} />
        </PosDialog>
    );
}

function TodayBody({
    registerId,
    receiptUrl,
}: {
    registerId: number | null;
    receiptUrl: (id: number) => string | null;
}) {
    const { t } = useT();
    const d = useDigits();
    const [data, setData] = useState<TodaySummary | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        todaySummary(registerId)
            .then(setData)
            .catch(() => setFailed(true));
    }, [registerId]);

    return (
        <>
            {failed && (
                <p className="text-sm text-danger" role="alert">
                    {t('pos.errors.network')}
                </p>
            )}
            {!data && !failed && <Skeleton className="h-32 w-full" />}
            {data && (
                <div className="space-y-4">
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <KpiTile
                            label={t('pos.today.count')}
                            value={d.n(data.count)}
                        />
                        <KpiTile
                            label={t('pos.today.total')}
                            value={d.money(data.total)}
                        />
                        <KpiTile
                            label={t('pos.today.paid')}
                            value={d.money(data.paid)}
                        />
                        <KpiTile
                            label={t('pos.today.due')}
                            value={d.money(data.due)}
                        />
                    </div>
                    {data.methods.length > 0 && (
                        <dl className="num grid grid-cols-2 gap-1 text-sm">
                            {data.methods.map((m) => (
                                <div key={m.name} className="contents">
                                    <dt>{m.name}</dt>
                                    <dd className="text-right">
                                        {d.money(m.amount)}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    )}
                    <table className="w-full text-sm">
                        <caption className="mb-1 text-left font-semibold">
                            {t('pos.today.recent')}
                        </caption>
                        <tbody className="divide-y">
                            {data.recent.map((sale) => {
                                const url = receiptUrl(sale.id);

                                return (
                                    <tr key={sale.uuid}>
                                        <td className="py-1.5 font-mono">
                                            {sale.number}
                                        </td>
                                        <td className="num py-1.5 text-right">
                                            {d.money(sale.total)}
                                        </td>
                                        <td className="num py-1.5 text-right text-warning">
                                            {sale.due > 0
                                                ? d.money(sale.due)
                                                : ''}
                                        </td>
                                        <td className="py-1.5 text-right">
                                            {url && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-11"
                                                    aria-label={t(
                                                        'pos.done.print_named',
                                                        { number: sale.number },
                                                    )}
                                                    onClick={() =>
                                                        window.open(
                                                            url,
                                                            '_blank',
                                                            'noopener',
                                                        )
                                                    }
                                                >
                                                    <Printer aria-hidden="true" />
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                    {data.recent.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            {t('pos.today.none')}
                        </p>
                    )}
                </div>
            )}
        </>
    );
}
