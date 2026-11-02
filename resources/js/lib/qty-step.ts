import { MAX_QTY_MILLI, type QtyMilli } from '@/engine/quantity';

export type StepOptions = { min?: number; max?: number };

/** value ± delta (milli-units), clamped to [min, max]. Integer in, integer out. */
export function stepQty(
    value: QtyMilli,
    delta: QtyMilli,
    { min = 0, max = MAX_QTY_MILLI }: StepOptions = {},
): QtyMilli {
    return Math.min(max, Math.max(min, value + delta));
}
