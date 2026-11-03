import { Head, router } from '@inertiajs/react';
import { MessageSquare, Printer } from 'lucide-react';
import { useState } from 'react';
import {
    CustomerPicker,
    type PickedCustomer,
} from '@/components/customers/customer-picker';
import { EmptyState } from '@/components/empty-state';
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
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <h1 className="text-2xl font-bold print:hidden">
                    {t('customers.statement.title')}
                </h1>

                <div className="flex flex-wrap items-end gap-3 print:hidden">
                    <CustomerPicker
                        autoFocus={customer === null}
                        placeholder={t('customers.statement.search')}
                        onPick={pick}
                        className="w-full max-w-md"
                    />
                    <fieldset className="m-0 flex min-w-0 flex-wrap gap-1 border-0 p-0">
                        <legend className="sr-only">
                            {t('customers.statement.period')}
                        </legend>
                        {STATEMENT_PRESETS.map((preset) => (
                            <Button
                                key={preset}
                                type="button"
                                size="sm"
                                variant={
                                    (showCustom ? 'custom' : active) === preset
                                        ? 'default'
                                        : 'outline'
                                }
                                aria-pressed={
                                    (showCustom ? 'custom' : active) === preset
                                }
                                className="min-h-11 md:min-h-8"
                                onClick={() => choose(preset)}
                            >
                                {t(`range.${preset}`)}
                            </Button>
                        ))}
                    </fieldset>
                    {showCustom ? (
                        <form
                            className="flex flex-wrap items-end gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                if (isValidRange(custom)) {
                                    go(customer?.uuid ?? null, custom);
                                }
                            }}
                        >
                            <div className="grid gap-1">
                                <Label htmlFor="st-from">
                                    {t('range.from')}
                                </Label>
                                <Input
                                    id="st-from"
                                    type="date"
                                    value={custom.from}
                                    className="min-h-11 md:min-h-9"
                                    onChange={(e) =>
                                        setCustom({
                                            ...custom,
                                            from: e.target.value,
                                        })
                                    }
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="st-to">{t('range.to')}</Label>
                                <Input
                                    id="st-to"
                                    type="date"
                                    value={custom.to}
                                    className="min-h-11 md:min-h-9"
                                    onChange={(e) =>
                                        setCustom({
                                            ...custom,
                                            to: e.target.value,
                                        })
                                    }
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={!isValidRange(custom)}
                                className="min-h-11 md:min-h-9"
                            >
                                {t('customers.statement.apply')}
                            </Button>
                        </form>
                    ) : null}
                </div>

                {customer === null || totals === null ? (
                    <EmptyState
                        title={t('customers.statement.pick')}
                        description={t('customers.statement.pick_hint')}
                    />
                ) : (
                    <section
                        className="print-area flex flex-col gap-3"
                        aria-label={t('customers.statement.title')}
                    >
                        <div className="flex flex-wrap items-start gap-3">
                            <div className="mr-auto">
                                <h2 className="text-xl font-semibold">
                                    {customer.name}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {[
                                        customer.shop_name,
                                        customer.customer_no
                                            ? digits.n(customer.customer_no)
                                            : null,
                                        customer.phone
                                            ? digits.n(customer.phone)
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                                <p className="num text-sm">
                                    {t('customers.statement.period')}:{' '}
                                    {digits.n(period.from)} -{' '}
                                    {digits.n(period.to)}
                                </p>
                            </div>
                            <div className="flex gap-2 print:hidden">
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="min-h-11 md:min-h-9"
                                    onClick={() => window.print()}
                                >
                                    <Printer aria-hidden="true" />
                                    {t('customers.statement.print')}
                                </Button>
                                {can?.sms ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="min-h-11 md:min-h-9"
                                        onClick={sendSms}
                                    >
                                        <MessageSquare aria-hidden="true" />
                                        {t('customers.statement.sms')}
                                    </Button>
                                ) : null}
                            </div>
                        </div>

                        {rows.length === 0 ? (
                            <EmptyState
                                title={t('customers.statement.empty')}
                            />
                        ) : (
                            <div className="overflow-x-auto rounded-lg border bg-card">
                                <table
                                    className="w-full border-collapse text-left"
                                    style={{ fontSize: 'var(--table-font)' }}
                                >
                                    <caption className="sr-only">
                                        {t('customers.statement.title')}
                                    </caption>
                                    <thead className="bg-muted text-muted-foreground">
                                        <tr>
                                            <th scope="col" className="px-3 py-2 font-medium">
                                                {t('customers.statement.col.date')}
                                            </th>
                                            <th scope="col" className="px-3 py-2 font-medium">
                                                {t('customers.statement.col.invoice')}
                                            </th>
                                            <th scope="col" className="px-3 py-2 text-right font-medium">
                                                {t('customers.statement.col.items')}
                                            </th>
                                            <th scope="col" className="px-3 py-2 text-right font-medium">
                                                {t('customers.statement.col.total')}
                                            </th>
                                            <th scope="col" className="px-3 py-2 text-right font-medium">
                                                {t('customers.statement.col.paid')}
                                            </th>
                                            <th scope="col" className="px-3 py-2 text-right font-medium">
                                                {t('customers.statement.col.due')}
                                            </th>
                                            <th scope="col" className="px-3 py-2 font-medium">
                                                {t('customers.statement.col.status')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {rows.map((r) => (
                                            <tr key={r.id}>
                                                <td className="num px-3 py-2">
                                                    {digits.n(r.sold_at.slice(0, 10))}
                                                </td>
                                                <td className="num px-3 py-2">
                                                    {r.number ? digits.n(r.number) : '-'}
                                                </td>
                                                <td className="num px-3 py-2 text-right">
                                                    {digits.n(r.items)}
                                                </td>
                                                <td className="num px-3 py-2 text-right">
                                                    {digits.money(r.total)}
                                                </td>
                                                <td className="num px-3 py-2 text-right">
                                                    {digits.money(r.paid)}
                                                </td>
                                                <td
                                                    className={cn(
                                                        'num px-3 py-2 text-right',
                                                        r.due > 0 && 'font-medium text-destructive',
                                                    )}
                                                >
                                                    {digits.money(r.due)}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <StatusBadge status={r.payment_status} />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot className="border-t-2 bg-muted font-semibold">
                                        <tr>
                                            <th scope="row" colSpan={3} className="px-3 py-2 text-left">
                                                {t('customers.statement.totals', {
                                                    n: digits.n(totals.count),
                                                })}
                                            </th>
                                            <td className="num px-3 py-2 text-right">
                                                {digits.money(totals.total)}
                                            </td>
                                            <td className="num px-3 py-2 text-right">
                                                {digits.money(totals.paid)}
                                            </td>
                                            <td className="num px-3 py-2 text-right">
                                                {digits.money(totals.due)}
                                            </td>
                                            <td aria-label={t('customers.statement.col.status')} />
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        )}
                        <p className="num text-sm">
                            {t('customers.col.balance')}:{' '}
                            <span className="font-semibold">
                                {digits.money(customer.balance)}
                            </span>
                        </p>
                    </section>
                )}
            </div>
        </>
    );
}
