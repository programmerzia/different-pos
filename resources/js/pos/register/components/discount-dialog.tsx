import { Percent } from 'lucide-react';
import { useState } from 'react';
import { MoneyInput } from '@/components/money-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { mulDivHalfUp, parseMoney, type Paisa } from '@/engine/money';
import type { Discount } from '@/engine/pricing';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { PosDialog } from './pos-dialog';

/** "12.5" → 1250 bp (two decimals, integers only); null when not a percentage 0–100. */
export function parsePercentBp(text: string): number | null {
    try {
        const bp = parseMoney(text.trim() === '' ? '0' : text.trim());

        return bp >= 0 && bp <= 10_000 ? bp : null;
    } catch {
        return null;
    }
}

const bpText = (bp: number): string =>
    `${Math.trunc(bp / 100)}${
        bp % 100 === 0
            ? ''
            : `.${String(bp % 100)
                  .padStart(2, '0')
                  .replace(/0$/, '')}`
    }`;

type Props = {
    open: boolean;
    title: string;
    /** The amount the discount applies to (line gross / bill after line discounts). */
    base: Paisa;
    current: Discount;
    capBp: number;
    onApply: (discount: Discount) => void;
    onNeedApproval: () => void;
    onClose: () => void;
};

/**
 * F6 (line) / Shift+F6 (bill): percent or fixed amount. Above the role cap is allowed to enter but
 * flagged: a manager must approve it before Pay, and the server checks again.
 */
export function DiscountDialog({ open, title, onClose, ...rest }: Props) {
    const { t } = useT();
    const d = useDigits();

    return (
        <PosDialog
            icon={Percent}
            open={open}
            onClose={onClose}
            title={title}
            description={t('pos.discount.base', { amount: d.money(rest.base) })}
            className="sm:max-w-md"
        >
            <DiscountBody onClose={onClose} {...rest} />
        </PosDialog>
    );
}

function DiscountBody({
    base,
    current,
    capBp,
    onApply,
    onNeedApproval,
    onClose,
}: Omit<Props, 'open' | 'title'>) {
    const { t } = useT();
    const d = useDigits();
    const [mode, setMode] = useState<'percent_bp' | 'amount'>(
        current.type === 'amount' ? 'amount' : 'percent_bp',
    );
    const [percent, setPercent] = useState(
        current.type === 'percent_bp' ? bpText(current.value) : '',
    );
    const [amount, setAmount] = useState<Paisa | null>(
        current.type === 'amount' ? current.value : null,
    );
    const bp = mode === 'percent_bp' ? parsePercentBp(d.latin(percent)) : null;
    const value =
        mode === 'percent_bp' ? (bp ?? 0) : Math.min(amount ?? 0, base);
    const discountPaisa =
        mode === 'percent_bp' ? mulDivHalfUp(base, value, 10_000) : value;
    const effectiveBp =
        base === 0 ? 0 : mulDivHalfUp(discountPaisa, 10_000, base);
    const overCap = effectiveBp > capBp;
    const invalid = mode === 'percent_bp' && bp === null;

    const apply = () => {
        if (invalid) {
            return;
        }
        onApply(
            value === 0 ? { type: 'none', value: 0 } : { type: mode, value },
        );
    };

    return (
        <form
            className="space-y-3"
            onSubmit={(e) => {
                e.preventDefault();
                apply();
            }}
        >
            <ToggleGroup
                type="single"
                variant="outline"
                value={mode}
                onValueChange={(v) => v && setMode(v as typeof mode)}
                aria-label={t('pos.discount.mode')}
            >
                <ToggleGroupItem value="percent_bp" className="min-h-11 px-4">
                    {t('pos.discount.percent')}
                </ToggleGroupItem>
                <ToggleGroupItem value="amount" className="min-h-11 px-4">
                    {t('pos.discount.amount')}
                </ToggleGroupItem>
            </ToggleGroup>
            <div className="space-y-1">
                <Label htmlFor="pos-discount-value">
                    {mode === 'percent_bp'
                        ? t('pos.discount.percent')
                        : t('pos.discount.amount')}
                </Label>
                {mode === 'percent_bp' ? (
                    <Input
                        id="pos-discount-value"
                        autoFocus
                        inputMode="decimal"
                        className="num h-11 text-right"
                        value={percent}
                        aria-invalid={invalid || undefined}
                        onChange={(e) => setPercent(e.target.value)}
                        onFocus={(e) => e.currentTarget.select()}
                    />
                ) : (
                    <MoneyInput
                        id="pos-discount-value"
                        value={amount}
                        onChange={setAmount}
                        className="h-11"
                    />
                )}
            </div>
            <p className="num text-sm">
                {t('pos.discount.preview', {
                    amount: d.money(discountPaisa),
                    pct: d.n(bpText(effectiveBp)),
                })}
            </p>
            {overCap && (
                <div
                    className="rounded-md border border-warning bg-warning-soft p-2 text-sm"
                    aria-live="polite"
                >
                    <p>
                        {t('pos.discount.over_cap', {
                            pct: d.n(bpText(capBp)),
                        })}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        className="mt-2 min-h-11"
                        onClick={onNeedApproval}
                    >
                        {t('pos.manager.ask')}
                    </Button>
                </div>
            )}
            <div className="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-11"
                    onClick={onClose}
                >
                    {t('confirm.cancel')}
                </Button>
                <Button type="submit" className="min-h-11" disabled={invalid}>
                    {t('pos.discount.apply')}
                </Button>
            </div>
        </form>
    );
}
