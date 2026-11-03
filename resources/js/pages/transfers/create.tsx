import { Head, Link } from '@inertiajs/react';
import { Page, PageHeader } from '@/components/page-header';
import {
    ArrowLeft,
    ArrowLeftRight,
    ArrowRight,
    Plus,
    Trash2,
} from 'lucide-react';
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

type BranchRef = { id: number; code: string; name: string };
type Line = {
    product_id: number;
    name: string;
    sku: string;
    qty: number;
    /** Milli-units on hand at the source branch when the line was added. */
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
const branchLabel = (b: BranchRef) => `${b.name} [${b.code}]`;

/** The mockup "Inter-Branch Stock Transfer" dialog as a page: from (this branch) → to, items table, dispatch note, gate pass summary, Generate Gate Pass. */
export default function TransferCreate({
    from,
    branches,
    prefill = null,
    canCost = false,
}: {
    from: BranchRef;
    branches: BranchRef[];
    /** The stock list's row "Transfer" opens this page with that product as the first line. */
    prefill?: Prefill | null;
    canCost?: boolean;
}) {
    const { t } = useT();
    const digits = useDigits();
    const { submit, processing, errors } = useFormSubmit();
    const scan = useRef<HTMLInputElement>(null);
    const [to, setTo] = useState<string>(
        branches[0] ? String(branches[0].id) : '',
    );
    const [note, setNote] = useState('');
    const [lines, setLines] = useState<Line[]>(() =>
        prefill
            ? [
                  {
                      product_id: prefill.id,
                      name: label(prefill),
                      sku: prefill.sku,
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

    const destination = branches.find((b) => String(b.id) === to) ?? null;
    const pieces = lines.reduce((sum, l) => sum + l.qty, 0);
    const value = lines.reduce((sum, l) => sum + timesQty(l.cost, l.qty), 0);
    const exceeds = lines.some((l) => l.qty > l.on_hand);
    const ready = !processing && lines.length > 0 && to !== '' && !exceeds;
    const save = () =>
        submit('post', '/app/transfers', {
            to_branch_id: Number(to),
            note: note || null,
            items: lines.map((l) => ({ product_id: l.product_id, qty: l.qty })),
        });

    useHotkeys('form', [
        {
            id: 'transfer.save',
            keys: 'mod+enter',
            labelKey: 'transfers.generate',
            allowInInput: true,
            handler: (e) => {
                e.preventDefault();
                if (ready) save();
            },
        },
        {
            id: 'transfer.scan',
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

    return (
        <>
            <Head title={t('transfers.create_title')} />
            <Page className="max-w-6xl">
                <div className="flex items-center gap-3">
                    <Button
                        asChild
                        variant="outline"
                        size="icon"
                        className="size-11 md:size-9"
                    >
                        <Link
                            href="/app/transfers"
                            aria-label={t('transfers.back')}
                        >
                            <ArrowLeft aria-hidden="true" />
                        </Link>
                    </Button>
                    <PageHeader
                        className="flex-1"
                        title={
                            <span className="inline-flex items-center gap-2">
                                <ArrowLeftRight
                                    className="size-5 text-primary"
                                    aria-hidden="true"
                                />
                                {t('transfers.create_title')}
                            </span>
                        }
                        subtitle={t('transfers.create_subtitle')}
                    />
                </div>

                <form
                    className="grid gap-3 text-xs lg:grid-cols-3"
                    noValidate
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (ready) save();
                    }}
                >
                    <div className="flex flex-col gap-3 lg:col-span-2">
                        <div className="grid gap-3 rounded-xl border bg-surface-sunken p-3 sm:grid-cols-2">
                            <FormField
                                label={`${t('transfers.from_branch')} *`}
                            >
                                <Input
                                    readOnly
                                    className="bg-surface font-semibold"
                                    value={branchLabel(from)}
                                />
                            </FormField>
                            <FormField
                                label={`${t('transfers.to_branch')} *`}
                                error={errors['to_branch_id']}
                                hint={
                                    branches.length === 0
                                        ? t('transfers.no_destination')
                                        : undefined
                                }
                            >
                                <NativeSelect
                                    className="bg-surface font-semibold"
                                    value={to}
                                    required
                                    disabled={branches.length === 0}
                                    onChange={(e) => setTo(e.target.value)}
                                >
                                    {branches.map((b) => (
                                        <option key={b.id} value={b.id}>
                                            {branchLabel(b)}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                        </div>

                        <section
                            aria-labelledby="ts-title"
                            className="space-y-2 rounded-xl border-2 border-dashed bg-surface p-3.5"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h2
                                    id="ts-title"
                                    className="flex items-center gap-1.5 text-xs font-bold text-foreground"
                                >
                                    <Plus
                                        className="size-3.5 text-primary"
                                        aria-hidden="true"
                                    />
                                    {t('transfers.item')}
                                </h2>
                                <span className="text-[10px] text-muted-foreground">
                                    {t('transfers.scan_hint')}{' '}
                                    <Kbd keys="f2" className="ml-1" />
                                </span>
                            </div>
                            <ScanField
                                ref={scan}
                                lookupUrl="/app/transfers/lookup"
                                onPick={onPick}
                            />
                            {lineError ? (
                                <p role="alert" className="text-destructive">
                                    {lineError}
                                </p>
                            ) : null}
                        </section>

                        <section
                            aria-labelledby="tl-title"
                            className="overflow-hidden rounded-xl border bg-surface"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b bg-surface-sunken p-2.5 text-xs font-bold">
                                <h2 id="tl-title">
                                    {t('transfers.items')}{' '}
                                    <span className="num font-normal text-muted-foreground">
                                        ·{' '}
                                        {t('adjustments.lines_count', {
                                            n: digits.n(lines.length),
                                        })}
                                    </span>
                                </h2>
                            </div>
                            {lines.length === 0 ? (
                                <p className="p-6 text-center text-muted-foreground">
                                    {t('transfers.scan_hint')}
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full border-collapse text-left text-xs">
                                        <caption className="sr-only">
                                            {t('transfers.items')}
                                        </caption>
                                        <thead className="border-b bg-surface-sunken">
                                            <tr>
                                                <th scope="col" className={TH}>
                                                    {t('transfers.col_product')}
                                                </th>
                                                <th scope="col" className={TH}>
                                                    {t('transfers.col_sku')}
                                                </th>
                                                <th
                                                    scope="col"
                                                    className={cn(
                                                        TH,
                                                        'text-right',
                                                    )}
                                                >
                                                    {t('transfers.col_on_hand')}
                                                </th>
                                                <th scope="col" className={TH}>
                                                    {t('transfers.col_qty')}
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
                                                                'transfers.col_unit_cost',
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
                                                                'transfers.col_total',
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
                                                const over = l.qty > l.on_hand;

                                                return (
                                                    <tr
                                                        key={l.product_id}
                                                        className="hover:bg-surface-sunken"
                                                    >
                                                        <th
                                                            scope="row"
                                                            className="px-3 py-1.5 text-left font-semibold"
                                                        >
                                                            {l.name}
                                                        </th>
                                                        <td className="num px-3 py-1.5 font-mono text-muted-foreground">
                                                            {l.sku}
                                                        </td>
                                                        <td
                                                            className={cn(
                                                                'num px-3 py-1.5 text-right font-mono',
                                                                over &&
                                                                    'text-destructive',
                                                            )}
                                                        >
                                                            {digits.qty(
                                                                l.on_hand,
                                                            )}
                                                            {over ? (
                                                                <span className="block text-[10px] font-semibold">
                                                                    {t(
                                                                        'transfers.exceeds',
                                                                    )}
                                                                </span>
                                                            ) : null}
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
                                                                aria-label={`${t('transfers.qty')} ${l.name}`}
                                                            />
                                                        </td>
                                                        {canCost ? (
                                                            <>
                                                                <td className="num px-3 py-1.5 text-right text-muted-foreground">
                                                                    {digits.money(
                                                                        l.cost,
                                                                    )}
                                                                </td>
                                                                <td className="num px-3 py-1.5 text-right font-bold">
                                                                    {digits.money(
                                                                        timesQty(
                                                                            l.cost,
                                                                            l.qty,
                                                                        ),
                                                                    )}
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
                                                                        (
                                                                            prev,
                                                                        ) =>
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

                        <FormField
                            label={t('transfers.note')}
                            error={errors['note']}
                        >
                            <Input
                                value={note}
                                placeholder={t('transfers.note_placeholder')}
                                onChange={(e) => setNote(e.target.value)}
                            />
                        </FormField>
                    </div>

                    {/* Gate pass summary */}
                    <aside
                        aria-labelledby="gp-title"
                        className="flex h-fit flex-col gap-3 rounded-xl border bg-surface p-4 shadow-xs lg:sticky lg:top-4"
                    >
                        <h2
                            id="gp-title"
                            className="text-xs font-bold tracking-wider text-foreground uppercase"
                        >
                            {t('transfers.gate_pass')}
                        </h2>
                        <div className="rounded-lg border bg-surface-sunken p-2.5">
                            <div className="text-[10px] font-semibold text-muted-foreground uppercase">
                                {t('transfers.summary_route')}
                            </div>
                            <div className="mt-1 flex flex-wrap items-center gap-1.5 font-semibold">
                                <span>{branchLabel(from)}</span>
                                <ArrowRight
                                    className="size-3.5 text-primary"
                                    aria-hidden="true"
                                />
                                <span className="text-primary">
                                    {destination
                                        ? branchLabel(destination)
                                        : '—'}
                                </span>
                            </div>
                        </div>
                        <dl className="grid gap-2">
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">
                                    {t('transfers.summary_lines')}
                                </dt>
                                <dd className="num font-bold">
                                    {digits.n(lines.length)}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">
                                    {t('transfers.summary_pcs')}
                                </dt>
                                <dd className="num font-bold">
                                    {digits.qty(pieces)}
                                </dd>
                            </div>
                            {canCost ? (
                                <div className="flex justify-between border-t pt-2 text-sm font-bold">
                                    <dt>{t('transfers.summary_value')}</dt>
                                    <dd className="num text-primary">
                                        {digits.money(value)}
                                    </dd>
                                </div>
                            ) : null}
                        </dl>
                        {note ? (
                            <p className="rounded-lg border bg-surface-sunken p-2 text-[11px] text-muted-foreground">
                                {note}
                            </p>
                        ) : null}
                        <div className="grid gap-2 border-t pt-3">
                            <Button
                                type="submit"
                                className="min-h-11 font-bold md:min-h-9"
                                disabled={!ready}
                            >
                                <ArrowLeftRight aria-hidden="true" />{' '}
                                {t('transfers.generate')}
                                <Kbd keys="mod+enter" onButton />
                            </Button>
                            <Button
                                asChild
                                type="button"
                                variant="outline"
                                className="min-h-11 md:min-h-9"
                            >
                                <Link href="/app/transfers">
                                    {t('common.cancel')}
                                </Link>
                            </Button>
                        </div>
                    </aside>
                </form>
            </Page>
        </>
    );
}
