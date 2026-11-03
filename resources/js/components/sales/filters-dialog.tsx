import { Filter } from 'lucide-react';
import { useState } from 'react';
import { NativeSelect } from '@/components/native-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogActions,
    DialogBar,
    DialogBody,
    DialogContent,
} from '@/components/ui/dialog';
import { useHotkeyScope } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';

export type FilterField = {
    key: string;
    label: string;
    /** `''` = all. */
    options: { value: string; label: string }[];
};

/**
 * Mockup "More Filters" panel of a list (DataList moreFilters): one select per field, Apply / Reset.
 * Values are the list's server filters; `''` removes a filter.
 */
export function FiltersDialog({
    open,
    onOpenChange,
    fields,
    values,
    onApply,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    fields: FilterField[];
    values: Record<string, string>;
    onApply: (next: Record<string, string>) => void;
}) {
    const { t } = useT();
    useHotkeyScope('dialog', open);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent layout="panel" size="md">
                {open ? (
                    <FiltersForm
                        fields={fields}
                        values={values}
                        onApply={(next) => {
                            onApply(next);
                            onOpenChange(false);
                        }}
                        title={t('table.more_filters')}
                        closeLabel={t('common.close')}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function FiltersForm({
    fields,
    values,
    onApply,
    title,
    closeLabel,
}: {
    fields: FilterField[];
    values: Record<string, string>;
    onApply: (next: Record<string, string>) => void;
    title: string;
    closeLabel: string;
}) {
    const { t } = useT();
    const [draft, setDraft] = useState<Record<string, string>>(() =>
        Object.fromEntries(fields.map((f) => [f.key, values[f.key] ?? ''])),
    );

    return (
        <form
            className="flex min-h-0 flex-col"
            onSubmit={(e) => {
                e.preventDefault();
                onApply(draft);
            }}
        >
            <DialogBar title={title} icon={Filter} closeLabel={closeLabel} />
            <DialogBody>
                {fields.map((field) => (
                    <div key={field.key} className="grid gap-1">
                        <label
                            htmlFor={`filter-${field.key}`}
                            className="text-[11px] font-semibold text-muted-foreground"
                        >
                            {field.label}
                        </label>
                        <NativeSelect
                            id={`filter-${field.key}`}
                            value={draft[field.key] ?? ''}
                            onChange={(e) =>
                                setDraft((d) => ({
                                    ...d,
                                    [field.key]: e.target.value,
                                }))
                            }
                        >
                            {field.options.map((o) => (
                                <option key={o.value} value={o.value}>
                                    {o.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                ))}
            </DialogBody>
            <DialogActions>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() =>
                        onApply(
                            Object.fromEntries(fields.map((f) => [f.key, ''])),
                        )
                    }
                >
                    {t('sales.filters_reset')}
                </Button>
                <Button type="submit">{t('sales.filters_apply')}</Button>
            </DialogActions>
        </form>
    );
}
