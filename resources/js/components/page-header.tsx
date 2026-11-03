import { ChevronDown, Download, FileSpreadsheet, FileText, Printer } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

/**
 * Page body (mockup DataList wrapper): 16 px padding, 12 px between blocks.
 * `fill` makes it exactly the content height so a `<DataTable fill>` scrolls inside its card.
 */
export function Page({
    children,
    fill = false,
    className,
}: {
    children: ReactNode;
    fill?: boolean;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3 p-page',
                fill && 'min-h-0 flex-1 md:overflow-hidden',
                className,
            )}
        >
            {children}
        </div>
    );
}

/**
 * The mockup page header: 18 px bold title + count pill + 12 px muted subtitle, right-aligned actions
 * (primary action first, then `Columns / Export / Print` outline buttons). Renders the page's `<h1>`.
 */
export function PageHeader({
    title,
    count,
    subtitle,
    actions,
    badge,
    className,
}: {
    title: ReactNode;
    /** Record count shown in the pill next to the title (formatted with the user's digits). */
    count?: number | null;
    subtitle?: ReactNode;
    /** Right-hand slot: buttons, `<ColumnChooser>`, `<ExportMenu>`, `<PrintButton>`. */
    actions?: ReactNode;
    /** Any extra chip after the title (e.g. a status). */
    badge?: ReactNode;
    className?: string;
}) {
    const digits = useDigits();

    return (
        <div
            className={cn(
                'flex shrink-0 flex-col justify-between gap-2 sm:flex-row sm:items-center',
                className,
            )}
        >
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-lg leading-7 font-bold text-foreground">
                        {title}
                    </h1>
                    {count !== undefined && count !== null ? (
                        <span className="num rounded-full border bg-surface-sunken px-2 py-0.5 font-mono text-[11px] font-bold text-muted-foreground">
                            {digits.n(count)}
                        </span>
                    ) : null}
                    {badge}
                </div>
                {subtitle ? (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {subtitle}
                    </p>
                ) : null}
            </div>
            {actions ? (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            ) : null}
        </div>
    );
}

export type ExportItem = {
    id: string;
    label: string;
    /** `excel` / `csv` / `print` pick the mockup's coloured icon. */
    kind?: 'excel' | 'csv' | 'print';
    onSelect: () => void;
};

/** Mockup "Export ▾" menu. Pass only exports the page can really produce. */
export function ExportMenu({ items }: { items: ExportItem[] }) {
    const { t } = useT();
    if (items.length === 0) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="outline">
                    <Download aria-hidden="true" />
                    <span className="max-sm:sr-only">{t('table.export')}</span>
                    <ChevronDown className="size-3" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44 rounded-xl p-1 text-xs">
                {items.map((item, i) => {
                    const Icon =
                        item.kind === 'print'
                            ? Printer
                            : item.kind === 'excel'
                              ? FileSpreadsheet
                              : FileText;

                    return (
                        <div key={item.id}>
                            {item.kind === 'print' && i > 0 ? (
                                <DropdownMenuSeparator />
                            ) : null}
                            <DropdownMenuItem
                                className="rounded-lg px-2.5 py-1.5 text-xs"
                                onSelect={item.onSelect}
                            >
                                <Icon
                                    aria-hidden="true"
                                    className={cn(
                                        'size-3.5',
                                        item.kind === 'excel' && 'text-success',
                                        item.kind === 'csv' && 'text-info',
                                        item.kind === 'print' && 'text-primary',
                                    )}
                                />
                                {item.label}
                            </DropdownMenuItem>
                        </div>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** Mockup "Print" header button (defaults to the browser print of the page's `.print-area`). */
export function PrintButton({
    onPrint = () => window.print(),
    label,
}: {
    onPrint?: () => void;
    label?: string;
}) {
    const { t } = useT();

    return (
        <Button type="button" variant="outline" onClick={onPrint}>
            <Printer aria-hidden="true" />
            <span className="max-sm:sr-only">{label ?? t('table.print')}</span>
        </Button>
    );
}
