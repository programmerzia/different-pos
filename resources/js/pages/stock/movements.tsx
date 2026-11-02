import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';

type Move = { id: number; type: string; qty: number; balance: number; unit_cost: number; note: string | null; at: string | null };

export default function StockMovements({
    product,
    onHand,
    branch,
    rows,
    truncated,
}: {
    product: { id: number; name: string; variant_label: string | null; sku: string };
    onHand: number;
    branch: { id: number; name: string };
    rows: Move[];
    truncated: boolean;
}) {
    const { t } = useT();
    const digits = useDigits();
    const title = `${product.name}${product.variant_label ? ` · ${product.variant_label}` : ''}`;

    return (
        <>
            <Head title={title} />
            <div className="flex max-w-5xl flex-col gap-4 p-4 md:p-6">
                <Button asChild variant="ghost" className="-ml-3 w-fit min-h-11"><Link href="/app/stock"><ArrowLeft aria-hidden="true" /> {t('stock.title')}</Link></Button>
                <div className="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h1 className="text-2xl font-bold">{title}</h1>
                        <p className="text-sm text-muted-foreground"><span className="num">{product.sku}</span> · {t('stock.branch', { name: branch.name })}</p>
                    </div>
                    <div className="text-right">
                        <p className="text-xs text-muted-foreground">{t('stock.on_hand')}</p>
                        <p className="num text-3xl font-bold">{digits.qty(onHand)}</p>
                    </div>
                </div>
                {rows.length === 0 ? <EmptyState title={t('stock.no_movements')} /> : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <caption className="sr-only">{t('stock.movements')}</caption>
                            <thead className="bg-muted text-left"><tr>
                                <th scope="col" className="px-3 py-2">{t('stock.col_date')}</th>
                                <th scope="col" className="px-3 py-2">{t('stock.col_type')}</th>
                                <th scope="col" className="px-3 py-2 text-right">{t('stock.col_qty')}</th>
                                <th scope="col" className="px-3 py-2 text-right">{t('stock.col_balance')}</th>
                                <th scope="col" className="px-3 py-2">{t('purchases.note')}</th>
                            </tr></thead>
                            <tbody>
                                {rows.map((m) => (
                                    <tr key={m.id} className="border-t">
                                        <td className="num px-3 py-2">{digits.n(m.at ?? '')}</td>
                                        <td className="px-3 py-2">{t(`stock.type.${m.type}`)}</td>
                                        <td className="num px-3 py-2 text-right font-medium">{m.qty > 0 ? '+' : ''}{digits.qty(m.qty)}</td>
                                        <td className="num px-3 py-2 text-right">{digits.qty(m.balance)}</td>
                                        <td className="px-3 py-2 text-muted-foreground">{m.note ?? ''}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {truncated ? <p className="border-t px-3 py-2 text-xs text-muted-foreground">{t('stock.truncated')}</p> : null}
                    </div>
                )}
            </div>
        </>
    );
}
