import { Head, Link } from '@inertiajs/react';
import { Page, PageHeader } from '@/components/page-header';
import { ArrowLeft, Check, Minus, Package, Plus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { FormField } from '@/components/form-field';
import { Kbd } from '@/components/kbd';
import { NativeSelect } from '@/components/native-select';
import { QtyStepper } from '@/components/qty-stepper';
import { ScanField, type ScanProduct } from '@/components/scan-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { timesQty } from '@/engine/money';
import { useDigits } from '@/hooks/use-digits';
import { useFormSubmit } from '@/hooks/use-form-submit';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

type Line = {
    product_id: number;
    name: string;
    sku: string;
    direction: 1 | -1;
    qty: number;
    /** Milli-units on hand at this branch when the line was added. */
    on_hand: number;
    /** Paisa; 0 when the user may not see costs. */
    cost: number;
};

type Prefill = {
    id: number;
    name: string;
    variant_label: string | null;
    sku: string;
    on_hand: number;
    cost_price: number;
};

const TH = 'px-3 py-1.5 text-[10px] font-semibold text-muted-foreground';
const label = (p: { name: string; variant_label: string | null }) =>
    p.variant_label ? `${p.name} · ${p.variant_label}` : p.name;

/** The mockup "Stock Adjustment" dialog as a page: reason + note, scan-to-add lines with on-hand, delta, new on-hand, cost and net value. */
export default function AdjustmentCreate({
    reasons,
    prefill = null,
    canCost = false,
}: {
    reasons: string[];
    /** The stock list's row "Adjust" opens this page with that product as the first line. */
    prefill?: Prefill | null;
    canCost?: boolean;
}) {
    const { t } = useT();
    const digits = useDigits();
    const { submit, processing, errors } = useFormSubmit();
    const scan = useRef<HTMLInputElement>(null);
    const [reason, setReason] = useState(
        reasons.includes('correction') ? 'correction' : (reasons[0] ?? ''),
    );
    const [note, setNote] = useState('');
    const [lines, setLines] = useState<Line[]>(() =>
        prefill
            ? [
                  {
                      product_id: prefill.id,
                      name: label(prefill),
                      sku: prefill.sku,
                      direction: -1,
                      qty: 1000,
                      on_hand: prefill.on_hand,
                      cost: prefill.cost_price,
                  },
              ]
            : [],
    );

    const onPick = (p: ScanProduct) =>
        setLines((prev) => {
            const at = prev.findIndex((l) => l.product_id === p.id);
            if (at >= 0) {
                return prev.map((l, i) =>
                    i === at ? { ...l, qty: l.qty + 1000 } : l,
                );
            }

            return [
                ...prev,
                {
                    product_id: p.id,
                    name: label(p),
                    sku: p.sku,
                    direction: -1,
                    qty: 1000,
                    on_hand: p.on_hand,
                    cost: p.cost_price,
                },
            ];
        });
    const setLine = (index: number, patch: Partial<Line>) =>
        setLines((prev) =>
            prev.map((l, i) => (i === index ? { ...l, ...patch } : l)),
        );

    const delta = (l: Line) => l.direction * l.qty;
    const net = (l: Line) => l.direction * timesQty(l.cost, l.qty);
    const netTotal = lines.reduce((sum, l) => sum + net(l), 0);
    const belowZero = lines.some((l) => l.on_hand + delta(l) < 0);
    const ready = !processing && lines.length > 0 && !belowZero;

    const save = () =>
        submit('post', '/app/adjustments', {
            reason,
            note: note || null,
            items: lines.map((l) => ({
                product_id: l.product_id,
                qty: delta(l),
            })),
        });

    useHotkeys('form', [
        {
            id: 'adjustment.save',
            keys: 'mod+enter',
            labelKey: 'adjustments.apply',
            allowInInput: true,
            handler: (e) => {
                e.preventDefault();
                if (ready) save();
            },
        },
        {
            id: 'adjustment.scan',
            keys: 'f2',
            labelKey: 'scan.label',
            allowInInput: true,
            handler: (e) => {
                e.preventDefault();
                scan.current?.focus();
            },
        },
    ]);

    const lineError = Object.entries(errors).find(([k]) =>
        k.startsWith('items'),
    )?.[1];
    const signed = (paisa: number) =>
        `${paisa < 0 ? '−' : paisa > 0 ? '+' : ''}${digits.money(Math.abs(paisa))}`;

    return (
        <>
            <Head title={t('adjustments.new')} />
            <Page className="max-w-6xl">
                <div className="flex items-center gap-3">
                    <Button
                        asChild
                        variant="outline"
                        size="icon"
                        className="size-11 md:size-9"
                    >
                        <Link
                            href="/app/adjustments"
                            aria-label={t('adjustments.back')}
                        >
                            <ArrowLeft aria-hidden="true" />
                        </Link>
                    </Button>
                    <PageHeader
                        className="flex-1"
                        title={
                            <span className="inline-flex items-center gap-2">
                                <Package
                                    className="size-5 text-primary"
                                    aria-hidden="true"
                                />
                                {t('adjustments.create_title')}
                            </span>
                        }
                        subtitle={t('adjustments.create_subtitle')}
                    />
                </div>

                <form
                    className="flex flex-col gap-3 text-xs"
                    noValidate
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (ready) save();
                    }}
                >
                    <div className="grid gap-3 rounded-xl border bg-surface-sunken p-3 sm:grid-cols-3">
                        <FormField
                            label={t('adjustments.reason')}
                            error={errors['reason']}
                        >
                            <NativeSelect
                                className="bg-surface font-semibold"
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                            >
                                {reasons.map((r) => (
                                    <option key={r} value={r}>
                                        {t(`adjustments.reasons.${r}`)}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        <FormField
                            className="sm:col-span-2"
                            label={t('purchases.note')}
                            error={errors['note']}
                        >
                            <Input
                                className="bg-surface"
                                value={note}
                                placeholder={t('adjustments.note_ph')}
                                onChange={(e) => setNote(e.target.value)}
                            />
                        </FormField>
                    </div>

                    <section
                        aria-labelledby="as-title"
                        className="space-y-2 rounded-xl border-2 border-dashed bg-surface p-3.5"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h2
                                id="as-title"
                                className="flex items-center gap-1.5 text-xs font-bold text-foreground"
                            >
                                <Plus
                                    className="size-3.5 text-primary"
                                    aria-hidden="true"
                                />
                                {t('adjustments.item')}
                            </h2>
                            <span className="text-[10px] text-muted-foreground">
                                {t('adjustments.qty_hint')}{' '}
                                <Kbd keys="f2" className="ml-1" />
                            </span>
                        </div>
                        <ScanField
                            ref={scan}
                            lookupUrl="/app/adjustments/lookup"
                            onPick={onPick}
                        />
                        {lineError ? (
                            <p role="alert" className="text-destructive">
                                {lineError}
                            </p>
                        ) : null}
                    </section>

                    <section
                        aria-labelledby="al-title"
                        className="overflow-hidden rounded-xl border bg-surface"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b bg-surface-sunken p-2.5 text-xs font-bold">
                            <h2 id="al-title">
                                {t('purchases.items')}{' '}
                                <span className="num font-normal text-muted-foreground">
                                    ·{' '}
                                    {t('adjustments.lines_count', {
                                        n: digits.n(lines.length),
                                    })}
                                </span>
                            </h2>
                            {canCost ? (
                                <span className="num text-[10px] font-normal text-muted-foreground">
                                    {t('adjustments.net_total')}:{' '}
                                    <strong
                                        className={cn(
                                            netTotal < 0
                                                ? 'text-destructive'
                                                : 'text-success',
                                        )}
                                    >
                                        {signed(netTotal)}
                                    </strong>
                                </span>
                            ) : null}
                        </div>
                        {lines.length === 0 ? (
                            <p className="p-6 text-center text-muted-foreground">
                                {t('adjustments.scan_hint')}
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full border-collapse text-left text-xs">
                                    <caption className="sr-only">
                                        {t('purchases.items')}
                                    </caption>
                                    <thead className="border-b bg-surface-sunken">
                                        <tr>
                                            <th scope="col" className={TH}>
                                                {t('adjustments.item')}
                                            </th>
                                            <th
                                                scope="col"
                                                className={cn(TH, 'text-right')}
                                            >
                                                {t('adjustments.col_on_hand')}
                                            </th>
                                            <th scope="col" className={TH}>
                                                {t('adjustments.direction')}
                                            </th>
                                            <th scope="col" className={TH}>
                                                {t('adjustments.col_change')}
                                            </th>
                                            <th
                                                scope="col"
                                                className={cn(TH, 'text-right')}
                                            >
                                                {t('adjustments.col_new_qty')}
                                            </th>
                                            {canCost ? (
                                                <>
                                                    <th
                                                        scope="col"
                                                        className={cn(
                                                            TH,
                                                            'text-right',
                                                        )}
                                                    >
                                                        {t(
                                                            'adjustments.col_unit_cost',
                                                        )}
                                                    </th>
                                                    <th
                                                        scope="col"
                                                        className={cn(
                                                            TH,
                                                            'text-right',
                                                        )}
                                                    >
                                                        {t(
                                                            'adjustments.col_net_value',
                                                        )}
                                                    </th>
                                                </>
                                            ) : null}
                                            <th scope="col" className={TH}>
                                                <span className="sr-only">
                                                    {t('common.actions')}
                                                </span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {lines.map((l, i) => {
                                            const after = l.on_hand + delta(l);

                                            return (
                                                <tr
                                                    key={l.product_id}
                                                    className="hover:bg-surface-sunken"
                                                >
                                                    <th
                                                        scope="row"
                                                        className="px-3 py-1.5 text-left font-semibold"
                                                    >
                                                        <div>{l.name}</div>
                                                        <div className="num text-[10px] font-normal text-muted-foreground">
                                                            {l.sku}
                                                        </div>
                                                    </th>
                                                    <td className="num px-3 py-1.5 text-right font-mono">
                                                        {digits.qty(l.on_hand)}
                                                    </td>
                                                    <td className="px-3 py-1.5">
                                                        <fieldset
                                                            className="m-0 inline-flex min-w-0 overflow-hidden rounded-lg border p-0"
                                                            aria-label={`${t('adjustments.direction')} ${l.name}`}
                                                        >
                                                            <legend className="sr-only">
                                                                {t(
                                                                    'adjustments.direction',
                                                                )}
                                                            </legend>
                                                            <button
                                                                type="button"
                                                                aria-pressed={
                                                                    l.direction ===
                                                                    -1
                                                                }
                                                                onClick={() =>
                                                                    setLine(i, {
                                                                        direction:
                                                                            -1,
                                                                    })
                                                                }
                                                                className={cn(
                                                                    'inline-flex min-h-11 items-center gap-1 px-2.5 text-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 md:min-h-8',
                                                                    l.direction ===
                                                                        -1
                                                                        ? 'bg-danger-soft font-bold text-destructive'
                                                                        : 'hover:bg-muted',
                                                                )}
                                                            >
                                                                <Minus
                                                                    className="size-3.5"
                                                                    aria-hidden="true"
                                                                />
                                                                {t(
                                                                    'adjustments.remove_stock',
                                                                )}
                                                            </button>
                                                            <button
                                                                type="button"
                                                                aria-pressed={
                                                                    l.direction ===
                                                                    1
                                                                }
                                                                onClick={() =>
                                                                    setLine(i, {
                                                                        direction: 1,
                                                                    })
                                                                }
                                                                className={cn(
                                                                    'inline-flex min-h-11 items-center gap-1 border-l px-2.5 text-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 md:min-h-8',
                                                                    l.direction ===
                                                                        1
                                                                        ? 'bg-success-soft font-bold text-success'
                                                                        : 'hover:bg-muted',
                                                                )}
                                                            >
                                                                <Plus
                                                                    className="size-3.5"
                                                                    aria-hidden="true"
                                                                />
                                                                {t(
                                                                    'adjustments.add_stock',
                                                                )}
                                                            </button>
                                                        </fieldset>
                                                    </td>
                                                    <td className="px-3 py-1.5">
                                                        <QtyStepper
                                                            value={l.qty}
                                                            min={1}
                                                            onChange={(v) =>
                                                                setLine(i, {
                                                                    qty: v,
                                                                })
                                                            }
                                                            aria-label={`${t('purchases.qty')} ${l.name}`}
                                                        />
                                                    </td>
                                                    <td
                                                        className={cn(
                                                            'num px-3 py-1.5 text-right font-mono font-bold',
                                                            after < 0 &&
                                                                'text-destructive',
                                                        )}
                                                    >
                                                        {digits.qty(after)}
                                                        {after < 0 ? (
                                                            <span className="block text-[10px] font-semibold">
                                                                {t(
                                                                    'adjustments.below_zero',
                                                                )}
                                                            </span>
                                                        ) : null}
                                                    </td>
                                                    {canCost ? (
                                                        <>
                                                            <td className="num px-3 py-1.5 text-right text-muted-foreground">
                                                                {digits.money(
                                                                    l.cost,
                                                                )}
                                                            </td>
                                                            <td
                                                                className={cn(
                                                                    'num px-3 py-1.5 text-right font-bold',
                                                                    net(l) < 0
                                                                        ? 'text-destructive'
                                                                        : 'text-success',
                                                                )}
                                                            >
                                                                {signed(net(l))}
                                                            </td>
                                                        </>
                                                    ) : null}
                                                    <td className="px-2 py-1.5 text-right">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-11 text-destructive hover:bg-danger-soft hover:text-destructive md:size-8"
                                                            aria-label={`${t('purchases.remove_line')} ${l.name}`}
                                                            onClick={() =>
                                                                setLines(
                                                                    (prev) =>
                                                                        prev.filter(
                                                                            (
                                                                                _,
                                                                                idx,
                                                                            ) =>
                                                                                idx !==
                                                                                i,
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
                            </div>
                        )}
                    </section>

                    <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-3">
                        <Button
                            asChild
                            type="button"
                            variant="outline"
                            className="min-h-11 md:min-h-9"
                        >
                            <Link href="/app/adjustments">
                                {t('common.cancel')}
                            </Link>
                        </Button>
                        <Button
                            type="submit"
                            className="min-h-11 font-bold md:min-h-9"
                            disabled={!ready}
                        >
                            <Check aria-hidden="true" />{' '}
                            {t('adjustments.apply')}
                            <Kbd keys="mod+enter" onButton />
                        </Button>
                    </div>
                </form>
            </Page>
        </>
    );
}
