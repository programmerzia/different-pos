import { Delete } from 'lucide-react';
import { useEffect, useReducer } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useHotkeyScope } from '@/hotkeys/use-hotkeys';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { PIN_MAX, pinReducer, type PinAction } from '@/lib/pin';
import { cn } from '@/lib/utils';

type Props = {
    /** `customer`: high-contrast overlay for the customer; no staff UI visible. */
    variant: 'staff' | 'customer';
    open: boolean;
    onSubmit: (pin: string) => void;
    onCancel: () => void;
    /** Customer variant: shown above the pad. */
    customerName?: string | undefined;
    /** Set after a wrong PIN; clears the entered digits. */
    error?: string | null | undefined;
    length?: number;
};

const MIN_LENGTH = 4;
const KEYS = ['1', '2', '3', '4', '5', '6', '7', '8', '9'] as const;

/**
 * Numeric PIN entry. Digits are never shown (dots only); physical digit keys, Backspace and Enter
 * work. The PIN stays in local state and goes only to `onSubmit`. The body unmounts with the dialog,
 * so every opening starts empty.
 */
export function PinPad({
    variant,
    open,
    onSubmit,
    onCancel,
    customerName,
    error,
    length = PIN_MAX,
}: Props) {
    useHotkeyScope('dialog', open);

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onCancel()}>
            <PinPadContent
                variant={variant}
                onSubmit={onSubmit}
                onCancel={onCancel}
                customerName={customerName}
                error={error}
                length={length}
            />
        </Dialog>
    );
}

function PinPadContent({
    variant,
    onSubmit,
    onCancel,
    customerName,
    error,
    length = PIN_MAX,
}: Omit<Props, 'open'>) {
    const { t } = useT();
    const digits = useDigits();
    const [pin, dispatch] = useReducer(
        (state: string, action: PinAction) => pinReducer(state, action, length),
        '',
    );

    // A wrong PIN comes back as `error`: start over.
    useEffect(() => {
        if (error) {
            dispatch({ type: 'clear' });
        }
    }, [error]);

    const press = (key: string) => {
        if (key === 'Backspace') {
            dispatch({ type: 'back' });
        } else if (key === 'Enter') {
            if (pin.length >= MIN_LENGTH) {
                onSubmit(pin);
            }
        } else {
            dispatch({ type: 'digit', digit: digits.latin(key) });
        }
    };

    const keyButton = 'num h-18 w-18 min-h-18 min-w-18 text-[1.75rem]';
    const customer = variant === 'customer';

    return (
        <DialogContent
            className={cn(
                'max-w-sm items-center justify-items-center',
                customer &&
                    'inset-0 h-full max-w-none translate-x-0 translate-y-0 rounded-none bg-background',
            )}
            onKeyDown={(e) => {
                if (
                    /^[0-9০-৯]$/.test(e.key) ||
                    e.key === 'Backspace' ||
                    e.key === 'Enter'
                ) {
                    e.preventDefault();
                    press(e.key);
                }
            }}
        >
            <DialogTitle
                className={cn('text-center', customer ? 'text-3xl' : 'text-lg')}
            >
                {customer ? t('pin.customer_title') : t('pin.staff_title')}
            </DialogTitle>
            <DialogDescription className="text-center">
                {customer
                    ? `${customerName ?? ''} ${t('pin.customer_hint')}`.trim()
                    : t('pin.staff_hint')}
            </DialogDescription>
            <output
                aria-label={t('pin.entered', { n: digits.n(pin.length) })}
                className={cn(
                    'flex gap-3 py-2',
                    error && 'motion-safe:animate-pulse',
                )}
            >
                {Array.from({ length }, (_, i) => (
                    <span
                        key={i}
                        className={cn(
                            'size-4 rounded-full border-2 border-primary',
                            i < pin.length && 'bg-primary',
                        )}
                    />
                ))}
            </output>
            {error ? (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            ) : null}
            <div className="grid grid-cols-3 gap-3">
                {KEYS.map((k) => (
                    <Button
                        key={k}
                        type="button"
                        variant="outline"
                        className={keyButton}
                        onClick={() => press(k)}
                    >
                        {digits.n(k)}
                    </Button>
                ))}
                <Button
                    type="button"
                    variant="ghost"
                    className={keyButton}
                    aria-label={t('pin.clear')}
                    onClick={() => dispatch({ type: 'clear' })}
                >
                    C
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    className={keyButton}
                    onClick={() => press('0')}
                >
                    {digits.n('0')}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    className={keyButton}
                    aria-label={t('pin.backspace')}
                    onClick={() => press('Backspace')}
                >
                    <Delete aria-hidden="true" />
                </Button>
            </div>
            <div className="flex w-full gap-2">
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-14 flex-1"
                    onClick={onCancel}
                >
                    {t('confirm.cancel')}
                </Button>
                <Button
                    type="button"
                    className="min-h-14 flex-1"
                    disabled={pin.length < MIN_LENGTH}
                    onClick={() => press('Enter')}
                >
                    {t('pin.confirm')}
                </Button>
            </div>
        </DialogContent>
    );
}
