import { emptyCart } from './cart';
import type { CartLine, CartState, CustomerCard } from './types';

/** A bill put aside with F8. Kept in localStorage per branch and register; approvals are not kept. */
export type HeldCart = {
    id: string;
    heldAt: string;
    state: CartState;
};

export const MAX_HELD = 20;

export function heldKey(branchId: number, registerId: number | null): string {
    return `pos.held.${branchId}.${registerId ?? 0}`;
}

const isInt = (v: unknown): v is number => Number.isSafeInteger(v);
const isStr = (v: unknown): v is string => typeof v === 'string';

function isDiscount(v: unknown): boolean {
    if (typeof v !== 'object' || v === null) {
        return false;
    }
    const d = v as Record<string, unknown>;

    return (
        ['none', 'percent_bp', 'amount'].includes(String(d['type'])) &&
        isInt(d['value']) &&
        (d['value'] as number) >= 0
    );
}

function isLine(v: unknown): v is CartLine {
    if (typeof v !== 'object' || v === null) {
        return false;
    }
    const l = v as Record<string, unknown>;

    return (
        isStr(l['key']) &&
        isInt(l['productId']) &&
        isStr(l['name']) &&
        isStr(l['sku']) &&
        isInt(l['unitPrice']) &&
        isInt(l['qty']) &&
        (l['qty'] as number) > 0 &&
        isInt(l['taxRateBp']) &&
        typeof l['allowsDecimal'] === 'boolean' &&
        (l['stock'] === null || isInt(l['stock'])) &&
        (l['label'] === null || isStr(l['label'])) &&
        isDiscount(l['discount'])
    );
}

function isCustomer(v: unknown): v is CustomerCard {
    if (typeof v !== 'object' || v === null) {
        return false;
    }
    const c = v as Record<string, unknown>;

    return (
        isInt(c['id']) &&
        isStr(c['uuid']) &&
        isStr(c['name']) &&
        isInt(c['balance']) &&
        isInt(c['advance'])
    );
}

/** Reads held bills, dropping anything malformed (an old version, a hand-edited value). */
export function parseHeld(text: string | null): HeldCart[] {
    if (!text) {
        return [];
    }
    let raw: unknown;
    try {
        raw = JSON.parse(text);
    } catch {
        return [];
    }
    if (!Array.isArray(raw)) {
        return [];
    }

    const out: HeldCart[] = [];
    for (const item of raw) {
        if (typeof item !== 'object' || item === null) {
            continue;
        }
        const h = item as Record<string, unknown>;
        const s = h['state'] as Record<string, unknown> | undefined;
        if (
            !isStr(h['id']) ||
            !isStr(h['heldAt']) ||
            typeof s !== 'object' ||
            s === null
        ) {
            continue;
        }
        const lines = Array.isArray(s['lines']) ? s['lines'] : [];
        if (
            lines.length === 0 ||
            !lines.every(isLine) ||
            !isStr(s['clientUuid'])
        ) {
            continue;
        }
        const customer =
            s['customer'] === null || s['customer'] === undefined
                ? null
                : s['customer'];
        if (customer !== null && !isCustomer(customer)) {
            continue;
        }
        out.push({
            id: h['id'],
            heldAt: h['heldAt'],
            state: {
                ...emptyCart(s['clientUuid']),
                lines,
                selected: 0,
                orderDiscount: isDiscount(s['orderDiscount'])
                    ? (s['orderDiscount'] as CartState['orderDiscount'])
                    : { type: 'none', value: 0 },
                customer,
            },
        });
    }

    return out;
}

export function serializeHeld(list: readonly HeldCart[]): string {
    return JSON.stringify(
        list.map((h) => ({ ...h, state: { ...h.state, approval: null } })),
    );
}

/** Puts the bill on top of the list (newest first, at most MAX_HELD). */
export function holdCart(
    list: readonly HeldCart[],
    state: CartState,
    now: Date = new Date(),
): HeldCart[] {
    const held: HeldCart = {
        id: state.clientUuid,
        heldAt: now.toISOString(),
        state: { ...state, approval: null },
    };

    return [held, ...list.filter((h) => h.id !== held.id)].slice(0, MAX_HELD);
}

/** Takes a held bill out of the list. */
export function resumeHeld(
    list: readonly HeldCart[],
    id: string,
): { state: CartState | null; list: HeldCart[] } {
    const found = list.find((h) => h.id === id);

    return {
        state: found ? found.state : null,
        list: list.filter((h) => h.id !== id),
    };
}

export function loadHeld(
    storage: Pick<Storage, 'getItem'>,
    key: string,
): HeldCart[] {
    try {
        return parseHeld(storage.getItem(key));
    } catch {
        return [];
    }
}

export function saveHeld(
    storage: Pick<Storage, 'setItem' | 'removeItem'>,
    key: string,
    list: readonly HeldCart[],
): void {
    try {
        if (list.length === 0) {
            storage.removeItem(key);
        } else {
            storage.setItem(key, serializeHeld(list));
        }
    } catch {
        // Storage full or blocked: holding is best effort on this page.
    }
}
