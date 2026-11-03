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

/** Size / colour chips that generate the variant grid (prices default to the base price). */
export function VariantBuilder({
    options,
    onOptionsChange,
    draft,
    onChange,
    familyCode,
    canViewCost,
}: {
    options: VariantOptionDef[];
    onOptionsChange: (options: VariantOptionDef[]) => void;
    draft: VariantDraft;
    onChange: (draft: VariantDraft) => void;
    familyCode: string;
    canViewCost: boolean;
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

    return (
        <section className="grid gap-4" aria-labelledby="variants-title">
            <div>
                <h2 id="variants-title" className="text-xl font-semibold">
                    {t('products.variants')}
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    {t('products.variants_hint')}
                </p>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
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
                                              values1: toggleId(
                                                  draft.values1,
                                                  id,
                                              ),
                                          }
                                        : {
                                              values2: toggleId(
                                                  draft.values2,
                                                  id,
                                              ),
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

            {rows.length > 0 ? (
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
                                <th
                                    scope="col"
                                    className="px-3 py-2 text-right"
                                >
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
            ) : null}
        </section>
    );
}

function OptionSlot({
    slot,
    options,
    option,
    selectedId,
    ids,
    onSelectOption,
    onToggle,
    onOptionUpdated,
}: {
    slot: 1 | 2;
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
