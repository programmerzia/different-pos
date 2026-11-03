import { Head, Link } from '@inertiajs/react';
import { Page, PageHeader } from '@/components/page-header';
import {
    ArrowLeft,
    ArrowLeftRight,
    ArrowRight,
    PackageCheck,
    Printer,
} from 'lucide-react';
import { useState } from 'react';
import { Kbd } from '@/components/kbd';
import { KpiTile } from '@/components/kpi-tile';
import { QtyStepper } from '@/components/qty-stepper';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { navigateTo } from '@/command/navigate';
import { timesQty } from '@/engine/money';
import { useDigits } from '@/hooks/use-digits';
import { useFormSubmit } from '@/hooks/use-form-submit';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

type BranchRef = { id: number; code: string; name: string };
type Item = {
    id: number;
    product: string;
    variant_label: string | null;
    sku: string;
    qty_sent: number;
    qty_received: number | null;
    unit_cost: number | null;
    short_reason: string | null;
};
type Receipt = { qty: number; reason: string };

const TH = 'px-3 py-2 text-[10px] font-semibold text-muted-foreground';
const branchLabel = (b: BranchRef) => `${b.name} [${b.code}]`;

/** One gate pass as a dispatch-note document, plus the receive panel when the viewer is at the destination branch while it is in transit. */
export default function TransferShow({
    transfer,
    items,
    canReceive,
}: {
    transfer: {
        id: number;
        uuid: string;
        number: string;
        status: string;
        from: BranchRef;
        to: BranchRef;
        note: string | null;
        sent_at: string | null;
        sent_by: string | null;
        received_at: string | null;
        received_by: string | null;
    };
    items: Item[];
    canReceive: boolean;
}) {
    const { t } = useT();
    const digits = useDigits();
    const { submit, processing, errors } = useFormSubmit();
    const [receipt, setReceipt] = useState<Record<number, Receipt>>(() =>
        Object.fromEntries(
            items.map((i) => [i.id, { qty: i.qty_sent, reason: '' }]),
        ),
    );
    const showCost = items.some((i) => i.unit_cost !== null);
    const received = transfer.status === 'received';
    const print = () => window.print();

    const sent = items.reduce((sum, i) => sum + i.qty_sent, 0);
    const got = items.reduce((sum, i) => sum + (i.qty_received ?? 0), 0);
    const value = items.reduce(
        (sum, i) => sum + timesQty(i.unit_cost ?? 0, i.qty_sent),
        0,
    );

    const incomplete = items.some((i) => {
        const r = receipt[i.id];

        return r !== undefined && r.qty < i.qty_sent && r.reason.trim() === '';
    });
    const ready = canReceive && !processing && !incomplete;
    const receive = () =>
        submit('post', `/app/transfers/${transfer.uuid}/receive`, {
            items: items.map((i) => ({
                item_id: i.id,
                qty_received: receipt[i.id]?.qty ?? i.qty_sent,
                short_reason: receipt[i.id]?.reason || null,
            })),
        });

    useHotkeys('page', [
        {
            id: 'transfer.receive',
            keys: 'mod+enter',
            labelKey: 'transfers.receive',
            allowInInput: true,
            handler: (e) => {
                e.preventDefault();
                if (ready) receive();
            },
        },
        {
            id: 'transfer.print',
            keys: 'p',
            labelKey: 'transfers.print',
            handler: print,
        },
        {
            id: 'transfer.back',
            keys: 'alt+arrowleft',
            labelKey: 'hotkeys.back',
            handler: () => navigateTo('/app/transfers'),
        },
    ]);

    const when = (iso: string | null) =>
        iso ? digits.n(iso.slice(0, 16).replace('T', ' ')) : '—';
    const lineError = Object.entries(errors).find(([k]) =>
        k.startsWith('items'),
    )?.[1];

    return (
        <>
            <Head title={transfer.number} />
            <Page className="max-w-6xl">
                <div className="flex flex-wrap items-start justify-between gap-3">
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
                            title={
                                <span className="inline-flex items-center gap-2">
                                    <ArrowLeftRight
                                        className="size-5 text-primary"
                                        aria-hidden="true"
                                    />
                                    {t('transfers.create_title')}
                                    <span className="num font-mono text-primary">
                                        {transfer.number}
                                    </span>
                                </span>
                            }
                            badge={
                                <StatusBadge
                                    status={transfer.status}
                                    {...(transfer.status === 'in_transit'
                                        ? { tone: 'warning' as const }
                                        : {})}
                                    label={t(
                                        `transfers.status.${transfer.status}`,
                                    )}
                                />
                            }
                            subtitle={
                                <span className="inline-flex flex-wrap items-center gap-1.5">
                                    <span>{branchLabel(transfer.from)}</span>
                                    <ArrowRight
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <span className="font-semibold text-foreground">
                                        {branchLabel(transfer.to)}
                                    </span>
                                    {' · '}
                                    <span className="num">
                                        {when(transfer.sent_at)}
                                    </span>
                                </span>
                            }
                        />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canReceive ? (
                            <Button
                                type="button"
                                className="min-h-11 md:min-h-9"
                                disabled={!ready}
                                onClick={receive}
                            >
                                <PackageCheck aria-hidden="true" />{' '}
                                {t('transfers.receive')}
                                <Kbd keys="mod+enter" onButton />
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11 md:min-h-9"
                            onClick={print}
                        >
                            <Printer aria-hidden="true" />{' '}
                            {t('transfers.print')}
                            <Kbd keys="p" onButton />
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <KpiTile
                        tone="primary"
                        label={t('transfers.summary_lines')}
                        value={digits.n(items.length)}
                        footnote={t('transfers.summary_route')}
                    />
                    <KpiTile
                        tone="info"
                        label={t('transfers.totals_sent')}
                        value={digits.qty(sent)}
                        footnote={`${t('transfers.dispatched_at')}: ${when(transfer.sent_at)}`}
                    />
                    <KpiTile
                        tone={
                            received
                                ? got < sent
                                    ? 'warning'
                                    : 'success'
                                : 'warning'
                        }
                        label={t('transfers.totals_received')}
                        value={
                            received
                                ? digits.qty(got)
                                : t('transfers.dispatch_pending')
                        }
                        footnote={
                            received
                                ? `${t('transfers.received_at')}: ${when(transfer.received_at)}`
                                : t(
                                      canReceive
                                          ? 'transfers.receive_pending'
                                          : 'transfers.receive_elsewhere',
                                      { branch: branchLabel(transfer.to) },
                                  )
                        }
                    />
                    {showCost ? (
                        <KpiTile
                            tone="default"
                            label={t('transfers.summary_value')}
                            value={digits.money(value)}
                            footnote={t('transfers.col_unit_cost')}
                        />
                    ) : (
                        <KpiTile
                            tone="default"
                            label={t('transfers.sent_by')}
                            value={transfer.sent_by ?? '—'}
                        />
                    )}
                </div>

                {/* Gate pass document */}
                <article
                    aria-labelledby="gp-title"
                    className="print-area space-y-4 rounded-xl border bg-surface p-5 text-xs shadow-xs"
                >
                    <div className="flex flex-wrap items-start justify-between gap-3 border-b pb-3">
                        <div>
                            <h2
                                id="gp-title"
                                className="text-xs font-bold tracking-widest text-primary uppercase"
                            >
                                {t('transfers.document_title')}
                            </h2>
                            {transfer.note ? (
                                <p className="mt-1 text-[11px] text-muted-foreground">
                                    {t('transfers.note')}:{' '}
                                    <span className="text-foreground">
                                        {transfer.note}
                                    </span>
                                </p>
                            ) : null}
                        </div>
                        <dl className="space-y-0.5 text-right text-[11px]">
                            <div>
                                <dt className="inline">
                                    {t('transfers.col_number')}:{' '}
                                </dt>
                                <dd className="num inline font-mono font-bold text-primary">
                                    {transfer.number}
                                </dd>
                            </div>
                            <div>
                                <dt className="inline">
                                    {t('transfers.dispatched_at')}:{' '}
                                </dt>
                                <dd className="num inline font-mono">
                                    {when(transfer.sent_at)}
                                </dd>
                            </div>
                            <div>
                                <dt className="inline">
                                    {t('common.status')}:{' '}
                                </dt>
                                <dd className="inline font-bold">
                                    {t(`transfers.status.${transfer.status}`)}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="rounded-lg border bg-surface-sunken p-2.5">
                            <div className="text-[10px] font-semibold text-muted-foreground uppercase">
                                {t('transfers.origin')}
                            </div>
                            <div className="text-sm font-bold">
                                {branchLabel(transfer.from)}
                            </div>
                            <div className="num text-[10px] text-muted-foreground">
                                {t('transfers.sent_by')}:{' '}
                                {transfer.sent_by ?? '—'} ·{' '}
                                {when(transfer.sent_at)}
                            </div>
                        </div>
                        <div className="rounded-lg border bg-surface-sunken p-2.5">
                            <div className="text-[10px] font-semibold text-muted-foreground uppercase">
                                {t('transfers.destination')}
                            </div>
                            <div className="text-sm font-bold text-primary">
                                {branchLabel(transfer.to)}
                            </div>
                            <div className="num text-[10px] text-muted-foreground">
                                {t('transfers.received_by')}:{' '}
                                {received
                                    ? `${transfer.received_by ?? '—'} · ${when(transfer.received_at)}`
                                    : t('transfers.dispatch_pending')}
                            </div>
                        </div>
                    </div>

                    <section aria-labelledby="ti-title">
                        <div className="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                            <h3 id="ti-title" className="text-xs font-bold">
                                {canReceive
                                    ? t('transfers.receive_title', {
                                          branch: branchLabel(transfer.to),
                                      })
                                    : t('transfers.items')}
                            </h3>
                            {canReceive ? (
                                <span className="text-[10px] text-muted-foreground">
                                    {t('transfers.receive_hint')}
                                </span>
                            ) : null}
                        </div>
                        {lineError ? (
                            <p role="alert" className="mb-2 text-destructive">
                                {lineError}
                            </p>
                        ) : null}
                        <div className="overflow-x-auto rounded-lg border">
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
                                            className={cn(TH, 'text-right')}
                                        >
                                            {t('transfers.col_sent')}
                                        </th>
                                        {showCost ? (
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
                                                        'transfers.col_line_value',
                                                    )}
                                                </th>
                                            </>
                                        ) : null}
                                        <th
                                            scope="col"
                                            className={cn(TH, 'text-right')}
                                        >
                                            {t('transfers.col_qty_received')}
                                        </th>
                                        <th scope="col" className={TH}>
                                            {t('transfers.col_short')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {items.map((i) => {
                                        const r = receipt[i.id] ?? {
                                            qty: i.qty_sent,
                                            reason: '',
                                        };
                                        const short = canReceive
                                            ? i.qty_sent - r.qty
                                            : i.qty_received === null
                                              ? 0
                                              : i.qty_sent - i.qty_received;

                                        return (
                                            <tr
                                                key={i.id}
                                                className="align-middle"
                                            >
                                                <th
                                                    scope="row"
                                                    className="px-3 py-2 text-left font-semibold"
                                                >
                                                    {i.product}
                                                    {i.variant_label
                                                        ? ` · ${i.variant_label}`
                                                        : ''}
                                                </th>
                                                <td className="num px-3 py-2 font-mono text-muted-foreground">
                                                    {i.sku}
                                                </td>
                                                <td className="num px-3 py-2 text-right font-mono font-bold">
                                                    {digits.qty(i.qty_sent)}
                                                </td>
                                                {showCost ? (
                                                    <>
                                                        <td className="num px-3 py-2 text-right text-muted-foreground">
                                                            {i.unit_cost ===
                                                            null
                                                                ? '—'
                                                                : digits.money(
                                                                      i.unit_cost,
                                                                  )}
                                                        </td>
                                                        <td className="num px-3 py-2 text-right font-bold">
                                                            {i.unit_cost ===
                                                            null
                                                                ? '—'
                                                                : digits.money(
                                                                      timesQty(
                                                                          i.unit_cost,
                                                                          i.qty_sent,
                                                                      ),
                                                                  )}
                                                        </td>
                                                    </>
                                                ) : null}
                                                <td className="px-3 py-2 text-right">
                                                    {canReceive ? (
                                                        <div className="flex justify-end">
                                                            <QtyStepper
                                                                value={r.qty}
                                                                min={0}
                                                                max={i.qty_sent}
                                                                onChange={(v) =>
                                                                    setReceipt(
                                                                        (
                                                                            prev,
                                                                        ) => ({
                                                                            ...prev,
                                                                            [i.id]: {
                                                                                ...r,
                                                                                qty: v,
                                                                            },
                                                                        }),
                                                                    )
                                                                }
                                                                aria-label={`${t('transfers.col_qty_received')} ${i.product}`}
                                                            />
                                                        </div>
                                                    ) : (
                                                        <span className="num font-mono font-semibold">
                                                            {i.qty_received ===
                                                            null
                                                                ? '—'
                                                                : digits.qty(
                                                                      i.qty_received,
                                                                  )}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {canReceive ? (
                                                        short > 0 ? (
                                                            <div className="grid gap-1">
                                                                <span className="text-[11px] font-semibold text-destructive">
                                                                    {t(
                                                                        'transfers.short_by',
                                                                        {
                                                                            qty: digits.qty(
                                                                                short,
                                                                            ),
                                                                        },
                                                                    )}
                                                                </span>
                                                                <Input
                                                                    required
                                                                    aria-label={`${t('transfers.short_reason')} ${i.product}`}
                                                                    placeholder={t(
                                                                        'transfers.short_reason_placeholder',
                                                                    )}
                                                                    value={
                                                                        r.reason
                                                                    }
                                                                    aria-invalid={
                                                                        r.reason.trim() ===
                                                                        ''
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        setReceipt(
                                                                            (
                                                                                prev,
                                                                            ) => ({
                                                                                ...prev,
                                                                                [i.id]: {
                                                                                    ...r,
                                                                                    reason: e
                                                                                        .target
                                                                                        .value,
                                                                                },
                                                                            }),
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                —
                                                            </span>
                                                        )
                                                    ) : short > 0 ? (
                                                        <span>
                                                            <span className="font-semibold text-destructive">
                                                                {t(
                                                                    'transfers.short_by',
                                                                    {
                                                                        qty: digits.qty(
                                                                            short,
                                                                        ),
                                                                    },
                                                                )}
                                                            </span>
                                                            {i.short_reason
                                                                ? ` · ${i.short_reason}`
                                                                : ''}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                                <tfoot className="border-t bg-surface-sunken font-bold">
                                    <tr>
                                        <th
                                            scope="row"
                                            colSpan={2}
                                            className="px-3 py-2 text-left"
                                        >
                                            {t('table.total', {
                                                n: digits.n(items.length),
                                            })}
                                        </th>
                                        <td className="num px-3 py-2 text-right font-mono">
                                            {digits.qty(sent)}
                                        </td>
                                        {showCost ? (
                                            <>
                                                <td aria-hidden="true" />
                                                <td className="num px-3 py-2 text-right">
                                                    {digits.money(value)}
                                                </td>
                                            </>
                                        ) : null}
                                        <td className="num px-3 py-2 text-right font-mono">
                                            {received
                                                ? digits.qty(got)
                                                : canReceive
                                                  ? digits.qty(
                                                        items.reduce(
                                                            (sum, i) =>
                                                                sum +
                                                                (receipt[i.id]
                                                                    ?.qty ??
                                                                    i.qty_sent),
                                                            0,
                                                        ),
                                                    )
                                                  : '—'}
                                        </td>
                                        <td aria-hidden="true" />
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </section>

                    <div className="grid gap-4 border-t pt-3 text-[10px] text-muted-foreground sm:grid-cols-2">
                        <div>
                            {t('transfers.signature_sender')}:
                            ___________________________
                            <div className="mt-1">{transfer.sent_by ?? ''}</div>
                        </div>
                        <div className="sm:text-right">
                            {t('transfers.signature_receiver')}:
                            ___________________________
                            <div className="mt-1">
                                {transfer.received_by ?? ''}
                            </div>
                        </div>
                    </div>
                </article>

                {canReceive ? (
                    <div className="flex justify-end gap-2 print:hidden">
                        <Button
                            type="button"
                            className="min-h-11 font-bold md:min-h-9"
                            disabled={!ready}
                            onClick={receive}
                        >
                            <PackageCheck aria-hidden="true" />{' '}
                            {t('transfers.receive')}
                            <Kbd keys="mod+enter" onButton />
                        </Button>
                    </div>
                ) : null}
            </Page>
        </>
    );
}
