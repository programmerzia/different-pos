import { type KeyboardEventHandler, useState } from 'react';
import { Input } from '@/components/ui/input';
import { useDigits } from '@/hooks/use-digits';
import {
    paisaFromText,
    sanitizeMoneyText,
    textFromPaisa,
} from '@/lib/money-input';
import { cn } from '@/lib/utils';
import type { Paisa } from '@/engine/money';

type Props = {
    value: Paisa | null;
    onChange: (value: Paisa | null) => void;
    id?: string;
    name?: string;
    disabled?: boolean;
    invalid?: boolean;
    'aria-describedby'?: string;
    className?: string;
    onKeyDown?: KeyboardEventHandler<HTMLInputElement>;
};

/**
 * Integer-paisa money field (03_UI_Spec §5): digits and one ".", max 2 decimals, Bangla digits
 * accepted; selects all on focus; shows the raw value while editing and the formatted amount on blur.
 */
export function MoneyInput({
    value,
    onChange,
    invalid,
    className,
    ...rest
}: Props) {
    const digits = useDigits();
    const [text, setText] = useState<string | null>(null); // null = not editing

    const display = text ?? (value === null ? '' : digits.money(value, false));

    return (
        <div className="relative">
            <span
                aria-hidden="true"
                className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted-foreground"
            >
                ৳
            </span>
            <Input
                {...rest}
                type="text"
                inputMode="decimal"
                autoComplete="off"
                aria-invalid={invalid || undefined}
                className={cn('num pl-8 text-right', className)}
                value={display}
                onFocus={(event) => {
                    setText(textFromPaisa(value));
                    const input = event.currentTarget;
                    requestAnimationFrame(() => input.select());
                }}
                onChange={(event) => {
                    const next = sanitizeMoneyText(event.target.value);
                    setText(next);
                    onChange(paisaFromText(next));
                }}
                onBlur={() => setText(null)}
            />
        </div>
    );
}
