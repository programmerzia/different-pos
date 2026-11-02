import { Minus, Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    QTY_SCALE,
    parseQty,
    qtyToDecimalString,
    type QtyMilli,
} from '@/engine/quantity';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { stepQty } from '@/lib/qty-step';

type Props = {
    /** Milli-units: 1.5 = 1500. */
    value: QtyMilli;
    onChange: (value: QtyMilli) => void;
    /** Step in milli-units (1000 = whole units). */
    step?: QtyMilli;
    min?: QtyMilli;
    max?: QtyMilli;
    /** Whole-unit products: no decimals can be typed. */
    integer?: boolean;
    id?: string;
    'aria-label'?: string;
};

const REPEAT_DELAY = 400;
const REPEAT_INTERVAL = 80;

/** − / + (44 px, long-press repeats) around an editable quantity. */
export function QtyStepper({
    value,
    onChange,
    step = QTY_SCALE,
    min = 0,
    max,
    integer = false,
    id,
    ...rest
}: Props) {
    const { t } = useT();
    const digits = useDigits();
    const latest = useRef(value);
    const [text, setText] = useState<string | null>(null);
    const timers = useRef<{
        delay?: ReturnType<typeof setTimeout>;
        repeat?: ReturnType<typeof setInterval>;
    }>({});

    useEffect(() => {
        latest.current = value;
    });

    const limits = { min, ...(max !== undefined ? { max } : {}) };
    const stop = () => {
        clearTimeout(timers.current.delay);
        clearInterval(timers.current.repeat);
    };
    useEffect(() => stop, []);

    const start = (sign: 1 | -1) => {
        const apply = () => {
            latest.current = stepQty(latest.current, sign * step, limits);
            onChange(latest.current);
        };
        apply();
        timers.current.delay = setTimeout(() => {
            timers.current.repeat = setInterval(apply, REPEAT_INTERVAL);
        }, REPEAT_DELAY);
    };

    const commit = (raw: string) => {
        try {
            const parsed = parseQty(integer ? raw.replace(/\..*/, '') : raw);
            onChange(stepQty(parsed, 0, limits));
        } catch {
            // Unparseable text keeps the previous value.
        }
    };

    return (
        <div className="inline-flex items-center gap-1">
            <Button
                type="button"
                variant="outline"
                size="icon"
                className="size-11"
                aria-label={t('qty.decrease')}
                disabled={value <= min}
                onPointerDown={() => start(-1)}
                onPointerUp={stop}
                onPointerLeave={stop}
                onPointerCancel={stop}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        onChange(stepQty(value, -step, limits));
                    }
                }}
            >
                <Minus aria-hidden="true" />
            </Button>
            <Input
                {...rest}
                id={id}
                type="text"
                inputMode="decimal"
                className="num h-11 w-24 text-center"
                value={text ?? digits.qty(value)}
                onFocus={(e) => {
                    setText(qtyToDecimalString(value).replace(/\.?0+$/, ''));
                    e.currentTarget.select();
                }}
                onChange={(e) =>
                    setText(
                        digits
                            .latin(e.target.value)
                            .replace(integer ? /[^0-9]/g : /[^0-9.]/g, ''),
                    )
                }
                onBlur={() => {
                    if (text !== null) {
                        commit(text);
                    }
                    setText(null);
                }}
                onKeyDown={(e) => {
                    if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                        e.preventDefault();
                        onChange(
                            stepQty(
                                value,
                                e.key === 'ArrowUp' ? step : -step,
                                limits,
                            ),
                        );
                    }
                }}
            />
            <Button
                type="button"
                variant="outline"
                size="icon"
                className="size-11"
                aria-label={t('qty.increase')}
                disabled={max !== undefined && value >= max}
                onPointerDown={() => start(1)}
                onPointerUp={stop}
                onPointerLeave={stop}
                onPointerCancel={stop}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        onChange(stepQty(value, step, limits));
                    }
                }}
            >
                <Plus aria-hidden="true" />
            </Button>
        </div>
    );
}
