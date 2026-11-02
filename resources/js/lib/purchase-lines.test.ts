import { describe, expect, it } from 'vite-plus/test';
import { addLine, purchaseTotals, type Line } from './purchase-lines';

const shirt = { product_id: 1, name: 'Shirt', sku: 'SH', unit_cost: 20000 };

describe('purchase lines', () => {
    it('adds a product once and bumps its quantity on a second scan', () => {
        const once = addLine([], shirt);
        expect(once).toEqual([{ ...shirt, qty: 1000, discount: 0 }]);
        expect(addLine(once, shirt)[0]?.qty).toBe(2000);
        expect(addLine(once, { ...shirt, product_id: 2 })).toHaveLength(2);
    });

    it('totals lines, line discount, order discount and shipping in paisa', () => {
        const lines: Line[] = [
            { ...shirt, qty: 10000, discount: 5000 },
            { product_id: 2, name: 'Jeans', sku: 'J', unit_cost: 60000, qty: 2500, discount: 0 },
        ];
        // 10 x 200.00 - 50.00 = 1950.00 ; 2.5 x 600.00 = 1500.00
        expect(purchaseTotals(lines, 0, 0).subtotal).toBe(345000);
        expect(purchaseTotals(lines, 10000, 2500)).toEqual({ subtotal: 345000, total: 337500 });
    });
});
