import { Head, Link, router } from '@inertiajs/react';
import { Page, PageHeader } from '@/components/page-header';
import { AlertTriangle, ArrowLeft, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Kbd } from '@/components/kbd';
import { MoneyInput } from '@/components/money-input';
import { NativeSelect } from '@/components/native-select';
import { QtyStepper } from '@/components/qty-stepper';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { navigateTo } from '@/command/navigate';
import { timesQty, type Paisa } from '@/engine/money';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { apiRequest } from '@/lib/api';
import {
    giveBackValue,
    parseGiveBack,
    settlementSummary,
    type Settlement,
} from '@/lib/exchange-settlement';
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

type CartLine = {
    product_id: number;
    label: string;
    sku: string;
    price: Paisa;
    qty: number;
};
type ProductHit = { id: number; label: string; sub: string; price: number };

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
    const [tenderAmount, setTenderAmount] = useState<Paisa | null>(null);
    const [tenderRef, setTenderRef] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const chosen = sale.lines.filter((l) => (qty[l.id] ?? 0) > 0);
    const value = chosen.reduce(
        (sum, l) => sum + returnValue(l.payable, qty[l.id] ?? 0, l.qty),
        0,
    );
    const cartTotal = cart.reduce(
        (sum, c) => sum + timesQty(c.price, c.qty),
        0,
    );
    const difference = cartTotal - value;
    const exchanging = settlement === 'exchange';
    const summary = settlementSummary(settlement, value, cartTotal);
    const settlements: { id: Settlement; disabled: boolean }[] = [
        { id: 'refund', disabled: false },
        { id: 'credit', disabled: walkIn },
        ...(can.exchange ? [{ id: 'exchange' as const, disabled: false }] : []),
    ];
    const tender = tenderAmount ?? Math.max(0, difference);
    const blocked =
        sale.window.expired ||
        chosen.length === 0 ||
        (exchanging && cart.length === 0);

    const submit = () => {
        if (blocked || busy) {
            return;
        }
        const lines = chosen.map((l) => ({
            sale_item_id: l.id,
            qty: qty[l.id] ?? 0,
            restock: restock[l.id] ?? true,
        }));
        const options = {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onError: (e: Record<string, string>) => setErrors(e),
        };
        if (exchanging) {
            router.post(
                '/app/exchanges',
                {
                    sale_id: sale.id,
                    lines,
                    items: cart.map((c) => ({
                        product_id: c.product_id,
                        qty: c.qty,
                    })),
                    tenders:
                        difference > 0 && tender > 0 && tenderMethod
                            ? [
                                  {
                                      payment_method_id: tenderMethod,
                                      amount: tender,
                                      reference: tenderRef || null,
                                  },
                              ]
                            : [],
                    surplus_mode: surplus,
                    refund_method_id:
                        surplus === 'refund' ? refundMethod : null,
                    reason,
                    note: note || null,
                    expected_total: cartTotal,
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
            className="grid gap-4 lg:grid-cols-3"
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
                                                        { name: l.name },
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
                                                    { name: l.name },
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
                    <ReplacementCart cart={cart} setCart={setCart} />
                ) : null}
            </div>

            <aside className="flex flex-col gap-4">
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
                        {sale.due > 0
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
                    <dl className="grid grid-cols-[1fr_auto] gap-y-1">
                        <dt className="text-muted-foreground">
                            {t('returns.value_estimate')}
                        </dt>
                        <dd className="num text-right font-semibold">
                            {digits.money(value)}
                        </dd>
                        {exchanging ? (
                            <>
                                <dt className="text-muted-foreground">
                                    {t('returns.replacement_total')}
                                </dt>
                                <dd className="num text-right">
                                    {digits.money(cartTotal)}
                                </dd>
                            </>
                        ) : null}
                        <dt
                            className="border-t pt-1 font-bold"
                            data-test="settlement-summary"
                        >
                            {summary.kind === 'pays'
                                ? t('returns.customer_pays')
                                : summary.kind === 'gets'
                                  ? t('returns.customer_gets')
                                  : t('returns.even_exchange')}
                        </dt>
                        <dd className="num border-t pt-1 text-right font-bold text-primary">
                            {digits.money(summary.amount)}
                        </dd>
                    </dl>
                    <p className="text-xs text-muted-foreground">
                        {t('returns.estimate_hint')}
                    </p>
                    {exchanging && difference > 0 ? (
                        <div className="grid gap-2 border-t pt-2">
                            <Label htmlFor="tender-method">
                                {t('returns.pay_difference')}
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
                            <MoneyInput
                                id="tender-amount"
                                value={tender}
                                onChange={setTenderAmount}
                            />
                            {methods.find((m) => m.id === tenderMethod)
                                ?.requires_reference ? (
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
                        </div>
                    ) : null}
                    {exchanging && difference < 0 ? (
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

function ReplacementCart({
    cart,
    setCart,
}: {
    cart: CartLine[];
    setCart: (update: (lines: CartLine[]) => CartLine[]) => void;
}) {
    const { t } = useT();
    const digits = useDigits();
    const [text, setText] = useState('');
    const [hits, setHits] = useState<ProductHit[]>([]);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        const q = text.trim();
        if (q.length < 2) {
            return;
        }
        const controller = new AbortController();
        const timer = setTimeout(() => {
            apiRequest<{ products: ProductHit[] }>(
                `/app/api/search?q=${encodeURIComponent(q)}`,
                { signal: controller.signal },
            )
                .then((r) => {
                    setHits(r.products);
                    setFailed(false);
                })
                .catch(() => !controller.signal.aborted && setFailed(true));
        }, 200);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [text]);

    const visibleHits = text.trim().length >= 2 ? hits : [];

    const add = (hit: ProductHit) => {
        setCart((lines) => {
            const found = lines.find((l) => l.product_id === hit.id);

            return found
                ? lines.map((l) =>
                      l.product_id === hit.id ? { ...l, qty: l.qty + 1000 } : l,
                  )
                : [
                      ...lines,
                      {
                          product_id: hit.id,
                          label: hit.label,
                          sku: hit.sub,
                          price: hit.price,
                          qty: 1000,
                      },
                  ];
        });
        setText('');
        document.getElementById('replacement-search')?.focus();
    };

    const total = useMemo(
        () => cart.reduce((sum, c) => sum + timesQty(c.price, c.qty), 0),
        [cart],
    );

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
                    id="replacement-search"
                    type="search"
                    className="min-h-11 pl-9"
                    value={text}
                    placeholder={t('returns.product_search')}
                    aria-label={t('returns.product_search')}
                    onChange={(e) => setText(e.target.value)}
                    onKeyDown={(e) => {
                        const first = visibleHits[0];
                        if (e.key === 'Enter' && first) {
                            e.preventDefault();
                            add(first);
                        }
                    }}
                />
            </div>
            {failed ? (
                <p className="mt-2 text-sm text-destructive">
                    {t('palette.error')}
                </p>
            ) : null}
            {visibleHits.length > 0 ? (
                <ul
                    className="mt-2 grid gap-1"
                    aria-label={t('returns.product_results')}
                >
                    {visibleHits.map((hit) => (
                        <li key={hit.id}>
                            <button
                                type="button"
                                className="flex min-h-11 w-full items-center justify-between rounded-md border px-3 text-left text-sm hover:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                onClick={() => add(hit)}
                            >
                                <span>
                                    <span className="font-medium">
                                        {hit.label}
                                    </span>
                                    <span className="num ml-2 text-xs text-muted-foreground">
                                        {hit.sub}
                                    </span>
                                </span>
                                <span className="num flex items-center gap-2">
                                    {digits.money(hit.price)}
                                    <Plus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </span>
                            </button>
                        </li>
                    ))}
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
                        {cart.map((c) => (
                            <tr
                                key={c.product_id}
                                className="border-b last:border-0"
                            >
                                <td className="py-2">
                                    <div className="font-medium">{c.label}</div>
                                    <div className="num text-xs text-muted-foreground">
                                        {c.sku} · {digits.money(c.price)}
                                    </div>
                                </td>
                                <td className="py-2">
                                    <div className="flex justify-center">
                                        <QtyStepper
                                            value={c.qty}
                                            min={1000}
                                            step={1000}
                                            integer
                                            onChange={(v) =>
                                                setCart((lines) =>
                                                    lines.map((l) =>
                                                        l.product_id ===
                                                        c.product_id
                                                            ? { ...l, qty: v }
                                                            : l,
                                                    ),
                                                )
                                            }
                                            aria-label={t('returns.qty_for', {
                                                name: c.label,
                                            })}
                                        />
                                    </div>
                                </td>
                                <td className="num py-2 text-right font-semibold">
                                    {digits.money(timesQty(c.price, c.qty))}
                                </td>
                                <td className="py-2 text-right">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-11 md:size-8"
                                        aria-label={t('returns.remove_named', {
                                            name: c.label,
                                        })}
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
                        ))}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colSpan={2} className="pt-2 font-semibold">
                                {t('returns.replacement_total')}
                            </td>
                            <td
                                colSpan={2}
                                className="num pt-2 text-right font-bold"
                            >
                                {digits.money(total)}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            ) : (
                <p className="mt-3 text-sm text-muted-foreground">
                    {t('returns.cart_empty')}
                </p>
            )}
        </section>
    );
}
