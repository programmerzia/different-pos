import { Head, Link, router } from '@inertiajs/react';
import { Page, PageHeader } from '@/components/page-header';
import { AlertTriangle, ArrowLeft, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Kbd } from '@/components/kbd';
import { MoneyInput } from '@/components/money-input';
import {
    ReplacementCart,
    type CartLine,
} from '@/components/returns/replacement-cart';
import { NativeSelect } from '@/components/native-select';
import { QtyStepper } from '@/components/qty-stepper';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { navigateTo } from '@/command/navigate';
import type { Paisa } from '@/engine/money';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import {
    checkTender,
    exchangeNeeds,
    exchangeResult,
    giveBackValue,
    parseGiveBack,
    settlementSummary,
    shortLines,
    type ExchangeQuote,
    type Settlement,
} from '@/lib/exchange-settlement';
import { useExchangeQuote } from '@/lib/use-exchange-quote';
import { cn } from '@/lib/utils';
import {
    returnValue,
    salesUrl,
    type CustomerSummary,
    type MoneyMethod,
} from '@/lib/sales';

type Candidate = {
    id: number;
    number: string;
    sold_at: string | null;
    customer: CustomerSummary | null;
    items: number;
    total: number;
    due: number;
    returned: number;
};

type SaleLine = {
    id: number;
    product_id: number;
    name: string;
    sku: string;
    qty: number;
    qty_returned: number;
    returnable: number;
    unit_price: number;
    payable: number;
};

type ReturnableSale = {
    id: number;
    number: string;
    status: string;
    sold_at: string | null;
    customer: CustomerSummary | null;
    total: number;
    paid: number;
    due: number;
    returned_total: number;
    window: { days: number | null; age: number; expired: boolean };
    lines: SaleLine[];
};

type Props = {
    mode: 'return' | 'exchange';
    q: string;
    candidates: Candidate[];
    sale: ReturnableSale | null;
    methods: MoneyMethod[];
    reasons: string[];
    can: { exchange: boolean };
};

export default function ReturnCreate(props: Props) {
    const { t } = useT();

    return (
        <>
            <Head
                title={
                    props.mode === 'exchange'
                        ? t('returns.new_exchange')
                        : t('returns.new')
                }
            />
            <Page>
                <div className="flex items-center gap-3">
                    <Button
                        asChild
                        variant="outline"
                        size="icon"
                        className="size-11 md:size-9"
                    >
                        <Link
                            href={salesUrl.returnsIndex}
                            aria-label={t('returns.back')}
                        >
                            <ArrowLeft aria-hidden="true" />
                        </Link>
                    </Button>
                    <PageHeader
                        className="flex-1"
                        title={
                            props.mode === 'exchange'
                                ? t('returns.new_exchange')
                                : t('returns.new')
                        }
                    />
                </div>
                {props.sale ? (
                    <ReturnForm
                        key={props.sale.id}
                        {...props}
                        sale={props.sale}
                    />
                ) : (
                    <SalePicker {...props} />
                )}
            </Page>
        </>
    );
}

function SalePicker({ mode, q, candidates }: Props) {
    const { t } = useT();
    const digits = useDigits();
    const [text, setText] = useState(q);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const base =
        mode === 'exchange'
            ? salesUrl.exchangeCreate()
            : salesUrl.returnCreate();

    useEffect(
        () => () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
        },
        [],
    );

    const search = (value: string) => {
        setText(value);
        if (timer.current) {
            clearTimeout(timer.current);
        }
        timer.current = setTimeout(
            () =>
                router.get(base, value.trim() ? { q: value.trim() } : {}, {
                    preserveState: true,
                    replace: true,
                    only: ['candidates', 'q'],
                }),
            250,
        );
    };

    const pick = (id: number) => router.get(base, { sale: id });

    return (
        <section className="grid max-w-3xl gap-3" aria-labelledby="pick-sale">
            <h2 id="pick-sale" className="text-lg font-semibold">
                {t('returns.pick_sale')}
            </h2>
            <div className="relative">
                <Search
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    id="page-search"
                    type="search"
                    autoFocus
                    className="min-h-11 pl-9"
                    value={text}
                    placeholder={t('returns.pick_placeholder')}
                    aria-label={t('returns.pick_placeholder')}
                    onChange={(e) => search(e.target.value)}
                    onKeyDown={(e) => {
                        const first = candidates[0];
                        if (e.key === 'Enter' && first) {
                            e.preventDefault();
                            pick(first.id);
                        }
                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            document
                                .querySelector<HTMLElement>('[data-candidate]')
                                ?.focus();
                        }
                    }}
                />
            </div>
            {text.trim().length >= 2 && candidates.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('returns.no_candidates')}
                </p>
            ) : null}
            <ul className="grid gap-2" aria-label={t('returns.candidates')}>
                {candidates.map((c) => (
                    <li key={c.id}>
                        <button
                            type="button"
                            data-candidate
                            aria-label={`${c.number} ${c.customer && !c.customer.walk_in ? c.customer.name : t('sales.walk_in')} ${digits.money(c.total)}`}
                            className="flex min-h-11 w-full items-center justify-between gap-3 rounded-lg border bg-card p-3 text-left hover:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            onClick={() => pick(c.id)}
                            onKeyDown={(e) => {
                                const li = e.currentTarget.parentElement;
                                if (e.key === 'ArrowDown') {
                                    e.preventDefault();
                                    li?.nextElementSibling
                                        ?.querySelector<HTMLElement>('button')
                                        ?.focus();
                                }
                                if (e.key === 'ArrowUp') {
                                    e.preventDefault();
                                    const prev =
                                        li?.previousElementSibling?.querySelector<HTMLElement>(
                                            'button',
                                        );
                                    (
                                        prev ??
                                        document.getElementById('page-search')
                                    )?.focus();
                                }
                            }}
                        >
                            <span>
                                <span className="num font-semibold text-primary">
                                    {c.number}
                                </span>
                                <span className="num ml-2 text-xs text-muted-foreground">
                                    {digits.n(c.sold_at ?? '')}
                                </span>
                                <span className="block text-sm">
                                    {c.customer && !c.customer.walk_in
                                        ? `${c.customer.name}${c.customer.shop_name ? ` · ${c.customer.shop_name}` : ''}`
                                        : t('sales.walk_in')}
                                </span>
                            </span>
                            <span className="text-right text-sm">
                                <span className="num block font-semibold">
                                    {digits.money(c.total)}
                                </span>
                                <span className="num text-xs text-muted-foreground">
                                    {t('returns.items_count', {
                                        n: digits.n(c.items),
                                    })}
                                </span>
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function ReturnForm({
    mode,
    sale,
    methods,
    reasons,
    can,
}: Props & { sale: ReturnableSale }) {
    const { t } = useT();
    const digits = useDigits();
    const walkIn = sale.customer === null || sale.customer.walk_in;
    const [qty, setQty] = useState<Record<number, number>>({});
    const [restock, setRestock] = useState<Record<number, boolean>>({});
    const [settlement, setSettlement] = useState<Settlement>(
        mode === 'exchange' && can.exchange ? 'exchange' : 'refund',
    );
    const [refundMethod, setRefundMethod] = useState<number | null>(
        methods[0]?.id ?? null,
    );
    const [surplus, setSurplus] = useState<'refund' | 'credit'>('refund');
    const [reason, setReason] = useState(
        mode === 'exchange' ? 'size' : (reasons[0] ?? 'other'),
    );
    const [note, setNote] = useState('');
    const [cart, setCart] = useState<CartLine[]>([]);
    const [tenderMethod, setTenderMethod] = useState<number | null>(
        methods[0]?.id ?? null,
    );
    const [typedAmount, setTypedAmount] = useState<Paisa | null>(null);
    const [tenderRef, setTenderRef] = useState('');
    const [submitted, setSubmitted] = useState<{
        key: string;
        errors: Record<string, string>;
    } | null>(null);
    const [busy, setBusy] = useState(false);

    const chosen = sale.lines.filter((l) => (qty[l.id] ?? 0) > 0);
    const value = chosen.reduce(
        (sum, l) => sum + returnValue(l.payable, qty[l.id] ?? 0, l.qty),
        0,
    );
    const exchanging = settlement === 'exchange';
    const summary = settlementSummary(settlement, value, 0);
    const settlements: { id: Settlement; disabled: boolean }[] = [
        { id: 'refund', disabled: false },
        { id: 'credit', disabled: walkIn },
        ...(can.exchange ? [{ id: 'exchange' as const, disabled: false }] : []),
    ];

    const lines = chosen.map((l) => ({
        sale_item_id: l.id,
        qty: qty[l.id] ?? 0,
        restock: restock[l.id] ?? true,
    }));
    const items = cart.map((c) => ({ product_id: c.product_id, qty: c.qty }));
    const needs = exchangeNeeds(chosen.length, cart.length);
    // The exact figures come from the server; the screen never adds up an exchange itself.
    const quoteState = useExchangeQuote(
        exchanging && !sale.window.expired && needs.length === 0
            ? { sale_id: sale.id, lines, items }
            : null,
    );
    const quote = quoteState.quote;
    const pays = quote?.pays ?? 0;

    // A new amount to pay (the cart or the return changed) starts the payment over.
    const [seenPays, setSeenPays] = useState(pays);
    if (seenPays !== pays) {
        setSeenPays(pays);
        setTypedAmount(null);
    }
    const received = typedAmount ?? pays;
    const tenderMethodRow = methods.find((m) => m.id === tenderMethod) ?? null;
    const tender = checkTender({
        pays,
        received,
        method: tenderMethodRow,
        reference: tenderRef,
        walkIn,
    });
    const short = quote ? shortLines(quote) : [];

    const blocked =
        sale.window.expired ||
        chosen.length === 0 ||
        (exchanging &&
            (needs.length > 0 ||
                quote === null ||
                quoteState.error !== null ||
                short.length > 0 ||
                (pays > 0 && tender.problem !== null)));

    // A server refusal is about the form as it was sent: any change to the form clears it.
    const formKey = JSON.stringify([
        lines,
        items,
        settlement,
        refundMethod,
        surplus,
        reason,
        note,
        tenderMethod,
        received,
        tenderRef,
    ]);
    const errors = submitted?.key === formKey ? submitted.errors : {};

    const submit = () => {
        if (blocked || busy) {
            return;
        }
        setSubmitted(null);
        const options = {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onError: (e: Record<string, string>) =>
                setSubmitted({ key: formKey, errors: e }),
        };
        if (exchanging && quote) {
            router.post(
                '/app/exchanges',
                {
                    sale_id: sale.id,
                    lines,
                    items,
                    tenders:
                        pays > 0 && received > 0 && tenderMethod
                            ? [
                                  {
                                      payment_method_id: tenderMethod,
                                      amount: received,
                                      reference: tenderRef.trim() || null,
                                  },
                              ]
                            : [],
                    surplus_mode: surplus,
                    refund_method_id:
                        surplus === 'refund' ? refundMethod : null,
                    reason,
                    note: note || null,
                    expected_total: quote.replacement_total,
                },
                options,
            );
        } else {
            router.post(
                '/app/returns',
                {
                    sale_id: sale.id,
                    lines,
                    refund_mode: settlement,
                    refund_method_id:
                        settlement === 'refund' ? refundMethod : null,
                    reason,
                    note: note || null,
                },
                options,
            );
        }
    };

    useHotkeys('form', [
        {
            id: 'return.submit',
            keys: 'mod+enter',
            labelKey: 'returns.save',
            allowInInput: true,
            handler: (e) => {
                e.preventDefault();
                submit();
            },
        },
        {
            id: 'return.save',
            keys: 'mod+s',
            labelKey: 'returns.save',
            allowInInput: true,
            handler: (e) => {
                e.preventDefault();
                submit();
            },
        },
        {
            id: 'return.all',
            keys: 'alt+a',
            labelKey: 'returns.return_all',
            allowInInput: true,
            handler: () =>
                setQty(
                    Object.fromEntries(
                        sale.lines.map((l) => [l.id, l.returnable]),
                    ),
                ),
        },
        {
            id: 'return.cancel',
            keys: 'escape',
            labelKey: 'returns.change_sale',
            handler: () =>
                navigateTo(
                    mode === 'exchange'
                        ? salesUrl.exchangeCreate()
                        : salesUrl.returnCreate(),
                ),
        },
    ]);

    const errorList = Object.entries(errors);

    return (
        <form
            className="grid grid-cols-1 gap-4 lg:grid-cols-3"
            onSubmit={(e) => {
                e.preventDefault();
                submit();
            }}
        >
            <div className="flex flex-col gap-4 lg:col-span-2">
                <section
                    className="rounded-lg border bg-card p-4"
                    aria-labelledby="sale-lines"
                >
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 id="sale-lines" className="font-semibold">
                                {t('returns.lines_of', { number: sale.number })}
                            </h2>
                            <p className="num text-sm text-muted-foreground">
                                {digits.n(sale.sold_at ?? '')} ·{' '}
                                {walkIn
                                    ? t('sales.walk_in')
                                    : `${sale.customer?.name ?? ''}${sale.customer?.shop_name ? ` · ${sale.customer.shop_name}` : ''}`}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11 md:min-h-9"
                            onClick={() =>
                                setQty(
                                    Object.fromEntries(
                                        sale.lines.map((l) => [
                                            l.id,
                                            l.returnable,
                                        ]),
                                    ),
                                )
                            }
                        >
                            {t('returns.return_all')}{' '}
                            <Kbd
                                keys="alt+a"
                                className="ml-1 hidden md:inline-flex"
                            />
                        </Button>
                    </div>
                    {sale.window.expired ? (
                        <p
                            role="alert"
                            className="mb-3 flex items-center gap-2 rounded-md bg-warning-soft p-2 text-sm text-warning"
                        >
                            <AlertTriangle
                                className="size-4"
                                aria-hidden="true"
                            />
                            {t('returns.window_closed', {
                                days: digits.n(sale.window.days ?? 0),
                            })}
                        </p>
                    ) : null}
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b text-muted-foreground">
                                <tr>
                                    <th scope="col" className="py-2 text-left">
                                        {t('sales.item')}
                                    </th>
                                    <th scope="col" className="py-2 text-right">
                                        {t('returns.sold')}
                                    </th>
                                    <th scope="col" className="py-2 text-right">
                                        {t('returns.returnable')}
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-2 text-center"
                                    >
                                        {t('returns.qty')}
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-2 text-center"
                                    >
                                        {t('returns.restock')}
                                    </th>
                                    <th scope="col" className="py-2 text-right">
                                        {t('returns.value')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {sale.lines.map((l) => (
                                    <tr
                                        key={l.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="py-2">
                                            <div className="font-medium">
                                                {l.name}
                                            </div>
                                            <div className="num text-xs text-muted-foreground">
                                                {l.sku} ·{' '}
                                                {digits.money(l.unit_price)}
                                            </div>
                                        </td>
                                        <td className="num py-2 text-right">
                                            {digits.qty(l.qty)}
                                        </td>
                                        <td className="num py-2 text-right">
                                            {digits.qty(l.returnable)}
                                        </td>
                                        <td className="py-2">
                                            <div className="flex justify-center">
                                                <QtyStepper
                                                    value={qty[l.id] ?? 0}
                                                    onChange={(v) =>
                                                        setQty((s) => ({
                                                            ...s,
                                                            [l.id]: v,
                                                        }))
                                                    }
                                                    min={0}
                                                    max={l.returnable}
                                                    step={1000}
                                                    integer={
                                                        l.returnable % 1000 ===
                                                            0 &&
                                                        l.qty % 1000 === 0
                                                    }
                                                    aria-label={t(
                                                        'returns.qty_for',
                                                        {
                                                            name: l.name,
                                                        },
                                                    )}
                                                />
                                            </div>
                                        </td>
                                        <td className="py-2 text-center">
                                            <Checkbox
                                                className="size-5"
                                                checked={restock[l.id] ?? true}
                                                onCheckedChange={(v) =>
                                                    setRestock((s) => ({
                                                        ...s,
                                                        [l.id]: v === true,
                                                    }))
                                                }
                                                aria-label={t(
                                                    'returns.restock_for',
                                                    {
                                                        name: l.name,
                                                    },
                                                )}
                                                disabled={l.returnable === 0}
                                            />
                                        </td>
                                        <td className="num py-2 text-right font-semibold">
                                            {(qty[l.id] ?? 0) > 0
                                                ? digits.money(
                                                      returnValue(
                                                          l.payable,
                                                          qty[l.id] ?? 0,
                                                          l.qty,
                                                      ),
                                                  )
                                                : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                {exchanging ? (
                    <ReplacementCart
                        cart={cart}
                        setCart={setCart}
                        quoteLines={quote?.lines ?? null}
                        quoteLoading={quoteState.loading}
                    />
                ) : null}
            </div>

            <aside className="flex flex-col gap-4 lg:sticky lg:top-3 lg:max-h-[calc(100dvh-1.5rem)] lg:self-start lg:overflow-y-auto">
                <section
                    className="grid gap-3 rounded-lg border bg-card p-4"
                    aria-labelledby="settle-heading"
                >
                    <h2 id="settle-heading" className="font-semibold">
                        {t('returns.settlement')}
                    </h2>
                    <div
                        role="radiogroup"
                        aria-labelledby="settle-heading"
                        className="grid gap-2 sm:grid-cols-3"
                    >
                        {settlements.map((m) => (
                            <label
                                key={m.id}
                                className={cn(
                                    'flex min-h-11 cursor-pointer items-center justify-center rounded-md border px-3 text-sm font-semibold transition-colors has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-50 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50 md:min-h-9',
                                    settlement === m.id
                                        ? 'border-primary bg-primary-soft text-primary'
                                        : 'bg-surface hover:bg-surface-sunken',
                                )}
                            >
                                <input
                                    type="radio"
                                    name="settlement"
                                    value={m.id}
                                    className="sr-only"
                                    checked={settlement === m.id}
                                    disabled={m.disabled}
                                    onChange={() => setSettlement(m.id)}
                                />
                                {t(`returns.mode_${m.id}`)}
                            </label>
                        ))}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {sale.due > 0 && !exchanging
                            ? t('returns.due_first', {
                                  due: digits.money(sale.due),
                              })
                            : t(`returns.hint_${settlement}`)}
                    </p>
                    {settlement === 'refund' ? (
                        <div className="grid gap-1.5">
                            <Label htmlFor="refund-method">
                                {t('returns.refund_method')}
                            </Label>
                            <NativeSelect
                                id="refund-method"
                                value={refundMethod ?? ''}
                                onChange={(e) =>
                                    setRefundMethod(
                                        Number(e.target.value) || null,
                                    )
                                }
                            >
                                {methods.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </div>
                    ) : null}
                    <div className="grid gap-1.5">
                        <Label htmlFor="return-reason">
                            {t('returns.col_reason')}
                        </Label>
                        <NativeSelect
                            id="return-reason"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                        >
                            {reasons.map((r) => (
                                <option key={r} value={r}>
                                    {t(`returns.reason_${r}`)}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="return-note">{t('sales.note')}</Label>
                        <Input
                            id="return-note"
                            className="min-h-11 md:min-h-9"
                            value={note}
                            maxLength={1000}
                            onChange={(e) => setNote(e.target.value)}
                        />
                    </div>
                </section>

                <section
                    className="grid gap-2 rounded-lg border bg-card p-4 text-sm"
                    aria-labelledby="summary-heading"
                >
                    <h2 id="summary-heading" className="font-semibold">
                        {t('returns.summary')}
                    </h2>
                    {exchanging ? (
                        <ExchangeSummary
                            needs={needs}
                            loading={quoteState.loading}
                            error={quoteState.error}
                            quote={quote}
                        />
                    ) : (
                        <>
                            <dl className="grid grid-cols-[1fr_auto] gap-y-1">
                                <dt className="text-muted-foreground">
                                    {t('returns.value_estimate')}
                                </dt>
                                <dd className="num text-right font-semibold">
                                    {digits.money(value)}
                                </dd>
                                <dt
                                    className="border-t pt-1 font-bold"
                                    data-test="settlement-summary"
                                >
                                    {summary.kind === 'even'
                                        ? t('returns.even_exchange')
                                        : t('returns.customer_gets')}
                                </dt>
                                <dd className="num border-t pt-1 text-right font-bold text-primary">
                                    {digits.money(summary.amount)}
                                </dd>
                            </dl>
                            <p className="text-xs text-muted-foreground">
                                {t('returns.estimate_hint')}
                            </p>
                        </>
                    )}
                    {exchanging && quote && pays > 0 ? (
                        <div className="grid gap-2 border-t pt-2">
                            <Label htmlFor="tender-method">
                                {t('returns.pay_with')}
                            </Label>
                            <NativeSelect
                                id="tender-method"
                                value={tenderMethod ?? ''}
                                onChange={(e) =>
                                    setTenderMethod(
                                        Number(e.target.value) || null,
                                    )
                                }
                            >
                                {methods.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name}
                                    </option>
                                ))}
                            </NativeSelect>
                            <Label htmlFor="tender-amount">
                                {t('returns.amount_received')}
                            </Label>
                            <MoneyInput
                                id="tender-amount"
                                value={received}
                                onChange={(v) => setTypedAmount(v ?? 0)}
                            />
                            {tenderMethodRow?.requires_reference ? (
                                <Input
                                    className="min-h-11 md:min-h-9"
                                    value={tenderRef}
                                    placeholder={t('dues.reference')}
                                    aria-label={t('dues.reference')}
                                    onChange={(e) =>
                                        setTenderRef(e.target.value)
                                    }
                                />
                            ) : null}
                            {tender.change > 0 && tender.problem === null ? (
                                <p
                                    className="flex justify-between font-semibold"
                                    data-test="tender-change"
                                >
                                    <span>{t('returns.change')}</span>
                                    <span className="num">
                                        {digits.money(tender.change)}
                                    </span>
                                </p>
                            ) : null}
                            {tender.onAccount > 0 && tender.problem === null ? (
                                <p
                                    className="text-warning"
                                    data-test="tender-on-account"
                                >
                                    {t('returns.on_account', {
                                        amount: digits.money(tender.onAccount),
                                    })}
                                </p>
                            ) : null}
                            {tender.problem ? (
                                <p
                                    role="alert"
                                    className="text-destructive"
                                    data-test="tender-problem"
                                >
                                    {t(`returns.tender_${tender.problem}`, {
                                        pays: digits.money(pays),
                                    })}
                                </p>
                            ) : null}
                        </div>
                    ) : null}
                    {exchanging && quote && quote.gets_back > 0 ? (
                        <div className="grid gap-1.5 border-t pt-2">
                            <Label htmlFor="give-back">
                                {t('returns.give_back_as')}
                            </Label>
                            <NativeSelect
                                id="give-back"
                                value={giveBackValue(
                                    surplus === 'credit'
                                        ? { mode: 'credit' }
                                        : refundMethod === null
                                          ? null
                                          : {
                                                mode: 'refund',
                                                methodId: refundMethod,
                                            },
                                )}
                                onChange={(e) => {
                                    const next = parseGiveBack(e.target.value);
                                    if (next?.mode === 'credit') {
                                        setSurplus('credit');
                                    } else if (next) {
                                        setSurplus('refund');
                                        setRefundMethod(next.methodId);
                                    }
                                }}
                            >
                                {methods.map((m) => (
                                    <option key={m.id} value={`m${m.id}`}>
                                        {t('returns.give_back_refund', {
                                            method: m.name,
                                        })}
                                    </option>
                                ))}
                                {walkIn ? null : (
                                    <option value="credit">
                                        {t('returns.mode_credit')}
                                    </option>
                                )}
                            </NativeSelect>
                        </div>
                    ) : null}
                    {short.length > 0 ? (
                        <div role="alert" className="grid gap-1">
                            {short.map((l) => (
                                <InputError
                                    key={l.product_id}
                                    message={`${l.name}: ${
                                        (l.stock ?? 0) <= 0
                                            ? t('returns.out_of_stock')
                                            : t('returns.only_n_in_stock', {
                                                  n: digits.qty(l.stock ?? 0),
                                              })
                                    }`}
                                />
                            ))}
                        </div>
                    ) : null}
                    {errorList.length > 0 ? (
                        <div role="alert" className="grid gap-1">
                            {errorList.map(([key, message]) => (
                                <InputError key={key} message={message} />
                            ))}
                        </div>
                    ) : null}
                    <Button
                        type="submit"
                        className="mt-2 min-h-11"
                        disabled={blocked || busy}
                    >
                        {exchanging
                            ? t('returns.save_exchange')
                            : t('returns.save')}
                        <Kbd
                            keys="mod+enter"
                            className="ml-1 hidden md:inline-flex"
                        />
                    </Button>
                </section>
            </aside>
        </form>
    );
}

/** The exchange summary: only the server's exact figures, or what is still needed to get them. */
function ExchangeSummary({
    needs,
    loading,
    error,
    quote,
}: {
    needs: ('return' | 'replacement')[];
    loading: boolean;
    error: string | null;
    quote: ExchangeQuote | null;
}) {
    const { t } = useT();
    const digits = useDigits();

    if (needs.length > 0) {
        return (
            <div data-test="exchange-needs" className="grid gap-1">
                <p className="text-muted-foreground">
                    {t('returns.needs_heading')}
                </p>
                <ul className="list-disc pl-5">
                    {needs.map((n) => (
                        <li key={n}>{t(`returns.need_${n}`)}</li>
                    ))}
                </ul>
            </div>
        );
    }
    if (error !== null) {
        return (
            <p
                role="alert"
                className="text-destructive"
                data-test="quote-error"
            >
                {error || t('returns.quote_failed')}
            </p>
        );
    }
    if (loading || quote === null) {
        return (
            <output
                className="block text-muted-foreground"
                data-test="quote-loading"
            >
                {t('returns.calculating')}
            </output>
        );
    }

    const result = exchangeResult(quote);

    return (
        <>
            <dl className="grid grid-cols-[1fr_auto] gap-y-1">
                <dt className="text-muted-foreground">
                    {t('returns.items_returned')}
                </dt>
                <dd className="num text-right">
                    {digits.money(quote.return_value)}
                </dd>
                <dt className="text-muted-foreground">
                    {t('returns.replacement_items')}
                </dt>
                <dd className="num text-right">
                    {digits.money(quote.replacement_total)}
                </dd>
                {quote.replacement_tax > 0 ? (
                    <>
                        <dt className="pl-3 text-xs text-muted-foreground">
                            {t('returns.includes_vat')}
                        </dt>
                        <dd className="num text-right text-xs text-muted-foreground">
                            {digits.money(quote.replacement_tax)}
                        </dd>
                    </>
                ) : null}
                {quote.group_discount > 0 ? (
                    <>
                        <dt className="pl-3 text-xs text-muted-foreground">
                            {t('returns.after_group_discount')}
                        </dt>
                        <dd className="num text-right text-xs text-muted-foreground">
                            −{digits.money(quote.group_discount)}
                        </dd>
                    </>
                ) : null}
                {result.kind === 'surplus' ? (
                    <>
                        {result.dueCleared > 0 ? (
                            <>
                                <dt
                                    className="border-t pt-1 font-bold"
                                    data-test="settlement-summary"
                                >
                                    {t('returns.on_old_due')}
                                </dt>
                                <dd className="num border-t pt-1 text-right font-bold text-primary">
                                    {digits.money(result.dueCleared)}
                                </dd>
                            </>
                        ) : null}
                        {result.getsBack > 0 ? (
                            <>
                                <dt
                                    className={cn(
                                        'font-bold',
                                        result.dueCleared === 0 &&
                                            'border-t pt-1',
                                    )}
                                    data-test={
                                        result.dueCleared === 0
                                            ? 'settlement-summary'
                                            : undefined
                                    }
                                >
                                    {t('returns.customer_gets')}
                                </dt>
                                <dd
                                    className={cn(
                                        'num text-right font-bold text-primary',
                                        result.dueCleared === 0 &&
                                            'border-t pt-1',
                                    )}
                                >
                                    {digits.money(result.getsBack)}
                                </dd>
                            </>
                        ) : null}
                    </>
                ) : (
                    <>
                        <dt
                            className="border-t pt-1 font-bold"
                            data-test="settlement-summary"
                        >
                            {result.kind === 'pays'
                                ? t('returns.customer_pays')
                                : t('returns.even_exchange')}
                        </dt>
                        <dd className="num border-t pt-1 text-right font-bold text-primary">
                            {result.kind === 'pays'
                                ? digits.money(result.amount)
                                : null}
                        </dd>
                    </>
                )}
            </dl>
            {quote.original_due > 0 && quote.surplus === 0 ? (
                <p className="text-xs text-muted-foreground">
                    {t('returns.old_due_stays', {
                        due: digits.money(quote.original_due),
                    })}
                </p>
            ) : null}
        </>
    );
}
