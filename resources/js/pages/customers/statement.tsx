import { Head, router } from '@inertiajs/react';
import { Page } from '@/components/page-header';
import { FileText, Printer, Send } from 'lucide-react';
import { useState } from 'react';
import {
    CustomerPicker,
    type PickedCustomer,
} from '@/components/customers/customer-picker';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import type { DateRange } from '@/lib/date-range';
import {
    STATEMENT_PRESETS,
    isValidRange,
    statementHref,
    statementPreset,
    statementRange,
    type StatementPreset,
} from '@/lib/statement';
import { cn } from '@/lib/utils';

type Row = {
    id: number;
    number: string | null;
    sold_at: string;
    items: number;
    total: number;
    paid: number;
    due: number;
    payment_status: string;
};
type Totals = { count: number; total: number; paid: number; due: number };
type Customer = {
    uuid: string;
    customer_no: string | null;
    name: string;
    name_bn: string | null;
    shop_name: string | null;
    phone: string | null;
    balance: number;
};

export default function Statement({
    customer,
    period,
    rows,
    totals,
    can,
}: {
    customer: Customer | null;
    period: DateRange;
    rows: Row[];
    totals: Totals | null;
    can?: { sms: boolean };
}) {
    const { t } = useT();
    const digits = useDigits();
    const [today] = useState(() => new Date());
    const active = statementPreset(period, today);
    const [custom, setCustom] = useState<DateRange>(period);
    const [showCustom, setShowCustom] = useState(active === 'custom');

    const go = (uuid: string | null, range: DateRange) =>
        router.get(statementHref(uuid, range), {}, { preserveScroll: true });

    const choose = (preset: StatementPreset) => {
        if (preset === 'custom') {
            setShowCustom(true);

            return;
        }
        setShowCustom(false);
        go(customer?.uuid ?? null, statementRange(preset, today));
    };

    const pick = (picked: PickedCustomer) => go(picked.uuid, period);

    useHotkeys('page', [
        {
            id: 'statement.print',
            keys: 'mod+p',
            labelKey: 'customers.statement.print',
            allowInInput: true,
            handler: (event) => {
                event.preventDefault();
                window.print();
            },
        },
    ]);

    const sendSms = () => {
        if (customer) {
            router.post(
                `/app/customers/${customer.uuid}/statement/sms`,
                { from: period.from, to: period.to },
                { preserveScroll: true },
            );
        }
    };

    return (
        <>
            <Head title={t('customers.statement.title')} />
            <Page className="overflow-y-auto">
                <div className="flex shrink-0 flex-col justify-between gap-2 border-b pb-3 sm:flex-row sm:items-center print:hidden">
                    <div>
                        <h1 className="text-lg leading-7 font-bold text-foreground">
                            {t('customers.statement.title')}
                        </h1>
                        <p className="text-xs text-muted-foreground">
                            {t('customers.statement.subtitle')}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {can?.sms || customer === null ? (
                            <button
                                type="button"
                                disabled={customer === null}
                                onClick={sendSms}
                                className="flex h-8 items-center gap-1.5 rounded-lg border border-success/30 bg-success-soft px-3 text-xs font-semibold text-success disabled:cursor-not-allowed disabled:opacity-40 [@media(pointer:coarse)]:min-h-11"
                            >
                                <Send className="size-3.5" aria-hidden="true" />
                                {t('customers.statement.sms')}
                            </button>
                        ) : null}
                        <button
                            type="button"
                            disabled={customer === null}
                            onClick={() => window.print()}
                            className="flex h-8 items-center gap-1.5 rounded-lg bg-primary px-3 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-40 [@media(pointer:coarse)]:min-h-11"
                        >
                            <Printer className="size-3.5" aria-hidden="true" />
                            {t('customers.statement.print')}
                        </button>
                    </div>
                </div>

                <CustomerPicker
                    autoFocus={customer === null}
                    placeholder={t('customers.statement.search')}
                    onPick={pick}
                    className="w-full max-w-xl print:hidden"
                />

                {customer === null || totals === null ? (
                    <div className="rounded-xl border border-dashed p-10 text-center text-xs text-muted-foreground print:hidden">
                        <FileText
                            className="mx-auto mb-2 size-7 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="font-semibold text-foreground">
                            {t('customers.statement.pick')}
                        </p>
                        <p>{t('customers.statement.pick_hint')}</p>
                    </div>
                ) : (
                    <section
                        className="print-area flex flex-col gap-4 rounded-xl border bg-surface p-4 shadow-xs"
                        aria-label={t('customers.statement.title')}
                    >
                        <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                            <div className="flex items-center gap-3">
                                <div
                                    aria-hidden="true"
                                    className="flex size-11 items-center justify-center rounded-xl bg-primary text-lg font-bold text-primary-foreground"
                                >
                                    {Array.from(customer.name)[0] ?? ''}
                                </div>
                                <div>
                                    <h2 className="text-base font-bold text-foreground">
                                        {customer.name}
                                        {customer.name_bn ? (
                                            <span className="ml-1 text-xs font-normal text-muted-foreground">
                                                {customer.name_bn}
                                            </span>
                                        ) : null}
                                    </h2>
                                    {customer.shop_name ? (
                                        <p className="text-xs font-semibold text-primary">
                                            {customer.shop_name}
                                        </p>
                                    ) : null}
                                    <p className="flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                                        {customer.phone ? (
                                            <span>
                                                {t('customers.statement.phone')}
                                                :{' '}
                                                <strong className="num font-mono">
                                                    {digits.n(customer.phone)}
                                                </strong>
                                            </span>
                                        ) : null}
                                        {customer.customer_no ? (
                                            <span>
                                                {t(
                                                    'customers.statement.customer_no',
                                                )}
                                                :{' '}
                                                <strong className="num font-mono">
                                                    {digits.n(
                                                        customer.customer_no,
                                                    )}
                                                </strong>
                                            </span>
                                        ) : null}
                                    </p>
                                    <p className="num text-xs text-muted-foreground">
                                        {t('customers.statement.period')}:{' '}
                                        {digits.n(period.from)} -{' '}
                                        {digits.n(period.to)}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-4">
                                <div className="text-right">
                                    <div className="text-[10px] font-bold text-muted-foreground uppercase">
                                        {t('customers.col.balance')}
                                    </div>
                                    <div
                                        className={cn(
                                            'num font-mono text-base font-bold',
                                            customer.balance > 0
                                                ? 'text-destructive'
                                                : 'text-success',
                                        )}
                                    >
                                        {digits.money(customer.balance)}
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => go(null, period)}
                                    className="h-7 rounded-lg border bg-surface-sunken px-2.5 text-[11px] font-semibold print:hidden [@media(pointer:coarse)]:min-h-11"
                                >
                                    {t('customers.statement.change')}
                                </button>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2 print:hidden">
                            <fieldset className="m-0 flex min-w-0 flex-wrap gap-2 border-0 p-0">
                                <legend className="sr-only">
                                    {t('customers.statement.period')}
                                </legend>
                                {STATEMENT_PRESETS.map((preset) => {
                                    const on =
                                        (showCustom ? 'custom' : active) ===
                                        preset;

                                    return (
                                        <button
                                            key={preset}
                                            type="button"
                                            aria-pressed={on}
                                            onClick={() => choose(preset)}
                                            className={cn(
                                                'h-7 rounded-full border px-3 text-[11px] font-semibold [@media(pointer:coarse)]:min-h-11',
                                                on
                                                    ? 'border-primary bg-primary-soft text-primary'
                                                    : 'bg-surface-sunken text-muted-foreground',
                                            )}
                                        >
                                            {t(`range.${preset}`)}
                                        </button>
                                    );
                                })}
                            </fieldset>
                            {showCustom ? (
                                <form
                                    className="flex flex-wrap items-center gap-1.5 text-[11px] text-muted-foreground"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        if (isValidRange(custom)) {
                                            go(customer.uuid, custom);
                                        }
                                    }}
                                >
                                    <Label
                                        htmlFor="st-from"
                                        className="sr-only"
                                    >
                                        {t('range.from')}
                                    </Label>
                                    <Input
                                        id="st-from"
                                        type="date"
                                        value={custom.from}
                                        className="h-7 w-auto px-2 text-[11px]"
                                        onChange={(e) =>
                                            setCustom({
                                                ...custom,
                                                from: e.target.value,
                                            })
                                        }
                                    />
                                    <span>{t('range.to').toLowerCase()}</span>
                                    <Label htmlFor="st-to" className="sr-only">
                                        {t('range.to')}
                                    </Label>
                                    <Input
                                        id="st-to"
                                        type="date"
                                        value={custom.to}
                                        className="h-7 w-auto px-2 text-[11px]"
                                        onChange={(e) =>
                                            setCustom({
                                                ...custom,
                                                to: e.target.value,
                                            })
                                        }
                                    />
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={!isValidRange(custom)}
                                    >
                                        {t('customers.statement.apply')}
                                    </Button>
                                </form>
                            ) : null}
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-xs">
                                <caption className="sr-only">
                                    {t('customers.statement.title')}
                                </caption>
                                <thead>
                                    <tr className="border-b text-left text-[10px] tracking-wider text-muted-foreground uppercase">
                                        <th scope="col" className="py-2 pr-3">
                                            {t('customers.statement.col.date')}
                                        </th>
                                        <th scope="col" className="py-2 pr-3">
                                            {t(
                                                'customers.statement.col.invoice',
                                            )}
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 pr-3 text-right"
                                        >
                                            {t('customers.statement.col.items')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 pr-3 text-right"
                                        >
                                            {t('customers.statement.col.total')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 pr-3 text-right"
                                        >
                                            {t('customers.statement.col.paid')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 pr-3 text-right"
                                        >
                                            {t('customers.statement.col.due')}
                                        </th>
                                        <th scope="col" className="py-2">
                                            {t(
                                                'customers.statement.col.status',
                                            )}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {rows.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                {t('customers.statement.empty')}
                                            </td>
                                        </tr>
                                    ) : null}
                                    {rows.map((r) => (
                                        <tr key={r.id}>
                                            <td className="num py-2 pr-3 whitespace-nowrap">
                                                {digits.n(
                                                    r.sold_at.slice(0, 10),
                                                )}
                                            </td>
                                            <td className="num py-2 pr-3 font-mono">
                                                {r.number
                                                    ? digits.n(r.number)
                                                    : '-'}
                                            </td>
                                            <td className="num py-2 pr-3 text-right font-mono">
                                                {digits.n(r.items)}
                                            </td>
                                            <td className="num py-2 pr-3 text-right font-mono">
                                                {digits.money(r.total)}
                                            </td>
                                            <td className="num py-2 pr-3 text-right font-mono">
                                                {digits.money(r.paid)}
                                            </td>
                                            <td
                                                className={cn(
                                                    'num py-2 pr-3 text-right font-mono',
                                                    r.due > 0 &&
                                                        'font-semibold text-destructive',
                                                )}
                                            >
                                                {digits.money(r.due)}
                                            </td>
                                            <td className="py-2">
                                                <StatusBadge
                                                    status={r.payment_status}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                {rows.length > 0 ? (
                                    <tfoot>
                                        <tr className="border-t-2 font-bold text-foreground">
                                            <th
                                                scope="row"
                                                colSpan={3}
                                                className="py-2 pr-3 text-left"
                                            >
                                                {t(
                                                    'customers.statement.totals',
                                                    {
                                                        n: digits.n(
                                                            totals.count,
                                                        ),
                                                    },
                                                )}
                                            </th>
                                            <td className="num py-2 pr-3 text-right font-mono">
                                                {digits.money(totals.total)}
                                            </td>
                                            <td className="num py-2 pr-3 text-right font-mono">
                                                {digits.money(totals.paid)}
                                            </td>
                                            <td className="num py-2 pr-3 text-right font-mono">
                                                {digits.money(totals.due)}
                                            </td>
                                            <td
                                                aria-label={t(
                                                    'customers.statement.col.status',
                                                )}
                                            />
                                        </tr>
                                    </tfoot>
                                ) : null}
                            </table>
                        </div>
                    </section>
                )}
            </Page>
        </>
    );
}
