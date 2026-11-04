import { Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { QtyStepper } from '@/components/qty-stepper';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { QTY_SCALE } from '@/engine/quantity';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { apiRequest } from '@/lib/api';
import type { QuoteLine } from '@/lib/exchange-settlement';
import { cn } from '@/lib/utils';
import type { Paisa } from '@/engine/money';
import type { CatalogItem } from '@/pos/register/types';

export type CartLine = {
    product_id: number;
    label: string;
    sku: string;
    /** List price per unit, for orientation only: the quote prices the lines. */
    price: Paisa;
    qty: number;
    allowsDecimal: boolean;
};

/** One selectable variant of the register's lookup. */
export type ProductRow = {
    id: number;
    label: string;
    sku: string;
    price: Paisa;
    /** Milli-units on hand; null = not tracked. */
    stock: number | null;
    allowsDecimal: boolean;
};

const outOfStock = (row: ProductRow) => row.stock !== null && row.stock <= 0;

/** Flattens the register's lookup (families with variants) to one row per variant. */
export function flattenLookup(items: CatalogItem[]): ProductRow[] {
    return items.flatMap((item) =>
        item.variants.map((v) => ({
            id: v.id,
            label: v.label ? `${item.name} · ${v.label}` : v.name,
            sku: v.sku,
            price: v.price,
            stock: v.stock,
            allowsDecimal: v.allows_decimal,
        })),
    );
}

/** Next row the arrow keys land on: out-of-stock rows are skipped. */
export function stepCursor(
    rows: ProductRow[],
    from: number,
    direction: 1 | -1,
): number {
    for (let i = from + direction; i >= 0 && i < rows.length; i += direction) {
        const row = rows[i];
        if (row && !outOfStock(row)) {
            return i;
        }
    }

    return from;
}

export function ReplacementCart({
    cart,
    setCart,
    quoteLines,
    quoteLoading,
}: {
    cart: CartLine[];
    setCart: (update: (lines: CartLine[]) => CartLine[]) => void;
    /** Lines of the quote for the current cart; null while it is not current. */
    quoteLines: QuoteLine[] | null;
    quoteLoading: boolean;
}) {
    const { t } = useT();
    const digits = useDigits();
    const [text, setText] = useState('');
    const [cursor, setCursor] = useState(0);
    const [found, setFound] = useState<{
        q: string;
        rows: ProductRow[];
    } | null>(null);
    const [failed, setFailed] = useState(false);
    const input = useRef<HTMLInputElement>(null);
    const query = text.trim();

    useEffect(() => {
        if (query === '') {
            return;
        }
        const controller = new AbortController();
        const timer = setTimeout(() => {
            apiRequest<{ items: CatalogItem[] }>(
                `/app/api/pos/products?q=${encodeURIComponent(query)}`,
                { signal: controller.signal },
            )
                .then((r) => {
                    setFound({ q: query, rows: flattenLookup(r.items) });
                    setFailed(false);
                })
                .catch(() => !controller.signal.aborted && setFailed(true));
        }, 200);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query]);

    const rows = found && found.q === query ? found.rows : [];
    const searched = found !== null && found.q === query;
    const firstFree = rows.findIndex((r) => !outOfStock(r));
    const activeRow = rows[cursor];
    const active = activeRow && !outOfStock(activeRow) ? cursor : firstFree;
    const activeId = active >= 0 ? `repl-opt-${rows[active]?.id}` : undefined;

    useEffect(() => {
        if (activeId) {
            document
                .getElementById(activeId)
                ?.scrollIntoView({ block: 'nearest' });
        }
    }, [activeId]);

    const add = (row: ProductRow) => {
        if (outOfStock(row)) {
            return;
        }
        setCart((lines) =>
            lines.some((l) => l.product_id === row.id)
                ? lines.map((l) =>
                      l.product_id === row.id
                          ? { ...l, qty: l.qty + QTY_SCALE }
                          : l,
                  )
                : [
                      ...lines,
                      {
                          product_id: row.id,
                          label: row.label,
                          sku: row.sku,
                          price: row.price,
                          qty: QTY_SCALE,
                          allowsDecimal: row.allowsDecimal,
                      },
                  ],
        );
        setText('');
        setCursor(0);
        input.current?.focus();
    };

    const lineQuote = (id: number) =>
        quoteLines?.find((l) => l.product_id === id);

    return (
        <section
            className="rounded-lg border bg-card p-4"
            aria-labelledby="replacement-heading"
        >
            <h2 id="replacement-heading" className="mb-3 font-semibold">
                {t('returns.replacement_items')}
            </h2>
            <div className="relative">
                <Search
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    ref={input}
                    id="replacement-search"
                    type="search"
                    autoComplete="off"
                    className="min-h-11 pl-9"
                    value={text}
                    placeholder={t('returns.product_search')}
                    aria-label={t('returns.product_search')}
                    onChange={(e) => {
                        setText(e.target.value);
                        setCursor(0);
                    }}
                    onKeyDown={(e) => {
                        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                            e.preventDefault();
                            setCursor(
                                stepCursor(
                                    rows,
                                    active,
                                    e.key === 'ArrowDown' ? 1 : -1,
                                ),
                            );
                        } else if (e.key === 'Enter') {
                            // Enter picks a row; it never submits the exchange from here.
                            e.preventDefault();
                            const row = rows[active];
                            if (row) {
                                add(row);
                            }
                        } else if (e.key === 'Escape' && text !== '') {
                            e.stopPropagation();
                            setText('');
                        }
                    }}
                />
            </div>
            {failed ? (
                <p role="alert" className="mt-2 text-sm text-destructive">
                    {t('palette.error')}
                </p>
            ) : null}
            {searched && rows.length === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    {t('returns.no_products')}
                </p>
            ) : null}
            {rows.length > 0 ? (
                <ul
                    id="replacement-results"
                    aria-label={t('returns.product_results')}
                    className="mt-2 grid max-h-72 gap-1 overflow-y-auto"
                >
                    {rows.map((row, index) => {
                        const out = outOfStock(row);

                        return (
                            <li key={row.id}>
                                <button
                                    type="button"
                                    id={`repl-opt-${row.id}`}
                                    aria-current={index === active}
                                    aria-disabled={out}
                                    tabIndex={-1}
                                    className={cn(
                                        'flex min-h-11 w-full cursor-pointer items-center justify-between gap-3 rounded-md border px-3 py-1.5 text-left text-sm',
                                        index === active &&
                                            'border-primary bg-primary-soft ring-[3px] ring-ring/50',
                                        out &&
                                            'cursor-not-allowed bg-surface-sunken text-muted-foreground',
                                        !out &&
                                            index !== active &&
                                            'hover:border-primary',
                                    )}
                                    onMouseDown={(e) => e.preventDefault()}
                                    onClick={() => add(row)}
                                >
                                    <span className="min-w-0">
                                        <span className="block truncate font-medium">
                                            {row.label}
                                        </span>
                                        <span className="num text-xs text-muted-foreground">
                                            {row.sku}
                                        </span>
                                    </span>
                                    <span className="num flex shrink-0 items-center gap-3 text-right">
                                        <span className="grid">
                                            <span className="font-semibold">
                                                {digits.money(row.price)}
                                            </span>
                                            <span
                                                className={cn(
                                                    'text-xs',
                                                    out
                                                        ? 'font-semibold text-destructive'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {row.stock === null
                                                    ? t(
                                                          'returns.stock_untracked',
                                                      )
                                                    : out
                                                      ? t(
                                                            'returns.out_of_stock',
                                                        )
                                                      : t('returns.in_stock', {
                                                            n: digits.qty(
                                                                row.stock,
                                                            ),
                                                        })}
                                            </span>
                                        </span>
                                        {out ? null : (
                                            <Plus
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        )}
                                    </span>
                                </button>
                            </li>
                        );
                    })}
                </ul>
            ) : null}
            {cart.length > 0 ? (
                <table className="mt-3 w-full text-sm">
                    <caption className="sr-only">
                        {t('returns.replacement_items')}
                    </caption>
                    <thead className="border-b text-muted-foreground">
                        <tr>
                            <th scope="col" className="py-2 text-left">
                                {t('sales.item')}
                            </th>
                            <th scope="col" className="py-2 text-center">
                                {t('returns.qty')}
                            </th>
                            <th scope="col" className="py-2 text-right">
                                {t('sales.line_total')}
                            </th>
                            <th scope="col" className="py-2">
                                <span className="sr-only">
                                    {t('returns.remove')}
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {cart.map((c) => {
                            const priced = lineQuote(c.product_id);

                            return (
                                <tr
                                    key={c.product_id}
                                    className="border-b last:border-0"
                                >
                                    <td className="py-2">
                                        <div className="font-medium">
                                            {c.label}
                                        </div>
                                        <div className="num text-xs text-muted-foreground">
                                            {c.sku} · {digits.money(c.price)}
                                        </div>
                                        {priced?.short ? (
                                            <div
                                                role="alert"
                                                className="mt-1 text-xs font-semibold text-destructive"
                                            >
                                                {(priced.stock ?? 0) <= 0
                                                    ? t('returns.out_of_stock')
                                                    : t(
                                                          'returns.only_n_in_stock',
                                                          {
                                                              n: digits.qty(
                                                                  priced.stock ??
                                                                      0,
                                                              ),
                                                          },
                                                      )}
                                            </div>
                                        ) : null}
                                    </td>
                                    <td className="py-2">
                                        <div className="flex justify-center">
                                            <QtyStepper
                                                value={c.qty}
                                                min={QTY_SCALE}
                                                step={QTY_SCALE}
                                                integer={!c.allowsDecimal}
                                                onChange={(v) =>
                                                    setCart((lines) =>
                                                        lines.map((l) =>
                                                            l.product_id ===
                                                            c.product_id
                                                                ? {
                                                                      ...l,
                                                                      qty: v,
                                                                  }
                                                                : l,
                                                        ),
                                                    )
                                                }
                                                aria-label={t(
                                                    'returns.qty_for',
                                                    { name: c.label },
                                                )}
                                            />
                                        </div>
                                    </td>
                                    <td className="num py-2 text-right font-semibold">
                                        {priced ? (
                                            digits.money(priced.total)
                                        ) : (
                                            <span
                                                className="text-muted-foreground"
                                                aria-label={
                                                    quoteLoading
                                                        ? t(
                                                              'returns.calculating',
                                                          )
                                                        : undefined
                                                }
                                            >
                                                {quoteLoading ? '…' : '—'}
                                            </span>
                                        )}
                                    </td>
                                    <td className="py-2 text-right">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-11 md:size-8"
                                            aria-label={t(
                                                'returns.remove_named',
                                                {
                                                    name: c.label,
                                                },
                                            )}
                                            onClick={() =>
                                                setCart((lines) =>
                                                    lines.filter(
                                                        (l) =>
                                                            l.product_id !==
                                                            c.product_id,
                                                    ),
                                                )
                                            }
                                        >
                                            <Trash2 aria-hidden="true" />
                                        </Button>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            ) : (
                <p className="mt-3 text-sm text-muted-foreground">
                    {t('returns.cart_empty')}
                </p>
            )}
        </section>
    );
}
