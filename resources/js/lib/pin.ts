export const PIN_MAX = 6;

export type PinAction =
    | { type: 'digit'; digit: string }
    | { type: 'back' }
    | { type: 'clear' };

/** Digits only, at most PIN_MAX. The PIN lives in component state and is never logged. */
export function pinReducer(
    pin: string,
    action: PinAction,
    max = PIN_MAX,
): string {
    switch (action.type) {
        case 'digit':
            return /^[0-9]$/.test(action.digit) && pin.length < max
                ? pin + action.digit
                : pin;
        case 'back':
            return pin.slice(0, -1);
        case 'clear':
            return '';
    }
}
