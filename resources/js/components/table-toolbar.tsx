import { Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/native-select';
import { useT } from '@/i18n';

export type ToolbarFilter = {
    key: string;
    labelKey: string;
    value: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
};

/** Search + filter selects above a DataTable; the search input has id `page-search` (the `/` hotkey target). */
export function TableToolbar({
    search,
    onSearch,
    placeholderKey = 'common.search',
    filters = [],
    children,
}: {
    search: string;
    onSearch: (text: string) => void;
    placeholderKey?: string;
    filters?: ToolbarFilter[];
    children?: ReactNode;
}) {
    const { t } = useT();

    return (
        <div className="flex flex-wrap items-center gap-2">
            <div className="relative min-w-56 flex-1 md:max-w-sm">
                <Search
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    id="page-search"
                    type="search"
                    className="min-h-11 pl-9 md:min-h-9"
                    value={search}
                    placeholder={t(placeholderKey)}
                    aria-label={t(placeholderKey)}
                    onChange={(e) => onSearch(e.target.value)}
                />
            </div>
            {filters.map((filter) => (
                <NativeSelect
                    key={filter.key}
                    className="w-auto min-w-36"
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
            {children ? <div className="ml-auto flex items-center gap-2">{children}</div> : null}
        </div>
    );
}
