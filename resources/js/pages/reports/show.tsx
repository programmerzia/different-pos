import { Head, Link, router } from '@inertiajs/react';
import { Download, Printer } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { DataTable, type DataColumn } from '@/components/data-table/data-table';
import { DateRangePicker } from '@/components/date-range-picker';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useDigits, type Digits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { i18n, useT } from '@/i18n';
import { downloadCsv } from '@/lib/csv-download';
import type { ReportColumn, ReportRow, ReportShowProps } from '@/types/reports';

const ANY = '__any';
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
    const [exporting, setExporting] = useState(false);
    const perPage = 50;

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
        visit({
            filters: {
                ...params.filters,
                [key]: value === ANY ? '' : value,
            },
        });

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

    const sorted = useMemo(() => {
        if (!sort) {
            return result.rows;
        }
        const key = sort.replace(/^-/, '');
        const direction = sort.startsWith('-') ? -1 : 1;

        return [...result.rows].sort((a, b) => {
            const x = a[key] ?? '';
            const y = b[key] ?? '';
            if (typeof x === 'number' && typeof y === 'number') {
                return (x - y) * direction;
            }

            return String(x).localeCompare(String(y)) * direction;
        });
    }, [result.rows, sort]);

    const lastPage = Math.max(1, Math.ceil(sorted.length / perPage));
    const current = Math.min(page, lastPage);
    const start = (current - 1) * perPage;
    const pageRows = sorted
        .slice(start, start + perPage)
        .map((row, i) => ({ ...row, __id: String(start + i) }));

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

    const totalColumns = result.columns.filter(
        (c) => result.totals && c.key in result.totals,
    );

    const toggleSort = (key: string) => {
        setPage(1);
        setSort((s) => (s === key ? `-${key}` : s === `-${key}` ? null : key));
    };

    const select = (
        label: string,
        value: string,
        items: { value: string; label: string }[],
        onChange: (value: string) => void,
    ) => (
        <label className="flex flex-col gap-1 text-xs text-muted-foreground">
            {label}
            <Select value={value || ANY} onValueChange={onChange}>
                <SelectTrigger className="min-h-11 min-w-40 md:min-h-9">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {items.map((item) => (
                        <SelectItem key={item.value} value={item.value}>
                            {item.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </label>
    );

    const any = { value: ANY, label: t('reports.filter.any') };
    const title = t(`reports.items.${report.name}.title`);

    return (
        <>
            <Head title={title} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3 print:hidden">
                    <div>
                        <Link
                            href="/app/reports"
                            className="text-sm text-muted-foreground hover:underline"
                        >
                            {t('reports.back')}
                        </Link>
                        <h1 className="text-2xl font-bold">{title}</h1>
                        <p className="text-sm text-muted-foreground">
                            {t(`reports.items.${report.name}.desc`)}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {canExport ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-11 md:min-h-9"
                                disabled={exporting}
                                onClick={() => void exportCsv()}
                            >
                                <Download aria-hidden="true" />
                                {t('reports.export')}
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11 md:min-h-9"
                            onClick={() => window.print()}
                        >
                            <Printer aria-hidden="true" />
                            {t('reports.print')}
                        </Button>
                    </div>
                </div>

                <div className="flex flex-wrap items-end gap-3 print:hidden">
                    {report.usesPeriod ? (
                        <DateRangePicker
                            value={{ from: params.from, to: params.to }}
                            onChange={(range) => visit(range)}
                        />
                    ) : (
                        <span className="text-sm text-muted-foreground">
                            {t('reports.as_of')}
                        </span>
                    )}
                    {canAllBranches
                        ? select(
                              t('reports.branch.label'),
                              params.branch,
                              [
                                  {
                                      value: 'current',
                                      label: t('reports.branch.current'),
                                  },
                                  {
                                      value: 'all',
                                      label: t('reports.branch.all'),
                                  },
                              ],
                              (value) => visit({ branch: value }),
                          )
                        : null}
                    {report.filters.includes('category')
                        ? select(
                              t('reports.filter.category'),
                              params.filters['category'] ?? '',
                              [
                                  any,
                                  ...options.categories.map((c) => ({
                                      value: String(c.id),
                                      label: c.name,
                                  })),
                              ],
                              (value) => setFilter('category', value),
                          )
                        : null}
                    {report.filters.includes('brand')
                        ? select(
                              t('reports.filter.brand'),
                              params.filters['brand'] ?? '',
                              [
                                  any,
                                  ...options.brands.map((b) => ({
                                      value: String(b.id),
                                      label: b.name,
                                  })),
                              ],
                              (value) => setFilter('brand', value),
                          )
                        : null}
                    {report.filters.includes('bucket')
                        ? select(
                              t('reports.filter.bucket'),
                              params.filters['bucket'] ?? '',
                              [
                                  any,
                                  ...options.buckets.map((b) => ({
                                      value: b,
                                      label: t(`reports.bucket.${b}`),
                                  })),
                              ],
                              (value) => setFilter('bucket', value),
                          )
                        : null}
                    {report.filters.includes('limit')
                        ? select(
                              t('reports.filter.limit'),
                              params.filters['limit'] ?? '100',
                              LIMITS.map((l) => ({
                                  value: l,
                                  label: digits.n(l),
                              })),
                              (value) => setFilter('limit', value),
                          )
                        : null}
                </div>

                <div className="print-area flex flex-col gap-3">
                    <h2 className="hidden text-xl font-bold print:block">
                        {title}
                        {report.usesPeriod
                            ? ` (${digits.n(params.from)} - ${digits.n(params.to)})`
                            : ''}
                    </h2>
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
                    />
                    {totalColumns.length > 0 && result.rows.length > 0 ? (
                        <dl
                            aria-label={t('reports.total_row')}
                            className="flex flex-wrap gap-x-6 gap-y-2 rounded-lg border bg-card p-3 font-semibold"
                        >
                            <dt className="sr-only">
                                {t('reports.total_row')}
                            </dt>
                            {totalColumns.map((column) => (
                                <div key={column.key}>
                                    <dt className="text-xs font-normal text-muted-foreground">
                                        {t(`reports.col.${column.key}`, {
                                            defaultValue: column.label,
                                        })}
                                    </dt>
                                    <dd className="num">
                                        {cellText(
                                            column,
                                            result.totals?.[column.key] ?? 0,
                                            digits,
                                            t,
                                        )}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    ) : null}
                </div>
            </div>
        </>
    );
}

ReportShow.layout = {
    breadcrumbs: [{ title: 'Reports', href: '/app/reports' }],
};
