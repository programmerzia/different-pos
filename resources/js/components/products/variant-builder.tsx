import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ChipGroup } from '@/components/chip-group';
import { MoneyInput } from '@/components/money-input';
import { NativeSelect } from '@/components/native-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useT } from '@/i18n';
import { apiRequest } from '@/lib/api';
import {
    buildVariantGrid,
    toggleId,
    type GridRow,
    type OptionValue,
} from '@/lib/variant-grid';

export type VariantOptionDef = {
    id: number;
    name: string;
    values: OptionValue[];
};

export type VariantDraft = {
    option1_id: number | null;
    option2_id: number | null;
    values1: number[];
    values2: number[];
    rows: GridRow[];
};

export const EMPTY_VARIANTS: VariantDraft = {
    option1_id: null,
    option2_id: null,
    values1: [],
    values2: [],
    rows: [],
};

/** The grid for the selected chips, chip order kept, typed row values carried over. */
export function gridFor(
    options: VariantOptionDef[],
    draft: VariantDraft,
    familyCode: string,
): GridRow[] {
    const pick = (optionId: number | null, ids: number[]) => {
        const values = options.find((o) => o.id === optionId)?.values ?? [];

        return ids
            .map((id) => values.find((v) => v.id === id))
            .filter((v): v is OptionValue => v !== undefined);
    };

    return buildVariantGrid(
        pick(draft.option1_id, draft.values1),
        pick(draft.option2_id, draft.values2),
        familyCode,
        draft.rows,
    );
}

/**
 * The draft a new product starts from: the first option slot on a size-like option and the second on a
 * colour-like one (else the first two options), so the chips can show straight away (mockup
 * "Applicable Sizes / Applicable Colors").
 */
export function defaultVariantDraft(options: VariantOptionDef[]): VariantDraft {
    const size =
        options.find((o) => /size|সাইজ|মাপ/iu.test(o.name)) ?? options[0];
    const colour =
        options.find(
            (o) => o.id !== size?.id && /colou?r|রঙ|রং/iu.test(o.name),
        ) ?? options.find((o) => o.id !== size?.id);

    return {
        ...EMPTY_VARIANTS,
        option1_id: size?.id ?? null,
        option2_id: colour?.id ?? null,
    };
}

/**
 * Size / colour chips that generate the variant grid (prices default to the base price).
 * `layout`: `full` (option pickers, chips and grid), `chips` (compact pickers and chips only, for the
 * pricing panel) or `grid` (the generated rows only).
 */
export function VariantBuilder({
    options,
    onOptionsChange,
    draft,
    onChange,
    familyCode,
    canViewCost,
    layout = 'full',
}: {
    options: VariantOptionDef[];
    onOptionsChange: (options: VariantOptionDef[]) => void;
    draft: VariantDraft;
    onChange: (draft: VariantDraft) => void;
    familyCode: string;
    canViewCost: boolean;
    layout?: 'full' | 'chips' | 'grid';
}) {
    const { t } = useT();
    const first = options.find((o) => o.id === draft.option1_id) ?? null;
    const second = options.find((o) => o.id === draft.option2_id) ?? null;

    const apply = (patch: Partial<VariantDraft>) => {
        const next = { ...draft, ...patch };
        // Rows keep what was typed; the grid itself is derived (see gridFor).
        onChange({ ...next, rows: gridFor(options, next, familyCode) });
    };

    const rows = useMemo(
        () => gridFor(options, draft, familyCode),
        [options, draft, familyCode],
    );

    const setRow = (key: string, patch: Partial<GridRow>) =>
        onChange({
            ...draft,
            rows: rows.map((r) => (r.key === key ? { ...r, ...patch } : r)),
        });

    const slots = (
        <div
            className={
                layout === 'chips' ? 'grid gap-3' : 'grid gap-6 lg:grid-cols-2'
            }
        >
            {([1, 2] as const).map((slot) => {
                const selectedId =
                    slot === 1 ? draft.option1_id : draft.option2_id;
                const option = slot === 1 ? first : second;
                const ids = slot === 1 ? draft.values1 : draft.values2;
                const otherId =
                    slot === 1 ? draft.option2_id : draft.option1_id;

                return (
                    <OptionSlot
                        key={slot}
                        slot={slot}
                        compact={layout === 'chips'}
                        options={options.filter((o) => o.id !== otherId)}
                        option={option}
                        selectedId={selectedId}
                        ids={ids}
                        onSelectOption={(id) =>
                            apply(
                                slot === 1
                                    ? { option1_id: id, values1: [] }
                                    : { option2_id: id, values2: [] },
                            )
                        }
                        onToggle={(id) =>
                            apply(
                                slot === 1
                                    ? {
                                          values1: toggleId(draft.values1, id),
                                      }
                                    : {
                                          values2: toggleId(draft.values2, id),
                                      },
                            )
                        }
                        onOptionUpdated={(updated) => {
                            onOptionsChange(
                                options.some((o) => o.id === updated.id)
                                    ? options.map((o) =>
                                          o.id === updated.id ? updated : o,
                                      )
                                    : [...options, updated],
                            );
                        }}
                    />
                );
            })}
        </div>
    );

    if (layout === 'chips') {
        return slots;
    }

    const grid =
        rows.length > 0 ? (
            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <caption className="sr-only">
                        {t('products.variant_grid')}
                    </caption>
                    <thead className="bg-muted text-left">
                        <tr>
                            <th scope="col" className="px-3 py-2">
                                {t('products.variant')}
                            </th>
                            <th scope="col" className="px-3 py-2">
                                {t('products.col_sku')}
                            </th>
                            <th scope="col" className="px-3 py-2 text-right">
                                {t('products.col_price')}
                            </th>
                            {canViewCost ? (
                                <th
                                    scope="col"
                                    className="px-3 py-2 text-right"
                                >
                                    {t('products.col_cost')}
                                </th>
                            ) : null}
                            <th scope="col" className="px-3 py-2">
                                {t('products.col_barcode')}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.key} className="border-t">
                                <th
                                    scope="row"
                                    className="px-3 py-2 text-left font-medium"
                                >
                                    {row.label}
                                </th>
                                <td className="px-3 py-2">
                                    <Input
                                        className="num min-h-11 md:min-h-9"
                                        aria-label={`${t('products.col_sku')} ${row.label}`}
                                        value={row.sku}
                                        onChange={(e) =>
                                            setRow(row.key, {
                                                sku: e.target.value,
                                            })
                                        }
                                    />
                                </td>
                                <td className="px-3 py-2">
                                    <MoneyInput
                                        value={row.sale_price}
                                        onChange={(v) =>
                                            setRow(row.key, {
                                                sale_price: v,
                                            })
                                        }
                                        aria-describedby="variants-price-hint"
                                    />
                                </td>
                                {canViewCost ? (
                                    <td className="px-3 py-2">
                                        <MoneyInput
                                            value={row.cost_price}
                                            onChange={(v) =>
                                                setRow(row.key, {
                                                    cost_price: v,
                                                })
                                            }
                                        />
                                    </td>
                                ) : null}
                                <td className="px-3 py-2">
                                    <Input
                                        className="num min-h-11 md:min-h-9"
                                        aria-label={`${t('products.col_barcode')} ${row.label}`}
                                        value={row.barcode}
                                        placeholder={t(
                                            'products.barcode_auto_short',
                                        )}
                                        onChange={(e) =>
                                            setRow(row.key, {
                                                barcode: e.target.value,
                                            })
                                        }
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <p
                    id="variants-price-hint"
                    className="border-t px-3 py-2 text-xs text-muted-foreground"
                >
                    {t('products.variants_price_hint', { n: rows.length })}
                </p>
            </div>
        ) : null;

    if (layout === 'grid') {
        return grid;
    }

    return (
        <section className="grid gap-4" aria-labelledby="variants-title">
            <div>
                <h3
                    id="variants-title"
                    className="text-xs font-bold text-foreground"
                >
                    {t('products.variants')}
                </h3>
                <p className="mt-0.5 text-[11px] text-muted-foreground">
                    {t('products.variants_hint')}
                </p>
            </div>
            {slots}
            {grid}
        </section>
    );
}

function OptionSlot({
    slot,
    compact = false,
    options,
    option,
    selectedId,
    ids,
    onSelectOption,
    onToggle,
    onOptionUpdated,
}: {
    slot: 1 | 2;
    compact?: boolean;
    options: VariantOptionDef[];
    option: VariantOptionDef | null;
    selectedId: number | null;
    ids: number[];
    onSelectOption: (id: number | null) => void;
    onToggle: (id: number) => void;
    onOptionUpdated: (option: VariantOptionDef) => void;
}) {
    const { t } = useT();
    const [newValue, setNewValue] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    /** A tenant without options yet: create "Size" (slot 1) or "Colour" (slot 2) in place and select it. */
    const createOption = async () => {
        setBusy(true);
        setError(null);
        try {
            const created = await apiRequest<VariantOptionDef>(
                '/app/variant-options',
                {
                    method: 'POST',
                    body: {
                        name: t(
                            slot === 1
                                ? 'products.option_size'
                                : 'products.option_colour',
                        ),
                    },
                },
            );
            onOptionUpdated(created);
            onSelectOption(created.id);
        } catch {
            setError(t('products.value_failed'));
        } finally {
            setBusy(false);
        }
    };

    const addValue = async () => {
        if (!option || newValue.trim() === '') {
            return;
        }
        setBusy(true);
        setError(null);
        try {
            const updated = await apiRequest<VariantOptionDef>(
                `/app/variant-options/${option.id}/values`,
                {
                    method: 'POST',
                    body: { value: newValue.trim() },
                },
            );
            onOptionUpdated(updated);
            const added = updated.values.find(
                (v) => v.value.toLowerCase() === newValue.trim().toLowerCase(),
            );
            if (added && !ids.includes(added.id)) {
                onToggle(added.id);
            }
            setNewValue('');
        } catch {
            setError(t('products.value_failed'));
        } finally {
            setBusy(false);
        }
    };

    const optionSelect = (
        <NativeSelect
            aria-label={t(
                slot === 1 ? 'products.option_first' : 'products.option_second',
            )}
            className="h-7 w-auto rounded-md border bg-surface-sunken px-2 text-[11px] font-medium [@media(pointer:coarse)]:min-h-11"
            value={selectedId ?? ''}
            onChange={(e) =>
                onSelectOption(
                    e.target.value === '' ? null : Number(e.target.value),
                )
            }
        >
            <option value="">{t('products.option_none')}</option>
            {options.map((o) => (
                <option key={o.id} value={o.id}>
                    {o.name}
                </option>
            ))}
        </NativeSelect>
    );

    if (compact) {
        // Mockup "Applicable Sizes" / "Applicable Colors": a label row, then the chips; new values inline.
        return (
            <div className="grid content-start gap-1.5">
                <div className="flex items-center justify-between gap-2">
                    <span className="text-[11px] font-semibold text-muted-foreground">
                        {option
                            ? t('products.applicable', { name: option.name })
                            : t(
                                  slot === 1
                                      ? 'products.option_first'
                                      : 'products.option_second',
                              )}
                    </span>
                    {options.length > 0 ? (
                        optionSelect
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-7 [@media(pointer:coarse)]:min-h-11"
                            disabled={busy}
                            onClick={() => void createOption()}
                        >
                            <Plus aria-hidden="true" />
                            {t(
                                slot === 1
                                    ? 'products.option_size'
                                    : 'products.option_colour',
                            )}
                        </Button>
                    )}
                </div>
                {!option && error ? (
                    <p role="alert" className="text-xs text-destructive">
                        {error}
                    </p>
                ) : null}
                {option ? (
                    <>
                        <ChipGroup
                            label={option.name}
                            chips={option.values.map((v) => ({
                                id: v.id,
                                label: v.value,
                            }))}
                            selected={ids}
                            onToggle={onToggle}
                            size="sm"
                        />
                        <div className="flex gap-1.5">
                            <Input
                                className="h-7 min-h-0 text-xs [@media(pointer:coarse)]:min-h-11"
                                value={newValue}
                                placeholder={t('products.add_value', {
                                    name: option.name,
                                })}
                                aria-label={t('products.add_value', {
                                    name: option.name,
                                })}
                                onChange={(e) => setNewValue(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        void addValue();
                                    }
                                }}
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-7 [@media(pointer:coarse)]:min-h-11"
                                disabled={busy || newValue.trim() === ''}
                                onClick={() => void addValue()}
                            >
                                <Plus aria-hidden="true" /> {t('common.add')}
                            </Button>
                        </div>
                        {error ? (
                            <p
                                role="alert"
                                className="text-xs text-destructive"
                            >
                                {error}
                            </p>
                        ) : null}
                    </>
                ) : null}
            </div>
        );
    }

    return (
        <div className="grid content-start gap-3">
            <label className="grid gap-1.5 text-sm font-medium">
                {t(
                    slot === 1
                        ? 'products.option_first'
                        : 'products.option_second',
                )}
                <NativeSelect
                    value={selectedId ?? ''}
                    onChange={(e) =>
                        onSelectOption(
                            e.target.value === ''
                                ? null
                                : Number(e.target.value),
                        )
                    }
                >
                    <option value="">{t('products.option_none')}</option>
                    {options.map((o) => (
                        <option key={o.id} value={o.id}>
                            {o.name}
                        </option>
                    ))}
                </NativeSelect>
            </label>
            {option ? (
                <>
                    <ChipGroup
                        label={option.name}
                        chips={option.values.map((v) => ({
                            id: v.id,
                            label: v.value,
                        }))}
                        selected={ids}
                        onToggle={onToggle}
                    />
                    <div className="flex gap-2">
                        <Input
                            className="min-h-11 md:min-h-9"
                            value={newValue}
                            placeholder={t('products.add_value', {
                                name: option.name,
                            })}
                            aria-label={t('products.add_value', {
                                name: option.name,
                            })}
                            onChange={(e) => setNewValue(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    void addValue();
                                }
                            }}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11 md:min-h-9"
                            disabled={busy}
                            onClick={() => void addValue()}
                        >
                            <Plus aria-hidden="true" /> {t('common.add')}
                        </Button>
                    </div>
                    {error ? (
                        <p role="alert" className="text-sm text-destructive">
                            {error}
                        </p>
                    ) : null}
                </>
            ) : null}
        </div>
    );
}
