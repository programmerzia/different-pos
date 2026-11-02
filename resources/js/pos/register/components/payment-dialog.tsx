import { ShieldCheck, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Kbd } from '@/components/kbd';
import { MoneyInput } from '@/components/money-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Paisa } from '@/engine/money';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';
import type { PosError } from '../api';
import { OVERRIDABLE, quickCash, settle, type TenderRow } from '../tenders';
import type { Approval, CustomerCard, PaymentMethod } from '../types';
import { PosDialog } from './pos-dialog';

/** Server error code → the manager permission that can lift it. */
export const APPROVABLE: Record<string, string> = {
    discount_cap_exceeded: 'pos.discount_override',
    below_cost_sale: 'pos.sell_below_cost',
    price_override_not_allowed: 'pos.price_override',
    credit_limit_exceeded: 'pos.customer_pin_override',
    backdate_not_allowed: 'pos.backdate',
};

const SHORTCUTS: { key: string; pick: (m: PaymentMethod) => boolean }[] = [
    { key: 'c', pick: (m) => m.type === 'cash' },
    { key: 'k', pick: (m) => m.type === 'card' },
    {
        key: 'b',
        pick: (m) => m.type === 'mobile_wallet' && /bkash/i.test(m.name),
    },
    {
        key: 'n',
        pick: (m) => m.type === 'mobile_wallet' && /nagad/i.test(m.name),
    },
];

/**
 * F9: split tender. Cash is prefilled with the exact amount; quick cash 1–4; Alt+C/K/B/N add a method,
 * Alt+D leaves the rest as due (named customer only). Enter or F10 completes.
 */
type Props = {
    open: boolean;
    total: Paisa;
    methods: readonly PaymentMethod[];
    customer: CustomerCard | null;
    approval: Approval | null;
    onSubmit: (tenders: TenderRow[]) => Promise<PosError | null>;
    onNeedApproval: (permission: string) => void;
    onClose: () => void;
};

export function PaymentDialog({ open, onClose, ...rest }: Props) {
    const { t } = useT();
    const [busy, setBusy] = useState(false);

    return (
        <PosDialog
            open={open}
            onClose={() => !busy && onClose()}
            title={t('pos.pay.title')}
            description={t('pos.pay.hint')}
            className="sm:max-w-2xl"
        >
            <PaymentBody onClose={onClose} onBusy={setBusy} {...rest} />
        </PosDialog>
    );
}

function PaymentBody({
    total,
    methods,
    customer,
    approval,
    onSubmit,
    onNeedApproval,
    onClose,
    onBusy,
}: Omit<Props, 'open'> & { onBusy: (busy: boolean) => void }) {
    const { t } = useT();
    const d = useDigits();
    const cash = methods.find((m) => m.type === 'cash');
    const [rows, setRows] = useState<TenderRow[]>(() =>
        cash ? [{ methodId: cash.id, amount: total, reference: '' }] : [],
    );
    const [busy, setBusyState] = useState(false);
    const [error, setError] = useState<PosError | null>(null);
    const formRef = useRef<HTMLFormElement>(null);
    const setBusy = (value: boolean) => {
        setBusyState(value);
        onBusy(value);
    };

    const usable = useMemo(
        () =>
            methods.filter(
                (m) =>
                    m.type !== 'customer_credit' ||
                    (customer !== null && customer.advance > 0),
            ),
        [methods, customer],
    );
    const s = settle(total, rows, methods, customer);
    const approved = approval !== null;
    const blocking = s.problems.filter(
        (p) => !(OVERRIDABLE.includes(p) && approved),
    );
    const methodName = (id: number) =>
        methods.find((m) => m.id === id)?.name ?? '';

    const add = (method: PaymentMethod) => {
        setRows((prev) => {
            if (prev.some((r) => r.methodId === method.id)) {
                return prev;
            }
            const nonCash = prev
                .filter((r) => r.methodId !== cash?.id)
                .reduce((sum, r) => sum + r.amount, 0);
            let amount = Math.max(0, total - nonCash);
            if (method.type === 'customer_credit' && customer) {
                amount = Math.min(amount, customer.advance);
            }
            if (method.id === cash?.id) {
                return [
                    ...prev,
                    { methodId: method.id, amount, reference: '' },
                ];
            }
            // The new tender takes the rest; cash keeps only what is still open.
            const next = prev
                .map((r) =>
                    r.methodId === cash?.id
                        ? {
                              ...r,
                              amount: Math.max(0, total - nonCash - amount),
                          }
                        : r,
                )
                .filter((r) => r.methodId !== cash?.id || r.amount > 0);

            return [...next, { methodId: method.id, amount, reference: '' }];
        });
    };
    const setRow = (index: number, patch: Partial<TenderRow>) =>
        setRows((prev) =>
            prev.map((r, i) => (i === index ? { ...r, ...patch } : r)),
        );
    const setCash = (amount: Paisa) => {
        if (!cash) {
            return;
        }
        setRows((prev) =>
            prev.some((r) => r.methodId === cash.id)
                ? prev.map((r) =>
                      r.methodId === cash.id ? { ...r, amount } : r,
                  )
                : [...prev, { methodId: cash.id, amount, reference: '' }],
        );
    };
    const leaveDue = () =>
        setRows((prev) =>
            prev.map((r) =>
                r.methodId === cash?.id ? { ...r, amount: 0 } : r,
            ),
        );

    const submit = async () => {
        if (busy || blocking.length > 0) {
            return;
        }
        setBusy(true);
        setError(null);
        const result = await onSubmit(rows.filter((r) => r.amount > 0));
        setBusy(false);
        setError(result);
    };

    const quick = quickCash(total);

    // Alt+C/K/B/N/D, quick cash 1–4 and F10, on the form (a native listener: the form is not a widget).
    const keys = useRef<(e: KeyboardEvent) => void>(() => undefined);
    useEffect(() => {
        keys.current = (e: KeyboardEvent) => {
            if (e.key === 'F10' && !e.shiftKey) {
                e.preventDefault();
                void submit();

                return;
            }
            if (e.altKey && !e.ctrlKey && !e.metaKey) {
                const key = e.key.toLowerCase();
                if (key === 'd') {
                    e.preventDefault();
                    leaveDue();

                    return;
                }
                const method = usable.find((m) =>
                    SHORTCUTS.find((sc) => sc.key === key)?.pick(m),
                );
                if (method) {
                    e.preventDefault();
                    add(method);
                }

                return;
            }
            const typing = e.target instanceof HTMLInputElement;
            const n = Number(e.key);
            if (!typing && Number.isInteger(n) && n >= 1 && n <= quick.length) {
                e.preventDefault();
                setCash(quick[n - 1] ?? total);
            }
        };
    });
    useEffect(() => {
        const form = formRef.current;
        const listener = (e: KeyboardEvent) => keys.current(e);
        form?.addEventListener('keydown', listener);

        return () => form?.removeEventListener('keydown', listener);
    }, []);

    return (
        <form
            ref={formRef}
            className="space-y-4"
            onSubmit={(e) => {
                e.preventDefault();
                void submit();
            }}
        >
            <div className="flex items-baseline justify-between rounded-lg bg-muted px-4 py-3">
                <span className="text-sm font-semibold uppercase">
                    {t('pos.totals.total')}
                </span>
                <span className="num text-3xl font-bold">{d.money(total)}</span>
            </div>

            <fieldset className="flex flex-wrap gap-2">
                <legend className="sr-only">{t('pos.pay.methods')}</legend>
                {usable.map((m) => {
                    const sc = SHORTCUTS.find((x) => x.pick(m));

                    return (
                        <Button
                            key={m.id}
                            type="button"
                            variant="outline"
                            className="min-h-11"
                            onClick={() => add(m)}
                        >
                            {m.name}
                            {m.type === 'customer_credit' && customer && (
                                <span className="num text-xs text-muted-foreground">
                                    ({d.money(customer.advance)})
                                </span>
                            )}
                            {sc && <Kbd keys={`alt+${sc.key}`} onButton />}
                        </Button>
                    );
                })}
                {customer && (
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11"
                        onClick={leaveDue}
                    >
                        {t('pos.pay.leave_due')} <Kbd keys="alt+d" onButton />
                    </Button>
                )}
            </fieldset>

            <ul className="space-y-2">
                {rows.map((row, i) => {
                    const method = methods.find((m) => m.id === row.methodId);

                    return (
                        <li
                            key={row.methodId}
                            className="grid grid-cols-[8rem_1fr_auto] items-center gap-2 sm:grid-cols-[10rem_1fr_1fr_auto]"
                        >
                            <span className="truncate font-semibold">
                                {methodName(row.methodId)}
                            </span>
                            <MoneyInput
                                value={row.amount}
                                onChange={(v) => setRow(i, { amount: v ?? 0 })}
                                aria-describedby="pos-pay-summary"
                                className="h-11"
                            />
                            {method?.requires_reference ? (
                                <Input
                                    className="h-11"
                                    value={row.reference}
                                    placeholder={t('pos.pay.reference')}
                                    aria-label={t('pos.pay.reference_for', {
                                        method: method.name,
                                    })}
                                    aria-invalid={
                                        row.reference.trim() === '' || undefined
                                    }
                                    onChange={(e) =>
                                        setRow(i, { reference: e.target.value })
                                    }
                                />
                            ) : (
                                <span className="max-sm:hidden" />
                            )}
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-11"
                                aria-label={t('pos.pay.remove', {
                                    method: methodName(row.methodId),
                                })}
                                onClick={() =>
                                    setRows((prev) =>
                                        prev.filter((_, j) => j !== i),
                                    )
                                }
                            >
                                <Trash2 aria-hidden="true" />
                            </Button>
                        </li>
                    );
                })}
            </ul>

            {cash && quick.length > 0 && (
                <fieldset className="flex flex-wrap gap-2">
                    <legend className="sr-only">{t('pos.pay.quick')}</legend>
                    {quick.map((amount, i) => (
                        <Button
                            key={amount}
                            type="button"
                            variant="secondary"
                            className="num min-h-11"
                            onClick={() => setCash(amount)}
                        >
                            {d.money(amount)}{' '}
                            <Kbd keys={String(i + 1)} onButton />
                        </Button>
                    ))}
                </fieldset>
            )}

            <dl
                id="pos-pay-summary"
                className="num grid grid-cols-2 gap-x-4 gap-y-1 text-sm"
                aria-live="polite"
            >
                <dt>{t('pos.pay.tendered')}</dt>
                <dd className="text-right">{d.money(s.tendered)}</dd>
                <dt className="text-lg font-semibold">{t('pos.pay.change')}</dt>
                <dd
                    className={cn(
                        'text-right text-lg font-bold',
                        s.change > 0 && 'text-success',
                    )}
                >
                    {d.money(s.change)}
                </dd>
                {s.due > 0 && (
                    <>
                        <dt className="font-semibold text-warning">
                            {t('pos.pay.due')}
                        </dt>
                        <dd className="text-right font-semibold text-warning">
                            {d.money(s.due)}
                        </dd>
                    </>
                )}
                {customer &&
                    s.balanceAfter !== null &&
                    (s.due > 0 || s.creditUsed > 0) && (
                        <>
                            <dt className="text-muted-foreground">
                                {t('pos.pay.balance_after')}
                            </dt>
                            <dd className="text-right text-muted-foreground">
                                {d.money(s.balanceAfter)}
                                {customer.credit_limit !== null &&
                                    ` / ${d.money(customer.credit_limit)}`}
                            </dd>
                        </>
                    )}
            </dl>

            {s.problems.length > 0 && (
                <ul className="space-y-1 text-sm" role="alert">
                    {s.problems.map((p) => (
                        <li
                            key={p}
                            className={cn(
                                OVERRIDABLE.includes(p) && approved
                                    ? 'text-muted-foreground'
                                    : 'text-danger',
                            )}
                        >
                            {t(`pos.pay.problem.${p}`)}
                            {p === 'over_limit' && !approved && (
                                <Button
                                    type="button"
                                    variant="link"
                                    className="h-auto p-0 pl-2"
                                    onClick={() =>
                                        onNeedApproval(
                                            'pos.customer_pin_override',
                                        )
                                    }
                                >
                                    {t('pos.manager.ask')}
                                </Button>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {error && (
                <div
                    className="rounded-md border border-danger bg-danger-soft p-3 text-sm"
                    role="alert"
                >
                    <p className="font-semibold">
                        {error.message || t('pos.errors.network')}
                    </p>
                    {error.detail && (
                        <p className="mt-1 text-muted-foreground">
                            {error.detail}
                        </p>
                    )}
                    {error.code && APPROVABLE[error.code] && (
                        <Button
                            type="button"
                            variant="outline"
                            className="mt-2 min-h-11"
                            onClick={() =>
                                onNeedApproval(
                                    APPROVABLE[error.code ?? ''] ?? '',
                                )
                            }
                        >
                            <ShieldCheck aria-hidden="true" />{' '}
                            {t('pos.manager.ask')}
                        </Button>
                    )}
                </div>
            )}

            {approved && (
                <p className="flex items-center gap-1 text-sm text-success">
                    <ShieldCheck className="size-4" aria-hidden="true" />{' '}
                    {t('pos.manager.approved_by', {
                        name: approval.managerName,
                    })}
                </p>
            )}

            <div className="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-11"
                    disabled={busy}
                    onClick={onClose}
                >
                    {t('confirm.cancel')} <Kbd keys="escape" onButton />
                </Button>
                <Button
                    type="submit"
                    className="min-h-11 min-w-40 text-base"
                    disabled={busy || blocking.length > 0}
                >
                    {busy ? t('pos.pay.saving') : t('pos.pay.complete')}{' '}
                    <Kbd keys="f10" onButton />
                </Button>
            </div>
        </form>
    );
}
