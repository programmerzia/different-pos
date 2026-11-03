import { Filter } from 'lucide-react';
import { useState } from 'react';
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
    /** First option is "all" (value `''`). */
    options: { value: string; label: string }[];
};

/**
 * The mockup "More Filters" panel for the catalog and stock lists: one select per extra filter,
 * Apply sends them all at once (one reload), Reset clears them.
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
            {open ? (
                <Body
                    fields={fields}
                    values={values}
                    onApply={(next) => {
                        onApply(next);
                        onOpenChange(false);
                    }}
                    onCancel={() => onOpenChange(false)}
                    title={t('table.more_filters')}
                />
            ) : null}
        </Dialog>
    );
}

function Body({
    fields,
    values,
    onApply,
    onCancel,
    title,
}: {
    fields: FilterField[];
    values: Record<string, string>;
    onApply: (next: Record<string, string>) => void;
    onCancel: () => void;
    title: string;
}) {
    const { t } = useT();
    const [draft, setDraft] = useState<Record<string, string>>(() =>
        Object.fromEntries(fields.map((f) => [f.key, values[f.key] ?? ''])),
    );

    return (
        <DialogContent layout="panel" size="sm">
            <DialogBar
                title={title}
                icon={Filter}
                closeLabel={t('common.close')}
            />
            <form
                className="contents"
                onSubmit={(e) => {
                    e.preventDefault();
                    onApply(draft);
                }}
            >
                <DialogBody>
                    {fields.map((field) => (
                        <div key={field.key}>
                            <label
                                htmlFor={`filter-${field.key}`}
                                className="text-[11px] font-semibold text-muted-foreground"
                            >
                                {field.label}
                            </label>
                            <select
                                id={`filter-${field.key}`}
                                className="mt-1 h-8 w-full rounded-lg border bg-surface-sunken px-2 text-xs font-semibold text-foreground [@media(pointer:coarse)]:min-h-11"
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
                            </select>
                        </div>
                    ))}
                </DialogBody>
                <DialogActions>
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() =>
                            onApply(
                                Object.fromEntries(
                                    fields.map((f) => [f.key, '']),
                                ),
                            )
                        }
                    >
                        {t('products.filters_reset')}
                    </Button>
                    <Button type="button" variant="outline" onClick={onCancel}>
                        {t('common.cancel')}
                    </Button>
                    <Button type="submit">{t('products.filters_apply')}</Button>
                </DialogActions>
            </form>
        </DialogContent>
    );
}
