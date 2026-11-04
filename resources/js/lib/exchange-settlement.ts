import type { Paisa } from '@/engine/money';

export type Settlement = 'refund' | 'credit' | 'exchange';

/** The one summary line of a return or exchange: who pays whom, and how much. */
export type SettlementSummary = {
    kind: 'gets' | 'pays' | 'even';
    amount: Paisa;
};

/**
 * Refund and store credit give the whole return value back. An exchange nets the return value
 * against the replacement cart: a dearer cart means the customer pays the difference, a cheaper one
 * means they get the rest back (as a refund or store credit, see GiveBack).
 */
export function settlementSummary(
    settlement: Settlement,
    returnValue: Paisa,
    replacementTotal: Paisa,
): SettlementSummary {
    const difference =
        settlement === 'exchange'
            ? replacementTotal - returnValue
            : -returnValue;
    if (difference === 0) {
        return { kind: 'even', amount: 0 };
    }

    return difference > 0
        ? { kind: 'pays', amount: difference }
        : { kind: 'gets', amount: -difference };
}

/** How an exchange surplus goes back: store credit, or a refund through a payment method. */
export type GiveBack =
    | { mode: 'credit' }
    | { mode: 'refund'; methodId: number };

export const giveBackValue = (g: GiveBack | null): string =>
    g === null ? '' : g.mode === 'credit' ? 'credit' : `m${g.methodId}`;

export function parseGiveBack(value: string): GiveBack | null {
    if (value === 'credit') {
        return { mode: 'credit' };
    }
    const id = Number(value.replace(/^m/, ''));

    return Number.isInteger(id) && id > 0
        ? { mode: 'refund', methodId: id }
        : null;
}

/** The server's exact figures for an exchange (POST /app/exchanges/quote, see App\Support\Sales\ExchangeQuote). */
export type QuoteLine = {
    product_id: number;
    name: string;
    sku: string;
    qty: number;
    total: Paisa;
    /** On hand in milli-units; null = stock is not tracked. */
    stock: number | null;
    short: boolean;
};

export type ExchangeQuote = {
    return_value: Paisa;
    replacement_total: Paisa;
    replacement_tax: Paisa;
    group_discount: Paisa;
    pays: Paisa;
    surplus: Paisa;
    due_cleared: Paisa;
    gets_back: Paisa;
    original_due: Paisa;
    walk_in: boolean;
    lines: QuoteLine[];
};

/** What an exchange still needs before it can be priced. */
export function exchangeNeeds(
    returnedLines: number,
    replacementLines: number,
): ('return' | 'replacement')[] {
    return [
        ...(returnedLines > 0 ? [] : (['return'] as const)),
        ...(replacementLines > 0 ? [] : (['replacement'] as const)),
    ];
}

/** The single result line of a quoted exchange. `surplus` carries both parts of a surplus (either may be 0). */
export type ExchangeResult =
    | { kind: 'pays'; amount: Paisa }
    | { kind: 'even' }
    | { kind: 'surplus'; dueCleared: Paisa; getsBack: Paisa };

export function exchangeResult(quote: ExchangeQuote): ExchangeResult {
    if (quote.pays > 0) {
        return { kind: 'pays', amount: quote.pays };
    }
    if (quote.surplus > 0) {
        return {
            kind: 'surplus',
            dueCleared: quote.due_cleared,
            getsBack: quote.gets_back,
        };
    }

    return { kind: 'even' };
}

/** A line the branch cannot cover; the message says how many are on hand. */
export const shortLines = (quote: ExchangeQuote): QuoteLine[] =>
    quote.lines.filter((l) => l.short);

export type TenderProblem =
    | 'reference'
    | 'overpay_non_cash'
    | 'walk_in_short'
    | null;

export type TenderCheck = {
    /** Change to hand back (cash only). */
    change: Paisa;
    /** Part of `pays` left on the customer's account as due. */
    onAccount: Paisa;
    problem: TenderProblem;
};

/**
 * What the cashier takes against `pays`. The server gives change from cash only and refuses to
 * overpay by another method, a walk-in has no account to leave a due on, and a method that needs a
 * reference (TrxID) needs it filled once money is taken through it.
 */
export function checkTender(input: {
    pays: Paisa;
    received: Paisa;
    method: { type: string; requires_reference: boolean } | null;
    reference: string;
    walkIn: boolean;
}): TenderCheck {
    const { pays, received, method } = input;
    const change = Math.max(0, received - pays);
    const onAccount = Math.max(0, pays - received);
    let problem: TenderProblem = null;
    if (pays > 0 && input.walkIn && received < pays) {
        problem = 'walk_in_short';
    } else if (
        received > 0 &&
        method?.requires_reference &&
        !input.reference.trim()
    ) {
        problem = 'reference';
    } else if (change > 0 && method?.type !== 'cash') {
        problem = 'overpay_non_cash';
    }

    return { change, onAccount, problem };
}
