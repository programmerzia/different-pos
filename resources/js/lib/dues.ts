import type { Paisa } from '@/engine/money';

/** Aging buckets for open dues by age in whole days. Mirrors App\Support\Sales\DueAging. */
export const BUCKETS = ['d0_30', 'd31_60', 'd61_90', 'd90_plus'] as const;
export type Bucket = (typeof BUCKETS)[number];

export function agingBucket(days: number): Bucket {
    if (days <= 30) {
        return 'd0_30';
    }
    if (days <= 60) {
        return 'd31_60';
    }

    return days <= 90 ? 'd61_90' : 'd90_plus';
}

/** Sums dues per bucket. */
export function bucketTotals(
    invoices: readonly { due: Paisa; age: number }[],
): Record<Bucket, Paisa> {
    const totals: Record<Bucket, Paisa> = {
        d0_30: 0,
        d31_60: 0,
        d61_90: 0,
        d90_plus: 0,
    };
    for (const invoice of invoices) {
        totals[agingBucket(invoice.age)] += invoice.due;
    }

    return totals;
}

export type Allocation = {
    /** sale id → paisa */
    allocations: Record<number, Paisa>;
    /** Left over after every due is settled: stays on the account. */
    advance: Paisa;
};

/** Oldest-first allocation, the same walk the server does. `invoices` must be oldest first. */
export function allocateOldestFirst(
    invoices: readonly { id: number; due: Paisa }[],
    amount: Paisa,
): Allocation {
    let left = Math.max(0, amount);
    const allocations: Record<number, Paisa> = {};
    for (const invoice of invoices) {
        if (left <= 0) {
            break;
        }
        if (invoice.due <= 0) {
            continue;
        }
        const share = Math.min(invoice.due, left);
        allocations[invoice.id] = share;
        left -= share;
    }

    return { allocations, advance: left };
}

/** Manual allocation: checked invoices, each capped at its due and at what is left of the amount (oldest first). */
export function allocateSelected(
    invoices: readonly { id: number; due: Paisa }[],
    selected: ReadonlySet<number>,
    amount: Paisa,
): Allocation {
    return allocateOldestFirst(
        invoices.filter((invoice) => selected.has(invoice.id)),
        amount,
    );
}

/** The total of a manual allocation. */
export function allocatedTotal(allocation: Allocation): Paisa {
    return Object.values(allocation.allocations).reduce((a, b) => a + b, 0);
}
