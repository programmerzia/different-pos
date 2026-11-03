import { Delete, Lock } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

export const PIN_MIN = 4;
export const PIN_MAX = 6;

/** Digits only, capped at PIN_MAX (Bangla digits are accepted from the keyboard too). */
export const pinKeyDigit = (key: string): string | null => {
    if (/^[0-9]$/.test(key)) {
        return key;
    }
    const bangla = '০১২৩৪৫৬৭৮৯'.indexOf(key);

    return bangla >= 0 ? String(bangla) : null;
};

export const PIN_PAD_KEYS = [
    '1',
    '2',
    '3',
    '4',
    '5',
    '6',
    '7',
    '8',
    '9',
    'clear',
    '0',
    'backspace',
] as const;

type Props = {
    /** customer = large dots and keys the customer reads at arm's length; staff = compact. */
    variant?: 'customer' | 'staff';
    /** Called with the digits once the customer confirms (Enter / Confirm, 4–6 digits). */
    onSubmit: (pin: string) => void | Promise<void>;
    /** Shown under the dots (wrong PIN, lock, weak PIN). */
    error?: string | null;
    /** Shown instead of the error when the entry succeeded. */
    success?: string | null;
    busy?: boolean;
    confirmLabel?: string;
    autoFocus?: boolean;
    className?: string;
};

/**
 * The PIN pad (04_TRD §3.8 PinPad, staff/customer variants). Digits are never rendered: the dots
 * count them, the on-screen keys and the keyboard (digits, Backspace, Enter, Esc clears) both work,
 * and the value only leaves through onSubmit. `aria-live` tells the count, not the digits.
 */
export function PinPad({
    variant = 'customer',
    onSubmit,
    error = null,
    success = null,
    busy = false,
    confirmLabel,
    autoFocus = true,
    className,
}: Props) {
    const { t } = useT();
    const d = useDigits();
    const id = useId();
    const [digits, setDigits] = useState('');
    const customer = variant === 'customer';
    const keyboard = useRef<HTMLInputElement>(null);

    const ready = digits.length >= PIN_MIN && !busy;

    const press = (key: (typeof PIN_PAD_KEYS)[number]) => {
        if (busy) {
            return;
        }
        keyboard.current?.focus();
        if (key === 'clear') {
            setDigits('');
        } else if (key === 'backspace') {
            setDigits((v) => v.slice(0, -1));
        } else {
            setDigits((v) => (v.length < PIN_MAX ? v + key : v));
        }
    };

    const submit = () => {
        if (!ready) {
            return;
        }
        const value = digits;
        setDigits('');
        void onSubmit(value);
    };

    const onKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        const digit = pinKeyDigit(e.key);
        if (digit !== null) {
            e.preventDefault();
            press(digit as (typeof PIN_PAD_KEYS)[number]);
        } else if (e.key === 'Backspace') {
            e.preventDefault();
            press('backspace');
        } else if (e.key === 'Enter') {
            e.preventDefault();
            submit();
        } else if (e.key === 'Delete') {
            e.preventDefault();
            press('clear');
        }
    };

    return (
        <fieldset
            aria-label={t('pin.pad_label')}
            aria-describedby={`${id}-status`}
            data-testid="pin-pad"
            className={cn('flex flex-col gap-3 rounded-xl', className)}
        >
            {/* The keyboard target: a masked field that keeps the focus; its value is never read, the state is. */}
            <input
                ref={keyboard}
                type="password"
                inputMode="numeric"
                autoComplete="off"
                aria-label={t('pin.pad_label')}
                autoFocus={autoFocus}
                value=""
                onChange={() => undefined}
                onKeyDown={onKeyDown}
                className="sr-only"
            />
            {/* The dots: one per typed digit, never the digit itself. */}
            <div
                className={cn(
                    'flex items-center justify-center gap-3 rounded-xl border bg-surface-sunken',
                    customer ? 'h-16' : 'h-10',
                )}
                aria-hidden="true"
            >
                {Array.from({ length: PIN_MAX }, (_, i) => (
                    <span
                        key={i}
                        data-filled={i < digits.length ? 'true' : 'false'}
                        className={cn(
                            'rounded-full transition-all',
                            customer ? 'size-4' : 'size-3',
                            i < digits.length
                                ? 'scale-110 bg-primary'
                                : i < PIN_MIN
                                  ? 'bg-border'
                                  : 'border border-dashed border-border bg-transparent',
                        )}
                    />
                ))}
            </div>
            <output
                id={`${id}-status`}
                aria-live="polite"
                className={cn(
                    'min-h-5 text-center font-bold',
                    customer ? 'text-sm' : 'text-[11px]',
                    error ? 'text-danger' : success ? 'text-success' : 'text-muted-foreground',
                )}
            >
                {error ??
                    success ??
                    t('pin.entered', { n: d.n(digits.length) })}
            </output>
            <div className="grid grid-cols-3 gap-2">
                {PIN_PAD_KEYS.map((key) => (
                    <button
                        key={key}
                        type="button"
                        disabled={busy}
                        onClick={() => press(key)}
                        aria-label={
                            key === 'clear'
                                ? t('pin.clear')
                                : key === 'backspace'
                                  ? t('pin.backspace')
                                  : d.n(key)
                        }
                        className={cn(
                            'touch-target flex items-center justify-center rounded-xl bg-surface-sunken font-mono font-bold text-foreground transition-colors hover:bg-border focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50',
                            customer ? 'h-14 text-xl' : 'h-11 text-sm',
                        )}
                    >
                        {key === 'clear' ? (
                            <span className="text-xs font-semibold uppercase">
                                {t('pin.clear_short')}
                            </span>
                        ) : key === 'backspace' ? (
                            <Delete
                                className={customer ? 'size-6' : 'size-4'}
                                aria-hidden="true"
                            />
                        ) : (
                            d.n(key)
                        )}
                    </button>
                ))}
            </div>
            <Button
                type="button"
                className={cn('w-full font-bold', customer ? 'h-12 text-base' : 'h-10')}
                disabled={!ready}
                onClick={submit}
            >
                <Lock aria-hidden="true" />
                {confirmLabel ?? t('pin.confirm')}
            </Button>
        </fieldset>
    );
}
