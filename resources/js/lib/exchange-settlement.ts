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
