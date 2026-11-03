import { Hash } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { QtyStepper } from '@/components/qty-stepper';
import { Button } from '@/components/ui/button';
import { useT } from '@/i18n';
import type { CartLine } from '../types';
import { PosDialog } from './pos-dialog';

type Props = {
    line: CartLine | null;
    allowNegative: boolean;
    onApply: (qty: number) => void;
    onClose: () => void;
};

/** F3: quantity of the selected line. */
export function QtyDialog({ line, onClose, ...rest }: Props) {
    const { t } = useT();

    return (
        <PosDialog
            icon={Hash}
            open={line !== null}
            onClose={onClose}
            title={t('pos.qty.title', { name: line?.name ?? '' })}
            className="sm:max-w-sm"
        >
            {line && <QtyBody line={line} onClose={onClose} {...rest} />}
        </PosDialog>
    );
}

function QtyBody({
    line,
    allowNegative,
    onApply,
    onClose,
}: Props & { line: CartLine }) {
    const { t } = useT();
    const [qty, setQty] = useState(line.qty);
    const latest = useRef(line.qty);
    const change = (value: number) => {
        latest.current = value;
        setQty(value);
    };
    const box = useRef<HTMLDivElement>(null);
    useEffect(() => box.current?.querySelector('input')?.focus(), []);
    const max =
        line.stock !== null && !allowNegative
            ? Math.max(line.stock, 0)
            : undefined;

    return (
        <form
            className="space-y-4"
            onSubmit={(e) => {
                e.preventDefault();
                // The stepper commits typed text on blur.
                (document.activeElement as HTMLElement | null)?.blur();
                onApply(latest.current);
            }}
        >
            <div ref={box}>
                <QtyStepper
                    value={qty}
                    onChange={change}
                    min={0}
                    {...(max !== undefined ? { max } : {})}
                    integer={!line.allowsDecimal}
                    aria-label={t('pos.bill.qty')}
                />
            </div>
            <div className="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-11"
                    onClick={onClose}
                >
                    {t('confirm.cancel')}
                </Button>
                <Button type="submit" className="min-h-11">
                    {t('confirm.ok')}
                </Button>
            </div>
        </form>
    );
}
