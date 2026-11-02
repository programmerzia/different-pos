import { Head, Link, router } from '@inertiajs/react';
import { BadgeDollarSign, FileText, Pencil, ShoppingCart } from 'lucide-react';
import { type ReactNode, useRef, useState } from 'react';
import { CardPanel } from '@/components/customers/card-panel';
import { EmptyState } from '@/components/empty-state';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useDigits, type Digits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';
import type { CustomerCard, CustomerDetail } from '@/types/customers';

type SaleRow = {
    id: number;
    number: string | null;
    sold_at: string;
    status: string;
    payment_status: string;
    total: number;
    paid: number;
    due: number;
};
type ReturnRow = {
    id: number;
    number: string;
    returned_at: string;
    refund_mode: string;
    total: number;
};
type PaymentRow = {
    id: number;
    number: string;
    paid_at: string;
    direction: string;
    method: string | null;
    amount: number;
    allocated: number;
    voided: boolean;
};
type MessageRow = {
    id: number;
    event: string;
    body: string;
    status: string;
    created_at: string | null;
};

type Props = {
    customer: CustomerDetail;
    card: CustomerCard | null;
    sales: SaleRow[];
    returns: ReturnRow[];
    payments: PaymentRow[];
    messages: MessageRow[];
    can: Record<string, boolean>;
};

const TABS = ['sales', 'returns', 'payments', 'messages'] as const;
type Tab = (typeof TABS)[number];

export default function CustomerShow({
    customer,
    card,
    sales,
    returns,
    payments,
    messages,
    can,
}: Props) {
    const { t } = useT();
    const digits = useDigits();
    const [tab, setTab] = useState<Tab>('sales');
    const tabRefs = useRef<Record<string, HTMLButtonElement | null>>({});
    const owes = customer.balance > 0;

    useHotkeys('page', [
        {
            id: 'customer.new_sale',
            keys: 'n',
            labelKey: 'customers.actions.new_sale',
            handler: () => {
                if (can['sell']) {
                    router.visit(`/app/pos?customer=${customer.uuid}`);
                }
            },
        },
        {
            id: 'customer.edit',
            keys: 'e',
            labelKey: 'hotkeys.edit',
            handler: () => {
                if (can['update']) {
                    router.visit(`/app/customers/${customer.uuid}/edit`);
                }
            },
        },
        {
            id: 'customer.collect',
            keys: 'c',
            labelKey: 'customers.actions.collect_due',
            handler: () => {
                if (can['collect'] && owes) {
                    router.visit(`/app/dues?customer=${customer.uuid}`);
                }
            },
        },
    ]);

    const onTabKey = (event: React.KeyboardEvent, index: number) => {
        const step =
            event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
        if (step === 0) {
            return;
        }
        event.preventDefault();
        const next = TABS[(index + step + TABS.length) % TABS.length] as Tab;
        setTab(next);
        tabRefs.current[next]?.focus();
    };

    return (
        <>
            <Head title={customer.name} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start gap-3">
                    <div className="mr-auto">
                        <h1 className="text-2xl font-bold">
                            {customer.name}
                            {customer.name_bn ? (
                                <span className="ml-2 text-lg font-normal text-muted-foreground">
                                    {customer.name_bn}
                                </span>
                            ) : null}
                        </h1>
                        <p className="text-muted-foreground">
                            {[customer.shop_name, customer.group]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                        <p className="num mt-1 text-sm">
                            {customer.customer_no ? (
                                <span className="font-mono">
                                    {digits.n(customer.customer_no)}
                                </span>
                            ) : null}
                            {customer.phone ? (
                                <span className="ml-3">
                                    {digits.n(customer.phone)}
                                </span>
                            ) : null}
                            {!customer.is_active ? (
                                <StatusBadge
                                    status="cancelled"
                                    label={t('customers.inactive')}
                                    className="ml-3"
                                />
                            ) : null}
                        </p>
                    </div>
                    <div className="text-right">
                        <p className="text-sm text-muted-foreground">
                            {t('customers.col.balance')}
                        </p>
                        <p
                            className={cn(
                                'num text-2xl font-bold',
                                owes && 'text-destructive',
                            )}
                        >
                            {digits.money(customer.balance)}
                        </p>
                    </div>
                    <div className="flex w-full flex-wrap gap-2">
                        {can['sell'] && customer.is_active ? (
                            <Button asChild className="min-h-11 md:min-h-9">
                                <Link href={`/app/pos?customer=${customer.uuid}`}>
                                    <ShoppingCart aria-hidden="true" />
                                    {t('customers.actions.new_sale')}
                                </Link>
                            </Button>
                        ) : null}
                        {can['collect'] && owes ? (
                            <Button
                                asChild
                                variant="outline"
                                className="min-h-11 md:min-h-9"
                            >
                                <Link href={`/app/dues?customer=${customer.uuid}`}>
                                    <BadgeDollarSign aria-hidden="true" />
                                    {t('customers.actions.collect_due')}
                                </Link>
                            </Button>
                        ) : null}
                        <Button
                            asChild
                            variant="outline"
                            className="min-h-11 md:min-h-9"
                        >
                            <Link
                                href={`/app/customers/${customer.uuid}/statement`}
                            >
                                <FileText aria-hidden="true" />
                                {t('customers.actions.statement')}
                            </Link>
                        </Button>
                        {can['update'] && !customer.is_walk_in ? (
                            <Button
                                asChild
                                variant="outline"
                                className="min-h-11 md:min-h-9"
                            >
                                <Link
                                    href={`/app/customers/${customer.uuid}/edit`}
                                >
                                    <Pencil aria-hidden="true" />
                                    {t('actions.edit')}
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                </header>

                <div className="grid gap-6 lg:grid-cols-[22rem_1fr]">
                    <div className="flex flex-col gap-6">
                        <CardPanel
                            customerUuid={customer.uuid}
                            card={card}
                            canIssue={can['issue_card'] === true}
                            canViewCards={can['cards'] === true}
                        />
                        <Details customer={customer} digits={digits} />
                    </div>

                    <section aria-label={t('customers.tabs.label')}>
                        <div
                            role="tablist"
                            aria-label={t('customers.tabs.label')}
                            className="flex gap-1 border-b"
                        >
                            {TABS.map((id, index) => (
                                <button
                                    key={id}
                                    ref={(el) => {
                                        tabRefs.current[id] = el;
                                    }}
                                    role="tab"
                                    type="button"
                                    id={`tab-${id}`}
                                    aria-selected={tab === id}
                                    aria-controls={`panel-${id}`}
                                    tabIndex={tab === id ? 0 : -1}
                                    className={cn(
                                        'min-h-11 border-b-2 px-4 text-sm font-medium md:min-h-9',
                                        tab === id
                                            ? 'border-primary text-primary'
                                            : 'border-transparent text-muted-foreground',
                                    )}
                                    onClick={() => setTab(id)}
                                    onKeyDown={(e) => onTabKey(e, index)}
                                >
                                    {t(`customers.tabs.${id}`)}
                                </button>
                            ))}
                        </div>
                        <div
                            role="tabpanel"
                            id={`panel-${tab}`}
                            aria-labelledby={`tab-${tab}`}
                            className="pt-3"
                        >
                            {tab === 'sales' ? (
                                <SalesTab rows={sales} digits={digits} />
                            ) : null}
                            {tab === 'returns' ? (
                                <ReturnsTab rows={returns} digits={digits} />
                            ) : null}
                            {tab === 'payments' ? (
                                <PaymentsTab rows={payments} digits={digits} />
                            ) : null}
                            {tab === 'messages' ? (
                                <MessagesTab rows={messages} digits={digits} />
                            ) : null}
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

function Details({
    customer,
    digits,
}: {
    customer: CustomerDetail;
    digits: Digits;
}) {
    const { t } = useT();
    const rows: [string, ReactNode][] = [
        ['customers.form.alt_phone', customer.alt_phone],
        ['customers.form.email', customer.email],
        [
            'customers.form.credit_limit',
            customer.credit_limit === null
                ? null
                : digits.money(customer.credit_limit),
        ],
        [
            'customers.form.address_line',
            [customer.address_line, customer.area, customer.post_code]
                .filter(Boolean)
                .join(', '),
        ],
        ['customers.form.dob', customer.dob],
        ['customers.form.anniversary', customer.anniversary_date],
        ['customers.form.notes', customer.notes],
    ];

    return (
        <section
            aria-label={t('customers.details')}
            className="rounded-lg border bg-card p-4"
        >
            <h2 className="mb-2 text-lg font-semibold">
                {t('customers.details')}
            </h2>
            <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                {rows
                    .filter(([, value]) => value)
                    .map(([key, value]) => (
                        <div key={key} className="contents">
                            <dt className="text-muted-foreground">{t(key)}</dt>
                            <dd className="num">
                                {typeof value === 'string'
                                    ? digits.n(value)
                                    : value}
                            </dd>
                        </div>
                    ))}
            </dl>
        </section>
    );
}

const Th = ({ children, num }: { children: ReactNode; num?: boolean }) => (
    <th scope="col" className={cn('px-3 py-2 font-medium', num && 'text-right')}>
        {children}
    </th>
);
const Td = ({ children, num }: { children: ReactNode; num?: boolean }) => (
    <td className={cn('px-3 py-2', num && 'num text-right')}>{children}</td>
);

function Grid({ head, children }: { head: ReactNode; children: ReactNode }) {
    return (
        <div className="overflow-x-auto rounded-lg border bg-card">
            <table
                className="w-full border-collapse text-left"
                style={{ fontSize: 'var(--table-font)' }}
            >
                <thead className="bg-muted text-muted-foreground">
                    <tr>{head}</tr>
                </thead>
                <tbody className="divide-y">{children}</tbody>
            </table>
        </div>
    );
}

function Empty() {
    const { t } = useT();

    return <EmptyState title={t('customers.tabs.empty')} />;
}

function SalesTab({ rows, digits }: { rows: SaleRow[]; digits: Digits }) {
    const { t } = useT();
    if (rows.length === 0) {
        return <Empty />;
    }

    return (
        <Grid
            head={
                <>
                    <Th>{t('customers.statement.col.date')}</Th>
                    <Th>{t('customers.statement.col.invoice')}</Th>
                    <Th num>{t('customers.statement.col.total')}</Th>
                    <Th num>{t('customers.statement.col.paid')}</Th>
                    <Th num>{t('customers.statement.col.due')}</Th>
                    <Th>{t('customers.statement.col.status')}</Th>
                </>
            }
        >
            {rows.map((r) => (
                <tr key={r.id}>
                    <Td>{digits.n(r.sold_at.slice(0, 10))}</Td>
                    <Td>{r.number ? digits.n(r.number) : '-'}</Td>
                    <Td num>{digits.money(r.total)}</Td>
                    <Td num>{digits.money(r.paid)}</Td>
                    <Td num>{digits.money(r.due)}</Td>
                    <Td>
                        <StatusBadge
                            status={
                                r.status === 'voided'
                                    ? 'void'
                                    : r.payment_status
                            }
                        />
                    </Td>
                </tr>
            ))}
        </Grid>
    );
}

function ReturnsTab({ rows, digits }: { rows: ReturnRow[]; digits: Digits }) {
    const { t } = useT();
    if (rows.length === 0) {
        return <Empty />;
    }

    return (
        <Grid
            head={
                <>
                    <Th>{t('customers.statement.col.date')}</Th>
                    <Th>{t('customers.tabs.number')}</Th>
                    <Th>{t('customers.tabs.refund_mode')}</Th>
                    <Th num>{t('customers.statement.col.total')}</Th>
                </>
            }
        >
            {rows.map((r) => (
                <tr key={r.id}>
                    <Td>{digits.n(r.returned_at.slice(0, 10))}</Td>
                    <Td>{digits.n(r.number)}</Td>
                    <Td>{t(`customers.refund.${r.refund_mode}`)}</Td>
                    <Td num>{digits.money(r.total)}</Td>
                </tr>
            ))}
        </Grid>
    );
}

function PaymentsTab({
    rows,
    digits,
}: {
    rows: PaymentRow[];
    digits: Digits;
}) {
    const { t } = useT();
    if (rows.length === 0) {
        return <Empty />;
    }

    return (
        <Grid
            head={
                <>
                    <Th>{t('customers.statement.col.date')}</Th>
                    <Th>{t('customers.tabs.number')}</Th>
                    <Th>{t('customers.tabs.method')}</Th>
                    <Th num>{t('customers.tabs.amount')}</Th>
                    <Th num>{t('customers.tabs.allocated')}</Th>
                </>
            }
        >
            {rows.map((r) => (
                <tr key={r.id} className={cn(r.voided && 'line-through')}>
                    <Td>{digits.n(r.paid_at.slice(0, 10))}</Td>
                    <Td>{digits.n(r.number)}</Td>
                    <Td>{r.method ?? '-'}</Td>
                    <Td num>
                        {r.direction === 'out' ? '-' : ''}
                        {digits.money(r.amount)}
                    </Td>
                    <Td num>{digits.money(r.allocated)}</Td>
                </tr>
            ))}
        </Grid>
    );
}

function MessagesTab({
    rows,
    digits,
}: {
    rows: MessageRow[];
    digits: Digits;
}) {
    const { t } = useT();
    if (rows.length === 0) {
        return <Empty />;
    }

    return (
        <Grid
            head={
                <>
                    <Th>{t('customers.statement.col.date')}</Th>
                    <Th>{t('customers.tabs.event')}</Th>
                    <Th>{t('customers.tabs.message')}</Th>
                    <Th>{t('customers.statement.col.status')}</Th>
                </>
            }
        >
            {rows.map((r) => (
                <tr key={r.id}>
                    <Td>{r.created_at ? digits.n(r.created_at.slice(0, 10)) : '-'}</Td>
                    <Td>{r.event}</Td>
                    <Td>{r.body}</Td>
                    <Td>{r.status}</Td>
                </tr>
            ))}
        </Grid>
    );
}
