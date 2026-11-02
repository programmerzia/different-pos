import { useEffect, useMemo, useRef, useState } from 'react';
import { Kbd } from '@/components/kbd';
import { QtyStepper } from '@/components/qty-stepper';
import { Button } from '@/components/ui/button';
import { timesQty } from '@/engine/money';
import { QTY_SCALE } from '@/engine/quantity';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';
import type { CatalogItem, CatalogVariant } from '../types';
import { PosDialog } from './pos-dialog';

/** Group the variants by the option with fewer values (usually colour); rows are the other (size). */
export function groupVariants(
    variants: readonly CatalogVariant[],
): { label: string; rows: CatalogVariant[] }[] {
    const distinct = (pick: (v: CatalogVariant) => string | null) =>
        new Set(variants.map(pick).filter((v) => v !== null)).size;
    const one = distinct((v) => v.option1);
    const two = distinct((v) => v.option2);
    if (one === 0 || two === 0) {
        return [{ label: '', rows: [...variants] }];
    }
    const key =
        two <= one
            ? (v: CatalogVariant) => v.option2
            : (v: CatalogVariant) => v.option1;
    const groups = new Map<string, CatalogVariant[]>();
    for (const v of variants) {
        const k = key(v) ?? '';
        groups.set(k, [...(groups.get(k) ?? []), v]);
    }

    return [...groups.entries()].map(([label, rows]) => ({ label, rows }));
}

function rowLabel(v: CatalogVariant): string {
    return (
        [v.option1, v.option2].filter(Boolean).join(' · ') || v.label || v.sku
    );
}

/**
 * Sizes × colours of a family with stock at this branch. Each row has a stepper; "Add to bill" adds
 * every row above zero. Enter adds, Esc closes. Stock limits the quantity unless negative stock is on.
 */
export function VariantPicker({
    item,
    allowNegative,
    onAdd,
    onClose,
}: {
    item: CatalogItem | null;
    allowNegative: boolean;
    onAdd: (rows: { variant: CatalogVariant; qty: number }[]) => void;
    onClose: () => void;
}) {
    const { t } = useT();
    const d = useDigits();
    const [qty, setQtyState] = useState<Record<number, number>>({});
    const latest = useRef<Record<number, number>>({});
    const setQty = (
        next:
            | Record<number, number>
            | ((prev: Record<number, number>) => Record<number, number>),
    ) => {
        latest.current =
            typeof next === 'function' ? next(latest.current) : next;
        setQtyState(latest.current);
    };
    const groups = useMemo(() => groupVariants(item?.variants ?? []), [item]);

    const variants = item?.variants ?? [];
    const pieces = variants.reduce((s, v) => s + (qty[v.id] ?? 0), 0);
    const amount = variants.reduce(
        (s, v) => s + timesQty(v.price, qty[v.id] ?? 0),
        0,
    );

    const close = () => {
        setQty({});
        onClose();
    };
    const confirm = () => {
        // A stepper commits typed text on blur.
        (document.activeElement as HTMLElement | null)?.blur();
        const picked = latest.current;
        const rows = variants
            .filter((v) => (picked[v.id] ?? 0) > 0)
            .map((v) => ({ variant: v, qty: picked[v.id] ?? 0 }));
        if (rows.length === 0) {
            return;
        }
        setQty({});
        onAdd(rows);
    };

    const firstId = variants.find(
        (v) => !(v.stock !== null && v.stock <= 0 && !allowNegative),
    )?.id;

    return (
        <PosDialog
            open={item !== null}
            onClose={close}
            title={t('pos.picker.title', { name: item?.name ?? '' })}
            description={t('pos.picker.hint')}
            className="sm:max-w-2xl"
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    confirm();
                }}
            >
                <div className="mb-2 flex items-center justify-between text-sm">
                    <span className="font-mono text-muted-foreground">
                        {item?.code}
                    </span>
                    <span
                        className="num font-semibold text-primary"
                        aria-live="polite"
                    >
                        {t('pos.picker.summary', {
                            pcs: d.qty(pieces),
                            amount: d.money(amount),
                        })}
                    </span>
                </div>
                <div className="max-h-[52svh] space-y-2 overflow-y-auto">
                    {groups.map((group) => (
                        <section
                            key={group.label}
                            className="overflow-hidden rounded-lg border"
                        >
                            {group.label !== '' && (
                                <h3 className="border-b bg-muted px-3 py-1.5 text-sm font-semibold">
                                    {group.label}
                                </h3>
                            )}
                            <ul className="divide-y">
                                {group.rows.map((v) => {
                                    const soldOut =
                                        v.stock !== null &&
                                        v.stock <= 0 &&
                                        !allowNegative;
                                    const autoFocus = v.id === firstId;
                                    const max =
                                        v.stock === null || allowNegative
                                            ? undefined
                                            : v.stock;

                                    return (
                                        <li
                                            key={v.id}
                                            className={cn(
                                                'grid grid-cols-[1fr_auto_auto_auto] items-center gap-3 px-3 py-2',
                                                soldOut && 'opacity-50',
                                            )}
                                        >
                                            <div className="min-w-0">
                                                <div className="font-semibold">
                                                    {rowLabel(v)}
                                                </div>
                                                <div className="truncate font-mono text-xs text-muted-foreground">
                                                    {v.sku}
                                                </div>
                                            </div>
                                            <div
                                                className={cn(
                                                    'text-xs',
                                                    v.stock !== null &&
                                                        v.stock <= 2 * QTY_SCALE
                                                        ? 'text-danger'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {v.stock === null
                                                    ? t('pos.stock.untracked')
                                                    : soldOut
                                                      ? t('pos.stock.sold_out')
                                                      : t(
                                                            'pos.stock.in_stock',
                                                            {
                                                                qty: d.qty(
                                                                    v.stock,
                                                                ),
                                                            },
                                                        )}
                                            </div>
                                            <div className="num w-24 text-right font-semibold">
                                                {d.money(v.price)}
                                            </div>
                                            {soldOut ? (
                                                <span className="w-[13.5rem]" />
                                            ) : (
                                                <PickerQty
                                                    value={qty[v.id] ?? 0}
                                                    max={max}
                                                    integer={!v.allows_decimal}
                                                    autoFocus={autoFocus}
                                                    label={t(
                                                        'pos.picker.qty_for',
                                                        { name: rowLabel(v) },
                                                    )}
                                                    onChange={(n) =>
                                                        setQty((prev) => ({
                                                            ...prev,
                                                            [v.id]: n,
                                                        }))
                                                    }
                                                />
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        </section>
                    ))}
                </div>
                <div className="mt-3 flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11"
                        onClick={close}
                    >
                        {t('confirm.cancel')} <Kbd keys="escape" onButton />
                    </Button>
                    <Button type="submit" className="min-h-11">
                        {t('pos.picker.add')} <Kbd keys="enter" onButton />
                    </Button>
                </div>
            </form>
        </PosDialog>
    );
}

function PickerQty({
    value,
    max,
    integer,
    autoFocus,
    label,
    onChange,
}: {
    value: number;
    max: number | undefined;
    integer: boolean;
    autoFocus: boolean;
    label: string;
    onChange: (value: number) => void;
}) {
    const ref = useRef<HTMLDivElement>(null);
    useEffect(() => {
        if (autoFocus) {
            requestAnimationFrame(() =>
                ref.current?.querySelector('input')?.focus(),
            );
        }
        // Focus once, when the picker opens.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return (
        <div ref={ref}>
            <QtyStepper
                value={value}
                onChange={onChange}
                {...(max !== undefined ? { max } : {})}
                integer={integer}
                aria-label={label}
            />
        </div>
    );
}
