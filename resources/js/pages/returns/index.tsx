import { Head, Link, router, usePage } from '@inertiajs/react';
import { FileText, Plus, RefreshCw, Undo2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    ColumnChooser,
    DataTable,
    useColumnVisibility,
    type BulkAction,
    type DataColumn,
    type TableMeta,
} from '@/components/data-table/data-table';
import {
    buildTableQuery,
    useServerTable,
} from '@/components/data-table/server-table';
import { DateRangePicker } from '@/components/date-range-picker';
import { EmptyState } from '@/components/empty-state';
import { Kbd } from '@/components/kbd';
import {
    ExportMenu,
    Page,
    PageHeader,
    PrintButton,
} from '@/components/page-header';
import { DateTime } from '@/components/sales/date-time';
import { FiltersDialog } from '@/components/sales/filters-dialog';
import {
    DATE_PRESETS,
    presetOf,
    presetRange,
    type DatePreset,
} from '@/components/sales/list-tools';
import {
    ReturnWizard,
    type WizardData,
} from '@/components/sales/return-wizard';
import { StatusBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { moneyToDecimalString } from '@/engine/money';
import { useCan } from '@/hooks/use-can';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { downloadRowsCsv, type CsvColumn } from '@/lib/rows-csv';
import { salesUrl, type CustomerSummary } from '@/lib/sales';

type Row = {
    id: number;
    number: string;
    returned_at: string | null;
    sale: { id: number; number: string };
    customer: CustomerSummary | null;
    items: number;
    restocked: number;
    lines: { name: string; qty: number }[];
    total: number;
    refunded: number;
    credited: number;
    refund_mode: string;
    refund_method: string | null;
    reason: string;
    cashier: string | null;
    exchange_sale: { id: number; number: string; difference: number } | null;
};

type Props = {
    rows: Row[];
    meta: TableMeta;
    totals: { total: number; refunded: number; credited: number };
    filters: Record<string, string>;
    reasons: string[];
    cashiers: { id: number; name: string }[];
    wizard?: WizardData;
};

/** Reason chips of the mockup ledger (the rest are under More filters). */
const CHIP_REASONS = ['size', 'defect', 'changed_mind', 'colour'];
const MORE_FILTERS = ['reason', 'mode', 'cashier'] as const;

export default function ReturnsIndex({
    rows,
    meta,
    totals,
    filters,
    reasons,
    cashiers,
    wizard,
}: Props) {
    const { t } = useT();
    const digits = useDigits();
    const can = useCan();
    const table = useServerTable();
    const url = usePage().url;
    const [moreOpen, setMoreOpen] = useState(false);
    const [customRange, setCustomRange] = useState(false);
    const [wizardOpen, setWizardOpen] = useState(false);
    const [wizardLoading, setWizardLoading] = useState(false);

    const setFilters = (next: Record<string, string>) =>
        router.get(
            salesUrl.returnsIndex,
            buildTableQuery({
                ...table.state,
                filters: { ...table.state.filters, ...next },
                page: 1,
            }),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    // The wizard's data is the lazy `wizard` prop, reloaded alone (wizard_q / wizard_sale).
    const loadWizard = (data: { wizard_q?: string; wizard_sale?: number }) =>
        router.reload({
            only: ['wizard'],
            data,
            onStart: () => setWizardLoading(true),
            onFinish: () => setWizardLoading(false),
        });
    const openWizard = () => {
        if (!can('returns.create')) {
            return;
        }
        setWizardOpen(true);
        loadWizard({ wizard_q: '' });
    };
    const closeWizard = () => {
        setWizardOpen(false);
        if (url.includes('wizard_')) {
            const params = new URLSearchParams(
                url.includes('?') ? url.slice(url.indexOf('?') + 1) : '',
            );
            params.delete('wizard_q');
            params.delete('wizard_sale');
            const rest = params.toString();
            router.replace({
                url: `${salesUrl.returnsIndex}${rest ? `?${rest}` : ''}`,
                preserveState: true,
                preserveScroll: true,
            });
        }
    };

    const csvColumns: CsvColumn<Row>[] = [
        { header: t('returns.col_number'), value: (r) => r.number },
        { header: t('returns.col_sale'), value: (r) => r.sale.number },
        { header: t('returns.col_date'), value: (r) => r.returned_at ?? '' },
        {
            header: t('sales.col_customer'),
            value: (r) =>
                r.customer && !r.customer.walk_in
                    ? r.customer.name
                    : t('sales.walk_in'),
        },
        { header: t('returns.col_items'), value: (r) => itemsText(r, digits) },
        {
            header: t('returns.col_total'),
            value: (r) => moneyToDecimalString(r.total),
        },
        {
            header: t('returns.col_refunded'),
            value: (r) => moneyToDecimalString(r.refunded),
        },
        {
            header: t('returns.col_credited'),
            value: (r) => moneyToDecimalString(r.credited),
        },
        {
            header: t('returns.col_method'),
            value: (r) => methodLabel(r, t),
        },
        {
            header: t('returns.col_reason'),
            value: (r) =>
                t(`returns.reason_${r.reason}`, { defaultValue: r.reason }),
        },
        { header: t('returns.col_cashier'), value: (r) => r.cashier ?? '' },
    ];
    const exportRows = (list: Row[]) =>
        downloadRowsCsv('returns.csv', csvColumns, list);

    useHotkeys('list', [
        {
            id: 'returns.search',
            keys: '/',
            labelKey: 'hotkeys.search',
            handler: (e) => {
                e.preventDefault();
                document.getElementById('page-search')?.focus();
            },
        },
        {
            id: 'returns.new',
            keys: 'n',
            labelKey: 'returns.wizard',
            handler: openWizard,
        },
        {
            id: 'returns.pos_return',
            keys: 'f11',
            labelKey: 'returns.pos_return',
            handler: (e) => {
                e.preventDefault();
                openWizard();
            },
        },
        {
            id: 'returns.export',
            keys: 'shift+e',
            labelKey: 'returns.export_csv',
            handler: () => exportRows(rows),
        },
        {
            id: 'returns.more_filters',
            keys: 'shift+f',
            labelKey: 'table.more_filters',
            handler: () => setMoreOpen(true),
        },
    ]);

    const columns = useMemo<DataColumn<Row>[]>(
        () => [
            {
                id: 'number',
                headerKey: 'returns.col_number',
                sortKey: 'number',
                required: true,
                mobile: 'title',
                cell: (r) => (
                    <span className="num font-mono font-bold text-warning">
                        {r.number}
                    </span>
                ),
            },
            {
                id: 'sale',
                headerKey: 'returns.col_sale',
                cell: (r) => (
                    <Link
                        href={salesUrl.show(r.sale.id)}
                        className="num font-mono text-primary hover:underline"
                    >
                        {r.sale.number}
                    </Link>
                ),
            },
            {
                id: 'date',
                headerKey: 'returns.col_date',
                sortKey: 'returned_at',
                cell: (r) => <DateTime value={r.returned_at} />,
            },
            {
                id: 'customer',
                headerKey: 'sales.col_customer',
                cell: (r) => (
                    <span className="font-medium">
                        {r.customer && !r.customer.walk_in
                            ? r.customer.name
                            : t('sales.walk_in')}
                    </span>
                ),
            },
            {
                id: 'items',
                headerKey: 'returns.col_items',
                cell: (r) => (
                    <span className="block max-w-xs truncate">
                        {itemsText(r, digits)}
                    </span>
                ),
            },
            {
                id: 'total',
                headerKey: 'returns.col_total',
                sortKey: 'total',
                numeric: true,
                cell: (r) => (
                    <span className="font-bold text-destructive">
                        −{digits.money(r.total)}
                    </span>
                ),
            },
            {
                id: 'net',
                headerKey: 'returns.col_net',
                numeric: true,
                cell: (r) => (
                    <span className="font-bold text-foreground">
                        {netLabel(r, t, digits)}
                    </span>
                ),
            },
            {
                id: 'method',
                headerKey: 'returns.col_method',
                cell: (r) => (
                    <span className="rounded-full border bg-surface-sunken px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap text-foreground">
                        {methodLabel(r, t)}
                    </span>
                ),
            },
            {
                id: 'reason',
                headerKey: 'returns.col_reason',
                cell: (r) => (
                    <StatusBadge
                        shape="tag"
                        status={r.reason}
                        tone={r.reason === 'defect' ? 'danger' : 'warning'}
                        className="font-bold"
                        label={t(`returns.reason_${r.reason}`, {
                            defaultValue: r.reason,
                        })}
                    />
                ),
            },
            {
                id: 'restock',
                headerKey: 'returns.col_restock',
                hidden: true,
                cell: (r) => (
                    <StatusBadge
                        status="restock"
                        tone={
                            r.restocked === r.items
                                ? 'success'
                                : r.restocked === 0
                                  ? 'neutral'
                                  : 'warning'
                        }
                        label={
                            r.restocked === r.items
                                ? t('returns.restocked')
                                : r.restocked === 0
                                  ? t('returns.not_restocked')
                                  : t('returns.part_restocked')
                        }
                    />
                ),
            },
            {
                id: 'cashier',
                headerKey: 'returns.col_cashier',
                cell: (r) => (
                    <span className="text-muted-foreground">
                        {r.cashier ?? '—'}
                    </span>
                ),
            },
        ],
        [digits, t],
    );
    const columnState = useColumnVisibility(columns);

    const bulkActions: BulkAction<Row>[] = [
        {
            id: 'export',
            labelKey: 'returns.export_selected',
            icon: FileText,
            run: exportRows,
        },
    ];

    const view =
        filters['reason'] === 'defect' && !filters['mode']
            ? 'defect'
            : filters['mode'] === 'refund' && !filters['reason']
              ? 'refunds'
              : '';
    const setView = (value: string) =>
        setFilters({
            reason: value === 'defect' ? 'defect' : '',
            mode: value === 'refunds' ? 'refund' : '',
        });

    const from = filters['from'] ?? '';
    const to = filters['to'] ?? '';
    const preset: DatePreset = customRange ? 'custom' : presetOf(from, to);
    const setPreset = (value: DatePreset) => {
        setCustomRange(value === 'custom');
        if (value !== 'custom') {
            setFilters(presetRange(value));
        }
    };

    const moreCount = MORE_FILTERS.filter((k) => filters[k]).length;

    return (
        <>
            <Head title={t('returns.title')} />
            <Page fill>
                <PageHeader
                    title={t('returns.ledger_title')}
                    count={meta.total}
                    subtitle={t('returns.ledger_subtitle')}
                    actions={
                        <>
                            {can('returns.create') ? (
                                <>
                                    <Button type="button" onClick={openWizard}>
                                        <Plus aria-hidden="true" />
                                        {t('returns.wizard')}
                                    </Button>
                                    <Button
                                        type="button"
                                        className="bg-warning text-destructive-foreground hover:bg-warning/90"
                                        onClick={openWizard}
                                    >
                                        <RefreshCw aria-hidden="true" />
                                        {t('returns.pos_return')}
                                        <Kbd keys="f11" onButton />
                                    </Button>
                                </>
                            ) : null}
                            <ColumnChooser
                                columns={columns}
                                visibility={columnState.visibility}
                                onChange={columnState.setVisibility}
                            />
                            <ExportMenu
                                items={[
                                    {
                                        id: 'csv',
                                        kind: 'csv',
                                        label: t('returns.export_csv'),
                                        onSelect: () => exportRows(rows),
                                    },
                                ]}
                            />
                            <PrintButton />
                        </>
                    }
                />
                <TableToolbar
                    search={table.searchText}
                    onSearch={table.setSearch}
                    placeholderKey="returns.search"
                    view={{
                        value: view,
                        options: [
                            { value: '', label: t('returns.view_default') },
                            {
                                value: 'defect',
                                label: t('returns.view_defect'),
                            },
                            {
                                value: 'refunds',
                                label: t('returns.view_refunds'),
                            },
                        ],
                        onChange: setView,
                    }}
                    date={{
                        value: preset,
                        options: DATE_PRESETS.map((p) => ({
                            value: p,
                            label: t(`sales.date_${p || 'all'}`),
                        })),
                        onChange: (v) => setPreset(v as DatePreset),
                    }}
                    dateControl={
                        preset === 'custom' ? (
                            <DateRangePicker
                                value={{ from, to }}
                                onChange={(range) =>
                                    setFilters({
                                        from: range.from,
                                        to: range.to,
                                    })
                                }
                            />
                        ) : undefined
                    }
                    chips={[
                        {
                            key: 'reason',
                            label: t('returns.col_reason'),
                            value: CHIP_REASONS.includes(
                                filters['reason'] ?? '',
                            )
                                ? (filters['reason'] ?? '')
                                : '',
                            options: [
                                { value: '', label: t('returns.all_reasons') },
                                ...CHIP_REASONS.map((r) => ({
                                    value: r,
                                    label: t(`returns.chip_${r}`),
                                })),
                            ],
                            onChange: (v) => table.setFilter('reason', v),
                        },
                    ]}
                    moreFilters={{
                        count: moreCount,
                        onOpen: () => setMoreOpen(true),
                    }}
                />
                <DataTable<Row>
                    entity="sale_return"
                    caption={t('returns.title')}
                    rows={rows}
                    columns={columns}
                    meta={meta}
                    getRowId={(r) => String(r.id)}
                    rowHref={(r) => salesUrl.returnShow(r.id)}
                    bulkActions={bulkActions}
                    sort={table.state.sort}
                    onSort={table.toggleSort}
                    onPage={table.setPage}
                    onPerPage={table.setPerPage}
                    loading={table.loading}
                    chooser="none"
                    visibility={columnState.visibility}
                    onVisibilityChange={columnState.setVisibility}
                    fill
                    totals={{
                        cells: {
                            total: (
                                <span className="text-destructive">
                                    −{digits.money(totals.total)}
                                </span>
                            ),
                            net: t('returns.net_refund_total', {
                                amount: digits.money(totals.refunded),
                            }),
                        },
                    }}
                    empty={
                        <EmptyState
                            icon={Undo2}
                            title={t('returns.empty')}
                            description={t('returns.empty_hint')}
                            action={
                                can('returns.create') ? (
                                    <Button type="button" onClick={openWizard}>
                                        <Plus aria-hidden="true" />
                                        {t('returns.wizard')}
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </Page>
            <ReturnWizard
                open={wizardOpen}
                data={wizard ?? null}
                loading={wizardLoading}
                onSearch={(q) => loadWizard({ wizard_q: q })}
                onPickSale={(id) =>
                    loadWizard(
                        id === null
                            ? { wizard_q: '', wizard_sale: 0 }
                            : { wizard_sale: id },
                    )
                }
                onClose={closeWizard}
            />
            <FiltersDialog
                open={moreOpen}
                onOpenChange={setMoreOpen}
                values={filters}
                onApply={setFilters}
                fields={[
                    {
                        key: 'reason',
                        label: t('returns.col_reason'),
                        options: [
                            { value: '', label: t('returns.all_reasons') },
                            ...reasons.map((r) => ({
                                value: r,
                                label: t(`returns.reason_${r}`),
                            })),
                        ],
                    },
                    {
                        key: 'mode',
                        label: t('returns.col_mode'),
                        options: [
                            { value: '', label: t('returns.all_modes') },
                            ...['refund', 'credit', 'exchange'].map((m) => ({
                                value: m,
                                label: t(`returns.mode_${m}`),
                            })),
                        ],
                    },
                    {
                        key: 'cashier',
                        label: t('returns.processed_by'),
                        options: [
                            { value: '', label: t('returns.all_staff') },
                            ...cashiers.map((c) => ({
                                value: String(c.id),
                                label: c.name,
                            })),
                        ],
                    },
                ]}
            />
        </>
    );
}

type Digits = ReturnType<typeof useDigits>;
type T = ReturnType<typeof useT>['t'];

function itemsText(r: Row, digits: Digits): string {
    return r.lines.map((l) => `${digits.qty(l.qty)}× ${l.name}`).join(', ');
}

function methodLabel(r: Row, t: T): string {
    if (r.refund_mode === 'refund') {
        return r.refund_method ?? t('returns.mode_refund');
    }

    return t(`returns.mode_${r.refund_mode}`);
}

function netLabel(r: Row, t: T, digits: Digits): string {
    if (r.exchange_sale) {
        return r.exchange_sale.difference >= 0
            ? t('returns.net_pays', {
                  amount: digits.money(r.exchange_sale.difference),
              })
            : t('returns.net_gets', {
                  amount: digits.money(-r.exchange_sale.difference),
              });
    }
    if (r.refunded > 0) {
        return t('returns.net_refund_total', {
            amount: digits.money(r.refunded),
        });
    }
    if (r.credited > 0) {
        return t('returns.net_credit', { amount: digits.money(r.credited) });
    }

    return digits.money(0);
}
