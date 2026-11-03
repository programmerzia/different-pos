import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { DataTable, type DataColumn } from '@/components/data-table/data-table';
import { DateRangePicker } from '@/components/date-range-picker';
import { KpiTile } from '@/components/kpi-tile';
import {
    ExportMenu,
    Page,
    PageHeader,
    PrintButton,
} from '@/components/page-header';
import {
    REPORT_PERIODS,
    type ReportPeriod,
    fiscalYearLabel,
    reportPeriodRange,
} from '@/components/reports/report-periods';
import { TableToolbar, type ToolbarFilter } from '@/components/table-toolbar';
import { useDigits, type Digits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { i18n, useT } from '@/i18n';
import { downloadCsv } from '@/lib/csv-download';
import type { ReportColumn, ReportRow, ReportShowProps } from '@/types/reports';

const LIMITS = ['10', '50', '100', '500'];

function cellText(
    column: ReportColumn,
    value: string | number | null,
    digits: Digits,
    t: (key: string) => string,
): string {
    if (value === null || value === '') {
        return column.type === 'date' || column.type === 'text' ? '-' : '';
    }
    switch (column.type) {
        case 'money':
            return digits.money(Number(value));
        case 'qty':
            return digits.qty(Number(value));
        case 'int':
            return digits.n(Number(value));
        case 'date':
            return digits.n(String(value));
        default:
            return column.valuePrefix
                ? t(`${column.valuePrefix}${String(value)}`)
                : digits.n(String(value));
    }
}

export default function ReportShow({
    report,
    params,
    result,
    canAllBranches,
    canExport,
    options,
}: ReportShowProps) {
    const { t } = useT();
    const digits = useDigits();
    const [sort, setSort] = useState<string | null>(null);
    const [page, setPage] = useState(1);
    const [search, setSearch] = useState('');
    const [exporting, setExporting] = useState(false);
    const [today] = useState(() => new Date());
    const perPage = 50;

    const preset: ReportPeriod | 'custom' =
        REPORT_PERIODS.find((key) => {
            const range = reportPeriodRange(key, today);

            return range.from === params.from && range.to === params.to;
        }) ?? 'custom';
    const [customOpen, setCustomOpen] = useState(preset === 'custom');

    const query = (next: Partial<typeof params>) => {
        const merged = { ...params, ...next };
        const data: Record<string, string> = {};
        if (report.usesPeriod) {
            data['from'] = merged.from;
            data['to'] = merged.to;
        }
        if (merged.branch !== 'current') {
            data['branch'] = merged.branch;
        }
        for (const [key, value] of Object.entries(merged.filters)) {
            if (value !== '') {
                data[`filter[${key}]`] = value;
            }
        }

        return data;
    };

    const visit = (next: Partial<typeof params>) =>
        router.get(`/app/reports/${report.name}`, query(next), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    const setFilter = (key: string, value: string) =>
        visit({ filters: { ...params.filters, [key]: value } });

    const exportCsv = async () => {
        setExporting(true);
        try {
            const search = new URLSearchParams(query({})).toString();
            await downloadCsv(
                `/app/reports/${report.name}/export${search ? `?${search}` : ''}`,
            );
        } catch {
            toast.error(t('reports.export_failed'));
        } finally {
            setExporting(false);
        }
    };

    useHotkeys('list', [
        ...(canExport
            ? [
                  {
                      id: 'report.export',
                      keys: 'e',
                      labelKey: 'reports.export',
                      handler: () => void exportCsv(),
                  },
              ]
            : []),
        {
            id: 'report.print',
            keys: 'p',
            labelKey: 'reports.print',
            handler: () => window.print(),
        },
    ]);

    // Per-method cash columns carry the tenant's own method names.
    for (const column of result.columns) {
        if (column.key.startsWith('method_')) {
            for (const lng of ['en', 'bn']) {
                i18n.addResource(
                    lng,
                    'translation',
                    `reports.col.${column.key}`,
                    column.label,
                );
            }
        }
    }

    // Client-side search over the cells as displayed.
    const filtered = useMemo(() => {
        const needle = digits.latin(search.trim()).toLowerCase();
        if (needle === '') {
            return result.rows;
        }

        return result.rows.filter((row) =>
            result.columns.some((column) =>
                digits
                    .latin(cellText(column, row[column.key] ?? null, digits, t))
                    .toLowerCase()
                    .includes(needle),
            ),
        );
    }, [result.rows, result.columns, search, digits, t]);

    const sorted = useMemo(() => {
        if (!sort) {
            return filtered;
        }
        const key = sort.replace(/^-/, '');
        const direction = sort.startsWith('-') ? -1 : 1;

        return [...filtered].sort((a, b) => {
            const x = a[key] ?? '';
            const y = b[key] ?? '';
            if (typeof x === 'number' && typeof y === 'number') {
                return (x - y) * direction;
            }

            return String(x).localeCompare(String(y)) * direction;
        });
    }, [filtered, sort]);

    const lastPage = Math.max(1, Math.ceil(sorted.length / perPage));
    const current = Math.min(page, lastPage);
    const start = (current - 1) * perPage;
    const pageRows = sorted
        .slice(start, start + perPage)
        .map((row, i) => ({ ...row, __id: String(start + i) }));

    const columns: DataColumn<ReportRow>[] = result.columns.map(
        (column, index) => ({
            id: column.key,
            headerKey: `reports.col.${column.key}`,
            sortKey: column.key,
            numeric:
                column.type === 'money' ||
                column.type === 'qty' ||
                column.type === 'int',
            required: index === 0,
            ...(index === 0 ? { mobile: 'title' as const } : {}),
            cell: (row) => cellText(column, row[column.key] ?? null, digits, t),
        }),
    );

    // Totals: the server's for the whole result, re-summed when a search narrows the rows.
    const totalColumns = result.columns.filter(
        (c) => result.totals && c.key in result.totals,
    );
    const totals: Record<string, number> = {};
    for (const column of totalColumns) {
        totals[column.key] =
            filtered === result.rows
                ? (result.totals?.[column.key] ?? 0)
                : filtered.reduce(
                      (sum, row) => sum + Number(row[column.key] ?? 0),
                      0,
                  );
    }
    const tileColumns = totalColumns
        .filter(
            (c) => c.type === 'money' || c.type === 'int' || c.type === 'qty',
        )
        .slice(-4);

    const toggleSort = (key: string) => {
        setPage(1);
        setSort((s) => (s === key ? `-${key}` : s === `-${key}` ? null : key));
    };

    const onSearch = (text: string) => {
        setPage(1);
        setSearch(text);
    };

    const toolbarFilters: ToolbarFilter[] = [];
    if (canAllBranches) {
        toolbarFilters.push({
            key: 'branch',
            labelKey: 'reports.branch.current',
            value: params.branch === 'current' ? '' : params.branch,
            options: [{ value: 'all', label: t('reports.branch.all') }],
            onChange: (value) =>
                visit({ branch: value === '' ? 'current' : value }),
        });
    }
    if (report.filters.includes('category')) {
        toolbarFilters.push({
            key: 'category',
            labelKey: 'reports.filter.all_categories',
            value: params.filters['category'] ?? '',
            options: options.categories.map((c) => ({
                value: String(c.id),
                label: c.name,
            })),
            onChange: (value) => setFilter('category', value),
        });
    }
    if (report.filters.includes('brand')) {
        toolbarFilters.push({
            key: 'brand',
            labelKey: 'reports.filter.all_brands',
            value: params.filters['brand'] ?? '',
            options: options.brands.map((b) => ({
                value: String(b.id),
                label: b.name,
            })),
            onChange: (value) => setFilter('brand', value),
        });
    }
    if (report.filters.includes('bucket')) {
        toolbarFilters.push({
            key: 'bucket',
            labelKey: 'reports.filter.all_buckets',
            value: params.filters['bucket'] ?? '',
            options: options.buckets.map((b) => ({
                value: b,
                label: t(`reports.bucket.${b}`),
            })),
            onChange: (value) => setFilter('bucket', value),
        });
    }
    if (report.filters.includes('limit')) {
        toolbarFilters.push({
            key: 'limit',
            labelKey: 'reports.filter.limit_default',
            value:
                (params.filters['limit'] ?? '') === '100'
                    ? ''
                    : (params.filters['limit'] ?? ''),
            options: LIMITS.filter((l) => l !== '100').map((l) => ({
                value: l,
                label: t('reports.filter.top_n', { n: digits.n(l) }),
            })),
            onChange: (value) => setFilter('limit', value),
        });
    }

    const title = t(`reports.items.${report.name}.title`);

    return (
        <>
            <Head title={title} />
            <Page>
                <Link
                    href="/app/reports"
                    className="inline-flex items-center gap-1 self-start text-xs font-medium text-muted-foreground hover:text-foreground hover:underline print:hidden"
                >
                    <ArrowLeft className="size-3.5" aria-hidden="true" />
                    {t('reports.back')}
                </Link>
                <PageHeader
                    className="print:hidden"
                    title={title}
                    count={filtered.length}
                    subtitle={t(`reports.items.${report.name}.desc`)}
                    actions={
                        <>
                            <ExportMenu
                                items={
                                    canExport && !exporting
                                        ? [
                                              {
                                                  id: 'csv',
                                                  kind: 'csv',
                                                  label: t('reports.export'),
                                                  onSelect: () =>
                                                      void exportCsv(),
                                              },
                                          ]
                                        : []
                                }
                            />
                            <PrintButton label={t('reports.print')} />
                        </>
                    }
                />

                <TableToolbar
                    className="print:hidden"
                    search={search}
                    onSearch={onSearch}
                    placeholderKey="reports.search"
                    {...(report.usesPeriod
                        ? {
                              date: {
                                  value: customOpen ? 'custom' : preset,
                                  options: [
                                      ...REPORT_PERIODS.map((key) => ({
                                          value: key,
                                          label: t(
                                              `reports.directory.periods.${key}`,
                                              {
                                                  year: digits.n(
                                                      fiscalYearLabel(today),
                                                  ),
                                              },
                                          ),
                                      })),
                                      {
                                          value: 'custom',
                                          label: t(
                                              'reports.directory.periods.custom',
                                          ),
                                      },
                                  ],
                                  onChange: (value: string) => {
                                      if (value === 'custom') {
                                          setCustomOpen(true);

                                          return;
                                      }
                                      setCustomOpen(false);
                                      visit(
                                          reportPeriodRange(
                                              value as ReportPeriod,
                                              today,
                                          ),
                                      );
                                  },
                              },
                              dateControl:
                                  customOpen || preset === 'custom' ? (
                                      <DateRangePicker
                                          value={{
                                              from: params.from,
                                              to: params.to,
                                          }}
                                          onChange={(range) => visit(range)}
                                      />
                                  ) : null,
                          }
                        : {})}
                    filters={toolbarFilters}
                >
                    {report.usesPeriod ? null : (
                        <span className="text-xs text-muted-foreground">
                            {t('reports.as_of')}
                        </span>
                    )}
                </TableToolbar>

                <div className="print-area flex flex-col gap-3">
                    <h2 className="hidden text-xl font-bold print:block">
                        {title}
                        {report.usesPeriod
                            ? ` (${digits.n(params.from)} - ${digits.n(params.to)})`
                            : ''}
                    </h2>
                    {tileColumns.length > 0 && filtered.length > 0 ? (
                        <dl
                            aria-label={t('reports.total_row')}
                            className="grid grid-cols-2 gap-3 md:grid-cols-4"
                        >
                            {tileColumns.map((column) => (
                                <div key={column.key} className="contents">
                                    <dt className="sr-only">
                                        {t(`reports.col.${column.key}`, {
                                            defaultValue: column.label,
                                        })}
                                    </dt>
                                    <dd className="contents">
                                        <KpiTile
                                            label={t(
                                                `reports.col.${column.key}`,
                                                { defaultValue: column.label },
                                            )}
                                            value={cellText(
                                                column,
                                                totals[column.key] ?? 0,
                                                digits,
                                                t,
                                            )}
                                        />
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    ) : null}
                    <DataTable
                        entity="report"
                        caption={title}
                        rows={pageRows}
                        columns={columns}
                        meta={{
                            page: current,
                            perPage,
                            total: sorted.length,
                            lastPage,
                        }}
                        getRowId={(row) => String(row['__id'])}
                        sort={sort}
                        onSort={toggleSort}
                        onPage={setPage}
                        selectable={false}
                        {...(totalColumns.length > 0 && filtered.length > 0
                            ? {
                                  totals: {
                                      label: t('reports.total_label', {
                                          n: digits.n(filtered.length),
                                      }),
                                      cells: Object.fromEntries(
                                          totalColumns.map((column) => [
                                              column.key,
                                              cellText(
                                                  column,
                                                  totals[column.key] ?? 0,
                                                  digits,
                                                  t,
                                              ),
                                          ]),
                                      ),
                                  },
                              }
                            : {})}
                    />
                </div>
            </Page>
        </>
    );
}

ReportShow.layout = {
    breadcrumbs: [{ title: 'Reports', href: '/app/reports' }],
};
