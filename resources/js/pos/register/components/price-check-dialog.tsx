import { Tag } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Input } from '@/components/ui/input';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { searchProducts } from '../api';
import type { CatalogItem } from '../types';
import { PosDialog } from './pos-dialog';

/** F5: scan or type to see price and branch stock without touching the bill. */
export function PriceCheckDialog({
    open,
    initial,
    onClose,
}: {
    open: boolean;
    initial: string;
    onClose: () => void;
}) {
    const { t } = useT();

    return (
        <PosDialog
            icon={Tag}
            open={open}
            onClose={onClose}
            title={t('pos.price.title')}
            description={t('pos.price.hint')}
            className="sm:max-w-xl"
        >
            <PriceCheckBody initial={initial} />
        </PosDialog>
    );
}

function PriceCheckBody({ initial }: { initial: string }) {
    const { t } = useT();
    const d = useDigits();
    const [q, setQ] = useState(initial);
    const [found, setFound] = useState<{ term: string; items: CatalogItem[] }>({
        term: '',
        items: [],
    });
    const [failed, setFailed] = useState(false);
    const term = q.trim();
    const items = term !== '' && found.term === term ? found.items : [];

    useEffect(() => {
        if (term === '') {
            return;
        }
        const controller = new AbortController();
        const timer = setTimeout(() => {
            searchProducts(term, null, controller.signal)
                .then((r) => {
                    setFound({ term, items: r.items.slice(0, 10) });
                    setFailed(false);
                })
                .catch(() => !controller.signal.aborted && setFailed(true));
        }, 200);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [term]);

    return (
        <>
            <Input
                autoFocus
                className="h-11"
                value={q}
                onChange={(e) => setQ(e.target.value)}
                aria-label={t('pos.scan.placeholder')}
                placeholder={t('pos.scan.placeholder')}
            />
            {failed && (
                <p className="text-sm text-danger" role="alert">
                    {t('pos.errors.network')}
                </p>
            )}
            <div
                className="max-h-[55svh] space-y-2 overflow-y-auto"
                aria-live="polite"
            >
                {items.map((item) => (
                    <section key={item.key} className="rounded-md border">
                        <h3 className="border-b bg-muted px-3 py-1.5 text-sm font-semibold">
                            {item.name}{' '}
                            <span className="font-mono text-xs text-muted-foreground">
                                {item.code}
                            </span>
                        </h3>
                        <table className="w-full text-sm">
                            <tbody className="divide-y">
                                {item.variants.map((v) => (
                                    <tr key={v.id}>
                                        <td className="px-3 py-1.5">
                                            {[v.option1, v.option2]
                                                .filter(Boolean)
                                                .join(' · ') || v.sku}
                                        </td>
                                        <td className="num px-3 py-1.5 text-right text-muted-foreground">
                                            {v.stock === null
                                                ? t('pos.stock.untracked')
                                                : t('pos.stock.in_stock', {
                                                      qty: d.qty(v.stock),
                                                  })}
                                        </td>
                                        <td className="num px-3 py-1.5 text-right font-semibold">
                                            {d.money(v.price)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                ))}
                {term !== '' &&
                    found.term === term &&
                    items.length === 0 &&
                    !failed && (
                        <p className="text-sm text-muted-foreground">
                            {t('pos.scan.none')}
                        </p>
                    )}
            </div>
        </>
    );
}
