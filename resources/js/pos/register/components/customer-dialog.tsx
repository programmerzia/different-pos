import { Search, UserX } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Kbd } from '@/components/kbd';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { toLatinDigits } from '@/engine/digits';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';
import { customerCard, searchCustomers } from '../api';
import type { CustomerCard, CustomerHit } from '../types';
import { PosDialog } from './pos-dialog';

type Props = {
    open: boolean;
    current: CustomerCard | null;
    onAttach: (customer: CustomerCard) => void;
    onDetach: () => void;
    onClose: () => void;
};

/** F4: find a customer by number, phone, name or shop; ↑↓ + Enter attaches. */
export function CustomerDialog({ open, onClose, ...rest }: Props) {
    const { t } = useT();

    return (
        <PosDialog
            open={open}
            onClose={onClose}
            title={t('pos.customer.title')}
            description={t('pos.customer.hint')}
            className="sm:max-w-xl"
        >
            <CustomerSearchBody onClose={onClose} {...rest} />
        </PosDialog>
    );
}

function CustomerSearchBody({
    current,
    onAttach,
    onDetach,
    onClose,
}: Omit<Props, 'open'>) {
    const { t } = useT();
    const d = useDigits();
    const [q, setQ] = useState('');
    const [found, setFound] = useState<{ term: string; hits: CustomerHit[] }>({
        term: '',
        hits: [],
    });
    const [index, setIndex] = useState(0);
    const [busy, setBusy] = useState(false);
    const [failed, setFailed] = useState(false);
    const listRef = useRef<HTMLUListElement>(null);

    const term = toLatinDigits(q.trim());
    const hits = term.length >= 2 && found.term === term ? found.hits : [];

    useEffect(() => {
        if (term.length < 2) {
            return;
        }
        const controller = new AbortController();
        const timer = setTimeout(() => {
            searchCustomers(term, controller.signal)
                .then((r) => {
                    setFound({ term, hits: r.customers });
                    setIndex(0);
                    setFailed(false);
                })
                .catch(() => !controller.signal.aborted && setFailed(true));
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [term]);

    const pick = async (hit: CustomerHit | undefined) => {
        if (!hit || busy) {
            return;
        }
        setBusy(true);
        try {
            const { customer } = await customerCard(hit.uuid);
            onAttach(customer);
        } catch {
            setFailed(true);
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <div className="relative">
                <Search
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    autoFocus
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder={t('pos.customer.placeholder')}
                    aria-label={t('pos.customer.placeholder')}
                    className="h-11 pl-9"
                    onKeyDown={(e) => {
                        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                            e.preventDefault();
                            const next = Math.max(
                                0,
                                Math.min(
                                    hits.length - 1,
                                    index + (e.key === 'ArrowDown' ? 1 : -1),
                                ),
                            );
                            setIndex(next);
                            listRef.current?.children[next]?.scrollIntoView({
                                block: 'nearest',
                            });
                        }
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            void pick(hits[index]);
                        }
                    }}
                />
            </div>
            {failed && (
                <p className="text-sm text-danger" role="alert">
                    {t('pos.errors.network')}
                </p>
            )}
            <ul
                ref={listRef}
                aria-label={t('pos.customer.results')}
                className="max-h-[50svh] divide-y overflow-y-auto rounded-md border empty:hidden"
            >
                {hits.map((hit, i) => (
                    <li key={hit.id}>
                        <button
                            type="button"
                            tabIndex={-1}
                            aria-current={i === index ? 'true' : undefined}
                            className={cn(
                                'flex min-h-11 w-full items-center gap-3 px-3 py-2 text-left hover:bg-accent',
                                i === index && 'bg-primary-soft',
                            )}
                            onMouseEnter={() => setIndex(i)}
                            onClick={() => void pick(hit)}
                        >
                            <span className="min-w-0 flex-1">
                                <span className="block truncate font-semibold">
                                    {hit.name}
                                </span>
                                {hit.shop_name && (
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {hit.shop_name}
                                    </span>
                                )}
                            </span>
                            <span className="num text-right text-xs text-muted-foreground">
                                {hit.number && (
                                    <span className="block font-mono">
                                        #{d.n(hit.number)}
                                    </span>
                                )}
                                {hit.phone && (
                                    <span className="block">
                                        {d.n(hit.phone)}
                                    </span>
                                )}
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
            {term.length >= 2 &&
                found.term === term &&
                hits.length === 0 &&
                !failed && (
                    <p className="text-sm text-muted-foreground">
                        {t('pos.customer.none')}
                    </p>
                )}
            <div className="flex justify-between gap-2">
                {current ? (
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11"
                        onClick={onDetach}
                    >
                        <UserX aria-hidden="true" /> {t('pos.customer.detach')}
                    </Button>
                ) : (
                    <span />
                )}
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-11"
                    onClick={onClose}
                >
                    {t('confirm.cancel')} <Kbd keys="escape" onButton />
                </Button>
            </div>
        </>
    );
}
