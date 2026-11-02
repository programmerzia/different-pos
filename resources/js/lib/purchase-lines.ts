import { addMoney, subtractMoney, timesQty, type Paisa } from '@/engine/money';
import type { QtyMilli } from '@/engine/quantity';

export type Line = {
    product_id: number;
    name: string;
    sku: string;
    qty: QtyMilli;
    unit_cost: Paisa;
    discount: Paisa;
};

export const lineNet = (l: Line): Paisa => subtractMoney(timesQty(l.unit_cost, l.qty), l.discount);

export function purchaseTotals(lines: Line[], discount: Paisa, shipping: Paisa) {
    const subtotal = addMoney(0, ...lines.map(lineNet));

    return { subtotal, total: subtractMoney(addMoney(subtotal, shipping), discount) };
}

/** Adds a scanned product: the same product again bumps its quantity by one unit. */
export function addLine(lines: Line[], product: Omit<Line, 'qty' | 'discount'>): Line[] {
    const at = lines.findIndex((l) => l.product_id === product.product_id);
    if (at >= 0) {
        return lines.map((l, i) => (i === at ? { ...l, qty: l.qty + 1000 } : l));
    }

    return [...lines, { ...product, qty: 1000, discount: 0 }];
}
