/**
 * Pure cart pricing on integer paisa and milli-quantities. Twin of app/Pos/Pricing/PriceCalculator.php:
 * both run tests/fixtures/pricing/cases.json. Keep the algorithm identical.
 *
 * Order (each step rounds half-up): gross = price x qty; line discount; group discount (per line, on the
 * amount left); order discount (prorated by largest remainder over the remaining line amounts); VAT
 * (exclusive adds, inclusive backs out price x rate / (10000 + rate)); cash rounding of the grand total.
 * In inclusive mode `gross` includes VAT and `net` excludes it. Discount caps are authorisation, not here.
 */
import {
    allocateMoney,
    mulDivHalfUp,
    percentOfBp,
    timesQty,
    type Paisa,
} from './money';

export type DiscountType = 'none' | 'percent_bp' | 'amount';

export interface Discount {
    type: DiscountType;
    value: number;
}

export interface CartLine {
    unit_price: Paisa;
    qty_milli: number;
    line_discount: Discount;
    tax_rate_bp: number;
    tax_inclusive: boolean;
}

export interface Tender {
    type: string;
    amount: Paisa;
}

export interface CartInput {
    lines: CartLine[];
    order_discount: Discount;
    group_discount_bp: number;
    rounding_step: Paisa;
    tenders: Tender[];
}

export interface LineTotals {
    gross: Paisa;
    line_discount: Paisa;
    group_discount: Paisa;
    order_discount: Paisa;
    discount: Paisa;
    net: Paisa;
    tax: Paisa;
    total: Paisa;
    line_discount_bp: number;
    effective_discount_bp: number;
}

export interface OrderTotals {
    subtotal: Paisa;
    line_discount_total: Paisa;
    group_discount: Paisa;
    order_discount: Paisa;
    discount_total: Paisa;
    taxable: Paisa;
    tax: Paisa;
    rounding: Paisa;
    grand_total: Paisa;
    effective_discount_bp: number;
    tendered: Paisa;
    balance: Paisa;
    change: Paisa;
    due: Paisa;
}

export interface CartTotals {
    lines: LineTotals[];
    order: OrderTotals;
}

export class PricingError extends Error {
    override name = 'PricingError';
}

function discountOf(discount: Discount, base: Paisa): Paisa {
    if (discount.value < 0) {
        throw new PricingError('A discount value must not be negative.');
    }
    switch (discount.type) {
        case 'none':
            return 0;
        case 'percent_bp':
            return percentOfBp(base, Math.min(discount.value, 10_000));
        case 'amount':
            return Math.min(discount.value, base);
        default:
            throw new PricingError(
                `Unknown discount type "${String(discount.type)}".`,
            );
    }
}

/** Effective discount in basis points of the gross amount, half-up (0 when the gross is 0). */
function bp(discount: Paisa, gross: Paisa): number {
    return gross === 0 ? 0 : mulDivHalfUp(discount, 10_000, gross);
}

const sum = (values: number[]): number => values.reduce((s, v) => s + v, 0);

export function calculateCart(input: CartInput): CartTotals {
    const groupBp = input.group_discount_bp;
    const step = input.rounding_step;
    if (groupBp < 0 || groupBp > 10_000 || step < 0) {
        throw new PricingError(
            'Group discount must be 0-10000 bp and the rounding step must not be negative.',
        );
    }

    const gross: Paisa[] = [];
    const lineDiscount: Paisa[] = [];
    const groupDiscount: Paisa[] = [];
    const afterGroup: Paisa[] = [];

    input.lines.forEach((line, i) => {
        if (line.unit_price < 0 || line.qty_milli < 0 || line.tax_rate_bp < 0) {
            throw new PricingError(
                `Line ${i} has a negative price, quantity or tax rate.`,
            );
        }
        const g = timesQty(line.unit_price, line.qty_milli);
        const ld = discountOf(line.line_discount, g);
        const gd = percentOfBp(g - ld, groupBp);
        gross.push(g);
        lineDiscount.push(ld);
        groupDiscount.push(gd);
        afterGroup.push(g - ld - gd);
    });

    const orderDiscountTotal = discountOf(
        input.order_discount,
        sum(afterGroup),
    );
    const orderShare: Paisa[] =
        orderDiscountTotal > 0
            ? allocateMoney(orderDiscountTotal, afterGroup)
            : afterGroup.map(() => 0);

    const lines: LineTotals[] = [];
    let subtotal = 0;
    let lineDiscountSum = 0;
    let groupSum = 0;
    let taxable = 0;
    let tax = 0;
    let linesTotal = 0;

    input.lines.forEach((line, i) => {
        const priced = (afterGroup[i] ?? 0) - (orderShare[i] ?? 0);
        const rate = line.tax_rate_bp;
        let net = priced;
        let lineTax: Paisa;
        if (line.tax_inclusive) {
            lineTax = mulDivHalfUp(priced, rate, 10_000 + rate);
            net = priced - lineTax;
        } else {
            lineTax = percentOfBp(priced, rate);
        }
        const g = gross[i] ?? 0;
        const ld = lineDiscount[i] ?? 0;
        const gd = groupDiscount[i] ?? 0;
        const od = orderShare[i] ?? 0;
        const discount = ld + gd + od;

        lines.push({
            gross: g,
            line_discount: ld,
            group_discount: gd,
            order_discount: od,
            discount,
            net,
            tax: lineTax,
            total: net + lineTax,
            line_discount_bp: bp(ld, g),
            effective_discount_bp: bp(discount, g),
        });

        subtotal += g;
        lineDiscountSum += ld;
        groupSum += gd;
        taxable += net;
        tax += lineTax;
        linesTotal += net + lineTax;
    });

    const rounding =
        step > 0 ? mulDivHalfUp(linesTotal, 1, step) * step - linesTotal : 0;
    const grandTotal = linesTotal + rounding;
    const tendered = sum(input.tenders.map((t) => t.amount));
    const balance = grandTotal - tendered;
    const discountTotal = lineDiscountSum + groupSum + orderDiscountTotal;

    return {
        lines,
        order: {
            subtotal,
            line_discount_total: lineDiscountSum,
            group_discount: groupSum,
            order_discount: orderDiscountTotal,
            discount_total: discountTotal,
            taxable,
            tax,
            rounding,
            grand_total: grandTotal,
            effective_discount_bp: bp(discountTotal, subtotal),
            tendered,
            balance,
            change: Math.max(0, -balance),
            due: Math.max(0, balance),
        },
    };
}
