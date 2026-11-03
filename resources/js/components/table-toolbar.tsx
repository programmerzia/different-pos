import { Filter, Search, X } from 'lucide-react';
import type { ReactNode, Ref } from 'react';
import { NativeSelect } from '@/components/native-select';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

export type ToolbarFilter = {
    key: string;
    labelKey: string;
    value: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
};

/** A single-choice chip row (mockup "All Statuses · Paid · Partial …"); `''` is the "all" value. */
export type ToolbarChips = {
    key: string;
    /** Accessible name of the group. */
    label: string;
    value: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
    /** Keep the chips on one line: the row scrolls sideways and long labels are truncated. */
    scroll?: boolean;
};

/** A labelled select ("View:", "Date:"). */
export type ToolbarSelect = {
    value: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
};

const control =
    'h-8 w-auto rounded-lg border bg-surface-sunken px-2 text-xs font-medium text-foreground';

/**
 * The mockup list toolbar: one bordered card with search (`/` keycap), `View:` and `Date:` selects,
 * chip groups and "More filters". The search input has id `page-search` (the `/` hotkey target).
 * Every slot except search is optional; `filters` keeps the older "select per filter" API.
 */
export function TableToolbar({
    search,
    onSearch,
    placeholderKey = 'common.search',
    filters = [],
    view,
    date,
    dateControl,
    chips = [],
    moreFilters,
    inputRef,
    className,
    children,
}: {
    search: string;
    onSearch: (text: string) => void;
    placeholderKey?: string;
    filters?: ToolbarFilter[];
    /** "View:" saved-view select. */
    view?: ToolbarSelect;
    /** "Date:" preset select. */
    date?: ToolbarSelect;
    /** Any date control (e.g. `<DateRangePicker>`) shown after the "Date:" label. */
    dateControl?: ReactNode;
    chips?: ToolbarChips[];
    /** "More filters" button: opens the page's filter drawer/dialog; `count` = active filters. */
    moreFilters?: { count: number; onOpen: () => void };
    inputRef?: Ref<HTMLInputElement>;
    className?: string;
    /** Right-aligned extras (kept from the old API). */
    children?: ReactNode;
}) {
    const { t } = useT();
    const digits = useDigits();
    const placeholder = t(placeholderKey);

    return (
        <search
            className={cn(
                'flex shrink-0 flex-wrap items-center justify-between gap-2 rounded-xl border bg-surface p-2.5',
                className,
            )}
        >
            <div className="flex min-w-64 flex-1 flex-wrap items-center gap-2">
                <div className="relative min-w-50 flex-1 md:max-w-sm">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-2.5 size-3.25 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <input
                        ref={inputRef}
                        id="page-search"
                        type="search"
                        value={search}
                        placeholder={placeholder}
                        aria-label={placeholder}
                        onChange={(e) => onSearch(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Escape') {
                                e.currentTarget.blur();
                            }
                        }}
                        className="h-8 w-full rounded-lg border bg-surface-sunken pr-7 pl-8 text-base text-foreground outline-none placeholder:text-muted-foreground focus-visible:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-xs [&::-webkit-search-cancel-button]:appearance-none [@media(pointer:coarse)]:min-h-11"
                    />
                    {search ? (
                        <button
                            type="button"
                            aria-label={t('table.clear_search')}
                            onClick={() => onSearch('')}
                            className="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        >
                            <X className="size-3.5" aria-hidden="true" />
                        </button>
                    ) : (
                        <kbd
                            aria-hidden="true"
                            className="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2 rounded-sm border bg-surface px-1 font-mono text-[9px] text-muted-foreground [@media(pointer:coarse)]:hidden"
                        >
                            /
                        </kbd>
                    )}
                </div>

                {view ? (
                    <LabelledSelect label={t('table.view')} select={view} />
                ) : null}
                {date || dateControl ? (
                    <div className="flex items-center gap-1">
                        <span className="text-[11px] font-medium text-muted-foreground">
                            {t('table.date')}
                        </span>
                        {date ? (
                            <NativeSelect
                                aria-label={t('table.date')}
                                className={control}
                                value={date.value}
                                onChange={(e) => date.onChange(e.target.value)}
                            >
                                {date.options.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {o.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        ) : null}
                        {dateControl}
                    </div>
                ) : null}

                {filters.map((filter) => (
                    <NativeSelect
                        key={filter.key}
                        className={cn(control, 'min-w-32')}
                        aria-label={t(filter.labelKey)}
                        value={filter.value}
                        onChange={(e) => filter.onChange(e.target.value)}
                    >
                        <option value="">{t(filter.labelKey)}</option>
                        {filter.options.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </NativeSelect>
                ))}

                {chips.map((group) => (
                    <ChipRow key={group.key} group={group} />
                ))}
            </div>

            {moreFilters || children ? (
                <div className="flex flex-wrap items-center gap-2">
                    {children}
                    {moreFilters ? (
                        <button
                            type="button"
                            onClick={moreFilters.onOpen}
                            className={cn(
                                'flex h-8 items-center gap-1.5 rounded-lg border px-3 text-xs font-medium transition-colors [@media(pointer:coarse)]:min-h-11',
                                moreFilters.count > 0
                                    ? 'border-primary bg-primary-soft font-bold text-primary'
                                    : 'bg-surface-sunken text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Filter className="size-3.25" aria-hidden="true" />
                            <span>{t('table.more_filters')}</span>
                            {moreFilters.count > 0 ? (
                                <span className="flex size-4 items-center justify-center rounded-full bg-primary font-mono text-[10px] font-bold text-primary-foreground">
                                    {digits.n(moreFilters.count)}
                                </span>
                            ) : null}
                        </button>
                    ) : null}
                </div>
            ) : null}
        </search>
    );
}

function LabelledSelect({
    label,
    select,
}: {
    label: string;
    select: ToolbarSelect;
}) {
    return (
        <div className="flex items-center gap-1">
            <span className="text-[11px] font-medium text-muted-foreground">
                {label}
            </span>
            <NativeSelect
                aria-label={label}
                className={control}
                value={select.value}
                onChange={(e) => select.onChange(e.target.value)}
            >
                {select.options.map((o) => (
                    <option key={o.value} value={o.value}>
                        {o.label}
                    </option>
                ))}
            </NativeSelect>
        </div>
    );
}

/** Mockup filter chips: h-7, rounded-lg, 12 px; the active chip is solid primary and bold. */
export function ChipRow({ group }: { group: ToolbarChips }) {
    return (
        <fieldset
            className={cn(
                'm-0 flex min-w-0 items-center gap-1 border-0 p-0',
                group.scroll
                    ? 'flex-1 basis-48 [scrollbar-width:none] flex-nowrap overflow-x-auto'
                    : 'flex-wrap',
            )}
        >
            <legend className="sr-only">{group.label}</legend>
            {group.options.map((opt) => {
                const on = group.value === opt.value;

                return (
                    <button
                        key={opt.value}
                        type="button"
                        aria-pressed={on}
                        onClick={() => group.onChange(opt.value)}
                        title={group.scroll ? opt.label : undefined}
                        className={cn(
                            'h-7 rounded-lg px-2.5 text-xs font-medium transition-colors [@media(pointer:coarse)]:min-h-11',
                            group.scroll &&
                                'max-w-32 shrink-0 truncate whitespace-nowrap',
                            on
                                ? 'bg-primary font-bold text-primary-foreground shadow-xs'
                                : 'bg-surface-sunken text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {opt.label}
                    </button>
                );
            })}
        </fieldset>
    );
}
