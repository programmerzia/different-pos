import type { Paisa } from '@/engine/money';

export type OptionValue = { id: number; value: string; code: string };

export type GridRow = {
    /** `option1-option2` ids: stable while the chips change, so typed prices survive. */
    key: string;
    option1_value_id: number;
    option2_value_id: number | null;
    label: string;
    sale_price: Paisa | null;
    cost_price: Paisa | null;
    sku: string;
    barcode: string;
};

export function rowKey(option1: number, option2: number | null): string {
    return `${option1}-${option2 ?? ''}`;
}

/** `{family code}-{value1 code}-{value2 code}`, the server's own default. */
export function defaultSku(
    familyCode: string,
    first: OptionValue,
    second: OptionValue | null,
): string {
    return [familyCode.trim().toUpperCase(), first.code, second?.code]
        .filter((part): part is string => Boolean(part))
        .join('-');
}

/**
 * The size x colour grid for the selected chips. Rows already in `previous` keep what was typed
 * (prices, SKU, barcode); new combinations start blank so the base price applies. Order follows the
 * chip order, option 1 outermost.
 */
export function buildVariantGrid(
    first: OptionValue[],
    second: OptionValue[],
    familyCode: string,
    previous: GridRow[] = [],
): GridRow[] {
    const kept = new Map(previous.map((row) => [row.key, row]));
    const rows: GridRow[] = [];
    const seconds: (OptionValue | null)[] = second.length > 0 ? second : [null];

    for (const a of first) {
        for (const b of seconds) {
            const key = rowKey(a.id, b?.id ?? null);
            const existing = kept.get(key);
            const sku = defaultSku(familyCode, a, b);
            rows.push({
                key,
                option1_value_id: a.id,
                option2_value_id: b?.id ?? null,
                label: b ? `${a.value} / ${b.value}` : a.value,
                sale_price: existing?.sale_price ?? null,
                cost_price: existing?.cost_price ?? null,
                // A SKU the user did not edit follows the family code as it is typed.
                sku:
                    existing && existing.sku !== '' && !existing.sku.endsWith(`-${a.code}${b ? `-${b.code}` : ''}`)
                        ? existing.sku
                        : sku,
                barcode: existing?.barcode ?? '',
            });
        }
    }

    return rows;
}

/** Toggles a value id in a chip selection, keeping the order the chips were picked in. */
export function toggleId(selected: number[], id: number): number[] {
    return selected.includes(id) ? selected.filter((x) => x !== id) : [...selected, id];
}
