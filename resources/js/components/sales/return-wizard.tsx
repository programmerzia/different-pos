import { router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    Banknote,
    Check,
    FileText,
    Repeat,
    Search,
    Undo2,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Kbd } from '@/components/kbd';
import { NativeSelect } from '@/components/native-select';
import { QtyStepper } from '@/components/qty-stepper';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogBar,
    DialogBody,
    DialogContent,
} from '@/components/ui/dialog';
import { navigateTo } from '@/command/navigate';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import {
    returnValue,
    salesUrl,
    type CustomerSummary,
    type MoneyMethod,
} from '@/lib/sales';
import { cn } from '@/lib/utils';

export type WizardCandidate = {
    id: number;
    number: string;
    sold_at: string | null;
    customer: CustomerSummary | null;
    items: number;
    total: number;
    due: number;
    returned: number;
};

export type WizardSale = {
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
    lines: {
        id: number;
        product_id: number;
        name: string;
        sku: string;
        qty: number;
        qty_returned: number;
        returnable: number;
        unit_price: number;
        payable: number;
    }[];
};

export type WizardData = {
    q: string;
    candidates: WizardCandidate[];
    sale: WizardSale | null;
    methods: MoneyMethod[];
    reasons: string[];
    can: { exchange: boolean };
};

export type WizardStep = 1 | 2 | 3 | 4;
type Settlement = 'refund' | 'credit' | 'exchange';

const STEPS: { step: WizardStep; key: string }[] = [
    { step: 1, key: 'returns.wizard_step_find' },
    { step: 2, key: 'returns.wizard_step_items' },
    { step: 3, key: 'returns.wizard_step_refund' },
    { step: 4, key: 'returns.wizard_step_confirm' },
];

/** The mockup stepper bar: current = primary soft, done = success soft, upcoming = sunken. */
export function WizardStepper({ step }: { step: WizardStep }) {
    const { t } = useT();

    return (
        <ol className="grid grid-cols-4 gap-2 border-b pb-2 text-center text-[11px] font-semibold">
            {STEPS.map((s) => (
                <li
                    key={s.step}
                    aria-current={s.step === step ? 'step' : undefined}
                    className={cn(
                        'rounded-lg border p-1.5',
                        s.step === step &&
                            'border-primary bg-primary-soft text-primary',
                        s.step < step &&
                            'border-success bg-success-soft text-success',
                        s.step > step &&
                            'bg-surface-sunken text-muted-foreground',
                    )}
                >
                    {s.step}. {t(s.key)}
                </li>
            ))}
        </ol>
    );
}

/**
 * Return & Exchange Wizard (mockup render_all_modals.py): 1 find the invoice, 2 pick lines, quantity
 * and condition (restock), 3 settlement (refund method / store credit / exchange) with the reason and
 * memo, 4 confirm. Saving posts to returns.store (the server values the return exactly). "Direct item
 * exchange" continues in the exchange page, which carries the replacement cart.
 */
export function ReturnWizard({
    open,
    data,
    loading,
    onSearch,
    onPickSale,
    onClose,
}: {
    open: boolean;
    data: WizardData | null;
    loading: boolean;
    onSearch: (q: string) => void;
    onPickSale: (saleId: number | null) => void;
    onClose: () => void;
}) {
    const { t } = useT();

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent layout="panel" size="2xl">
                {open ? (
                    <WizardBody
                        key={data?.sale?.id ?? 0}
                        data={data}
                        loading={loading}
                        onSearch={onSearch}
                        onPickSale={onPickSale}
                        onClose={onClose}
                    />
                ) : (
                    <span className="sr-only">{t('returns.wizard')}</span>
                )}
            </DialogContent>
        </Dialog>
    );
}

function WizardBody({
    data,
    loading,
    onSearch,
    onPickSale,
    onClose,
}: {
    data: WizardData | null;
    loading: boolean;
    onSearch: (q: string) => void;
    onPickSale: (saleId: number | null) => void;
    onClose: () => void;
}) {
    const { t } = useT();
    const digits = useDigits();
    const sale = data?.sale ?? null;
    const methods = data?.methods ?? [];
    const reasons = data?.reasons ?? [];
    const walkIn =
        sale === null || sale.customer === null || sale.customer.walk_in;

    const [step, setStep] = useState<WizardStep>(1);
    const [text, setText] = useState(data?.q ?? sale?.number ?? '');
    const [qty, setQty] = useState<Record<number, number>>({});
    const [restock, setRestock] = useState<Record<number, boolean>>({});
    const [settlement, setSettlement] = useState<Settlement>('refund');
    const [refundMethod, setRefundMethod] = useState<number | null>(
        methods[0]?.id ?? null,
    );
    const [reason, setReason] = useState(reasons[0] ?? 'other');
    const [note, setNote] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

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
        timer.current = setTimeout(() => onSearch(value.trim()), 250);
    };

    const lines = sale?.lines ?? [];
    const chosen = lines.filter((l) => (qty[l.id] ?? 0) > 0);
    const value = chosen.reduce(
        (sum, l) => sum + returnValue(l.payable, qty[l.id] ?? 0, l.qty),
        0,
    );
    const returnAll = () =>
        setQty(Object.fromEntries(lines.map((l) => [l.id, l.returnable])));

    const canNext =
        step === 1
            ? sale !== null && !sale.window.expired
            : step === 2
              ? chosen.length > 0
              : step === 3
                ? settlement !== 'refund' || refundMethod !== null
                : false;

    const submit = () => {
        if (!sale || busy || chosen.length === 0) {
            return;
        }
        router.post(
            '/app/returns',
            {
                sale_id: sale.id,
                lines: chosen.map((l) => ({
                    sale_item_id: l.id,
                    qty: qty[l.id] ?? 0,
                    restock: restock[l.id] ?? true,
                })),
                refund_mode: settlement,
                refund_method_id: settlement === 'refund' ? refundMethod : null,
                reason,
                note: note.trim() || null,
            },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onError: (e) => setErrors(e),
            },
        );
    };

    const next = () => {
        if (step === 4) {
            submit();

            return;
        }
        if (!canNext) {
            return;
        }
        if (step === 3 && settlement === 'exchange' && sale) {
            navigateTo(salesUrl.exchangeCreate(sale.id));

            return;
        }
        setStep((s) => (s + 1) as WizardStep);
    };

    useHotkeys('dialog', [
        {
            id: 'return.wizard.next',
            keys: 'mod+enter',
            labelKey: 'returns.wizard_next',
            allowInInput: true,
            handler: (e) => {
                e.preventDefault();
                next();
            },
        },
        {
            id: 'return.wizard.all',
            keys: 'alt+a',
            labelKey: 'returns.return_all',
            allowInInput: true,
            handler: () => step === 2 && returnAll(),
        },
        {
            id: 'return.wizard.back',
            keys: 'alt+arrowleft',
            labelKey: 'hotkeys.back',
            allowInInput: true,
            handler: () => step > 1 && setStep((s) => (s - 1) as WizardStep),
        },
    ]);

    const customerLabel = (c: CustomerSummary | null) =>
        c && !c.walk_in
            ? `${c.name}${c.customer_no ? ` (${digits.n(c.customer_no)})` : ''}`
            : t('sales.walk_in');

    const settlements: {
        id: Settlement;
        icon: LucideIcon;
        disabled: boolean;
        recommended?: boolean;
    }[] = [
        { id: 'refund', icon: Banknote, disabled: false },
        {
            id: 'credit',
            icon: FileText,
            disabled: walkIn,
            recommended: !walkIn,
        },
        ...(data?.can.exchange
            ? [{ id: 'exchange' as const, icon: Repeat, disabled: false }]
            : []),
    ];

    const errorList = Object.entries(errors);

    return (
        <form
            className="flex min-h-0 flex-col"
            onSubmit={(e) => {
                e.preventDefault();
                next();
            }}
        >
            <DialogBar
                icon={Undo2}
                closeLabel={t('common.close')}
                title={t('returns.wizard_title', { step: digits.n(step) })}
            />
            <DialogBody className="space-y-4">
                <WizardStepper step={step} />

                {step === 1 ? (
                    <div className="space-y-3">
                        <div>
                            <label
                                htmlFor="wizard-invoice"
                                className="block text-[11px] font-semibold text-muted-foreground"
                            >
                                {t('returns.wizard_invoice_label')}
                            </label>
                            <div className="mt-1 flex gap-2">
                                <input
                                    id="wizard-invoice"
                                    type="search"
                                    autoFocus
                                    value={text}
                                    placeholder={t('returns.pick_placeholder')}
                                    onChange={(e) => search(e.target.value)}
                                    onKeyDown={(e) => {
                                        const first = data?.candidates[0];
                                        if (
                                            e.key === 'Enter' &&
                                            first &&
                                            !sale
                                        ) {
                                            e.preventDefault();
                                            onPickSale(first.id);
                                        }
                                    }}
                                    className="h-9 flex-1 rounded-lg border bg-surface-sunken px-3 font-mono text-xs font-bold text-foreground outline-none focus-visible:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 [@media(pointer:coarse)]:min-h-11"
                                />
                                <Button
                                    type="button"
                                    variant="secondary"
                                    className="h-9"
                                    onClick={() => onSearch(text.trim())}
                                >
                                    <Search aria-hidden="true" />
                                    {t('returns.wizard_verify')}
                                </Button>
                            </div>
                        </div>

                        {!sale &&
                        text.trim().length >= 2 &&
                        !loading &&
                        (data?.candidates.length ?? 0) === 0 ? (
                            <p className="text-muted-foreground">
                                {t('returns.no_candidates')}
                            </p>
                        ) : null}
                        {!sale && (data?.candidates.length ?? 0) > 0 ? (
                            <ul
                                className="grid max-h-56 gap-1.5 overflow-y-auto"
                                aria-label={t('returns.candidates')}
                            >
                                {data?.candidates.map((c) => (
                                    <li key={c.id}>
                                        <button
                                            type="button"
                                            onClick={() => onPickSale(c.id)}
                                            className="flex w-full items-center justify-between gap-3 rounded-lg border bg-surface-sunken p-2.5 text-left hover:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none [@media(pointer:coarse)]:min-h-11"
                                        >
                                            <span>
                                                <span className="num font-mono font-bold text-primary">
                                                    {c.number}
                                                </span>
                                                <span className="num ml-2 text-[10px] text-muted-foreground">
                                                    {digits.n(c.sold_at ?? '')}
                                                </span>
                                                <span className="block text-[11px]">
                                                    {customerLabel(c.customer)}
                                                </span>
                                            </span>
                                            <span className="num font-semibold">
                                                {digits.money(c.total)}
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        ) : null}

                        {sale ? (
                            <div className="space-y-2 rounded-xl border bg-surface-sunken p-3.5">
                                <div className="flex items-center justify-between gap-2 border-b pb-2">
                                    <div>
                                        <div className="num font-mono text-xs font-bold">
                                            {sale.number}
                                        </div>
                                        <div className="num text-[11px] text-muted-foreground">
                                            {t('returns.wizard_sold', {
                                                customer: customerLabel(
                                                    sale.customer,
                                                ),
                                                date: digits.n(
                                                    sale.sold_at ?? '',
                                                ),
                                            })}
                                        </div>
                                    </div>
                                    <span className="flex items-center gap-2">
                                        <StatusBadge
                                            status={sale.status}
                                            label={t(
                                                'returns.wizard_completed',
                                            )}
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => onPickSale(null)}
                                        >
                                            {t('returns.change_sale')}
                                        </Button>
                                    </span>
                                </div>
                                <div className="grid grid-cols-3 gap-2">
                                    <Figure
                                        label={t('sales.total')}
                                        value={digits.money(sale.total)}
                                    />
                                    <Figure
                                        label={t('sales.paid')}
                                        value={digits.money(sale.paid)}
                                        tone="success"
                                    />
                                    <Figure
                                        label={t('returns.already_returned')}
                                        value={digits.money(
                                            sale.returned_total,
                                        )}
                                    />
                                </div>
                                {sale.window.expired ? (
                                    <p
                                        role="alert"
                                        className="flex items-center gap-2 rounded-md bg-warning-soft p-2 text-warning"
                                    >
                                        <AlertTriangle
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {t('returns.window_closed', {
                                            days: digits.n(
                                                sale.window.days ?? 0,
                                            ),
                                        })}
                                    </p>
                                ) : null}
                            </div>
                        ) : null}
                    </div>
                ) : null}

                {step === 2 && sale ? (
                    <div className="space-y-3">
                        <div className="flex justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={returnAll}
                            >
                                {t('returns.return_all')}
                                <Kbd keys="alt+a" onButton />
                            </Button>
                        </div>
                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full text-left text-xs">
                                <thead className="border-b bg-surface-sunken text-[10px] text-muted-foreground uppercase">
                                    <tr>
                                        <th scope="col" className="px-3 py-2">
                                            {t('returns.item_sku')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-2 py-2 text-center"
                                        >
                                            {t('returns.sold')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-2 py-2 text-center"
                                        >
                                            {t('returns.returnable')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-2 py-2 text-center"
                                        >
                                            {t('returns.qty')}
                                        </th>
                                        <th scope="col" className="px-2 py-2">
                                            {t('returns.condition')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2 text-right"
                                        >
                                            {t('returns.refund_total')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {lines.map((l) => (
                                        <tr key={l.id}>
                                            <td className="px-3 py-2.5">
                                                <div className="font-semibold">
                                                    {l.name}
                                                </div>
                                                <div className="num text-[10px] text-muted-foreground">
                                                    {t('returns.sku_price', {
                                                        sku: l.sku,
                                                        price: digits.money(
                                                            l.unit_price,
                                                        ),
                                                    })}
                                                </div>
                                            </td>
                                            <td className="num px-2 py-2.5 text-center font-mono font-semibold">
                                                {digits.qty(l.qty)}
                                            </td>
                                            <td className="num px-2 py-2.5 text-center font-mono font-bold text-primary">
                                                {digits.qty(l.returnable)}
                                            </td>
                                            <td className="px-2 py-2.5">
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
                                                            l.returnable %
                                                                1000 ===
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
                                            <td className="px-2 py-2.5">
                                                <NativeSelect
                                                    className="h-7 text-[11px]"
                                                    aria-label={t(
                                                        'returns.condition_for',
                                                        { name: l.name },
                                                    )}
                                                    disabled={
                                                        l.returnable === 0
                                                    }
                                                    value={
                                                        (restock[l.id] ?? true)
                                                            ? 'sellable'
                                                            : 'damaged'
                                                    }
                                                    onChange={(e) =>
                                                        setRestock((s) => ({
                                                            ...s,
                                                            [l.id]:
                                                                e.target
                                                                    .value ===
                                                                'sellable',
                                                        }))
                                                    }
                                                >
                                                    <option value="sellable">
                                                        {t(
                                                            'returns.condition_sellable',
                                                        )}
                                                    </option>
                                                    <option value="damaged">
                                                        {t(
                                                            'returns.condition_damaged',
                                                        )}
                                                    </option>
                                                </NativeSelect>
                                            </td>
                                            <td className="num px-3 py-2.5 text-right font-mono font-bold text-primary">
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
                        <div className="flex items-center justify-between gap-3 rounded-xl border bg-surface-sunken p-3">
                            <span className="text-[11px] text-muted-foreground">
                                {t('returns.estimate_hint')}
                            </span>
                            <span className="text-right">
                                <span className="block text-[10px] tracking-wider text-muted-foreground uppercase">
                                    {t('returns.net_refund')}
                                </span>
                                <span className="num text-base font-bold text-primary">
                                    {digits.money(value)}
                                </span>
                            </span>
                        </div>
                    </div>
                ) : null}

                {step === 3 && sale ? (
                    <div className="space-y-3">
                        <p
                            id="settlement-label"
                            className="text-[11px] font-semibold text-muted-foreground"
                        >
                            {t('returns.wizard_settlement')}
                        </p>
                        <div
                            role="radiogroup"
                            aria-labelledby="settlement-label"
                            className="grid gap-2.5 sm:grid-cols-2"
                        >
                            {settlements.map((m) => (
                                <label
                                    key={m.id}
                                    className={cn(
                                        'relative cursor-pointer rounded-xl border p-3 text-left transition-all hover:border-primary has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-50 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50 [@media(pointer:coarse)]:min-h-11',
                                        settlement === m.id
                                            ? 'border-primary bg-primary-soft'
                                            : 'bg-surface-sunken hover:bg-surface',
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
                                    {m.recommended ? (
                                        <span className="absolute top-2 right-2 rounded-sm bg-primary px-1.5 text-[9px] font-bold text-primary-foreground">
                                            {t('returns.recommended')}
                                        </span>
                                    ) : null}
                                    <span className="flex items-center gap-2 text-xs font-bold">
                                        <m.icon
                                            className="size-4 text-primary"
                                            aria-hidden="true"
                                        />
                                        {t(`returns.settle_${m.id}`)}
                                    </span>
                                    <span className="mt-1 block text-[11px] text-muted-foreground">
                                        {t(`returns.settle_${m.id}_hint`)}
                                    </span>
                                </label>
                            ))}
                        </div>
                        {sale.due > 0 ? (
                            <p className="text-[11px] text-warning">
                                {t('returns.due_first', {
                                    due: digits.money(sale.due),
                                })}
                            </p>
                        ) : null}
                        <div className="grid gap-3 sm:grid-cols-2">
                            {settlement === 'refund' ? (
                                <div className="grid gap-1">
                                    <label
                                        htmlFor="wizard-refund-method"
                                        className="text-[11px] font-semibold text-muted-foreground"
                                    >
                                        {t('returns.refund_method')}
                                    </label>
                                    <NativeSelect
                                        id="wizard-refund-method"
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
                            <div className="grid gap-1">
                                <label
                                    htmlFor="wizard-reason"
                                    className="text-[11px] font-semibold text-muted-foreground"
                                >
                                    {t('returns.col_reason')}
                                </label>
                                <NativeSelect
                                    id="wizard-reason"
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
                        </div>
                        <div className="grid gap-1">
                            <label
                                htmlFor="wizard-note"
                                className="text-[11px] font-semibold text-muted-foreground"
                            >
                                {t('returns.memo')}
                            </label>
                            <input
                                id="wizard-note"
                                value={note}
                                maxLength={1000}
                                onChange={(e) => setNote(e.target.value)}
                                className="h-8 w-full rounded-lg border bg-surface-sunken px-2.5 text-xs text-foreground outline-none focus-visible:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 [@media(pointer:coarse)]:min-h-11"
                            />
                        </div>
                    </div>
                ) : null}

                {step === 4 && sale ? (
                    <div className="space-y-4 py-2 text-center">
                        <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-primary-soft text-primary">
                            <Undo2 className="size-7" aria-hidden="true" />
                        </div>
                        <div>
                            <h3 className="text-base font-bold">
                                {t('returns.wizard_confirm_title')}
                            </h3>
                            <p className="mt-0.5 text-muted-foreground">
                                {t('returns.wizard_confirm_hint')}
                            </p>
                        </div>
                        <dl className="mx-auto grid max-w-sm grid-cols-[1fr_auto] gap-x-3 gap-y-2 rounded-xl border bg-surface-sunken p-4 text-left">
                            <dt className="text-muted-foreground">
                                {t('returns.original_sale')}
                            </dt>
                            <dd className="num text-right font-mono font-semibold">
                                {sale.number}
                            </dd>
                            <dt className="text-muted-foreground">
                                {t('returns.beneficiary')}
                            </dt>
                            <dd className="text-right font-semibold">
                                {customerLabel(sale.customer)}
                            </dd>
                            <dt className="text-muted-foreground">
                                {t('returns.lines')}
                            </dt>
                            <dd className="num text-right">
                                {t('returns.items_count', {
                                    n: digits.n(chosen.length),
                                })}
                            </dd>
                            <dt className="text-muted-foreground">
                                {t('returns.settlement')}
                            </dt>
                            <dd className="text-right font-semibold">
                                {t(`returns.mode_${settlement}`)}
                                {settlement === 'refund'
                                    ? ` · ${methods.find((m) => m.id === refundMethod)?.name ?? ''}`
                                    : ''}
                            </dd>
                            <dt className="text-muted-foreground">
                                {t('returns.col_reason')}
                            </dt>
                            <dd className="text-right">
                                {t(`returns.reason_${reason}`)}
                            </dd>
                            <dt className="text-muted-foreground">
                                {t('returns.value_estimate')}
                            </dt>
                            <dd className="num text-right text-base font-bold text-primary">
                                {digits.money(value)}
                            </dd>
                        </dl>
                        {errorList.length > 0 ? (
                            <div role="alert" className="grid gap-1 text-left">
                                {errorList.map(([key, message]) => (
                                    <InputError key={key} message={message} />
                                ))}
                            </div>
                        ) : null}
                    </div>
                ) : null}

                <div className="flex justify-between gap-2 pt-2">
                    {step === 1 ? (
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={onClose}
                        >
                            {t('confirm.cancel')}
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() =>
                                setStep((s) => (s - 1) as WizardStep)
                            }
                        >
                            <ArrowLeft aria-hidden="true" />
                            {t('returns.wizard_back')}
                        </Button>
                    )}
                    <Button
                        type="submit"
                        className="font-bold"
                        disabled={step === 4 ? busy : !canNext}
                    >
                        {step === 4 ? <Check aria-hidden="true" /> : null}
                        {t(
                            step === 1
                                ? 'returns.wizard_next_items'
                                : step === 2
                                  ? 'returns.wizard_next_refund'
                                  : step === 3
                                    ? settlement === 'exchange'
                                        ? 'returns.wizard_next_exchange'
                                        : 'returns.wizard_next_confirm'
                                    : 'returns.wizard_complete',
                        )}
                        {step < 4 ? <ArrowRight aria-hidden="true" /> : null}
                        <Kbd keys="mod+enter" onButton />
                    </Button>
                </div>
            </DialogBody>
        </form>
    );
}

function Figure({
    label,
    value,
    tone,
}: {
    label: string;
    value: string;
    tone?: 'success';
}) {
    return (
        <div>
            <span className="text-[10px] text-muted-foreground">{label}</span>
            <div
                className={cn(
                    'num font-bold',
                    tone === 'success' && 'text-success',
                )}
            >
                {value}
            </div>
        </div>
    );
}
