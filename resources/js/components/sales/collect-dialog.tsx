import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { MoneyInput } from '@/components/money-input';
import { NativeSelect } from '@/components/native-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { Paisa } from '@/engine/money';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeyScope } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import {
    allocatedTotal,
    allocateOldestFirst,
    allocateSelected,
} from '@/lib/dues';
import type { MoneyMethod } from '@/lib/sales';

export type CollectInvoice = {
    id: number;
    number: string;
    sold_at: string | null;
    due: Paisa;
};

/**
 * Due collection: amount, method, reference and the allocation preview (oldest first, or only the
 * ticked invoices). Overpayment stays on the account as an advance. The server does the real allocation.
 */
export function CollectDialog({
    open,
    onOpenChange,
    action,
    title,
    invoices,
    methods,
    onCollected,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** POST target. */
    action: string;
    title: string;
    /** Open invoices, oldest first. */
    invoices: CollectInvoice[];
    methods: MoneyMethod[];
    onCollected?: () => void;
}) {
    const { t } = useT();
    const digits = useDigits();
    const totalDue = invoices.reduce((sum, i) => sum + i.due, 0);
    const [amount, setAmount] = useState<Paisa | null>(totalDue);
    const [methodId, setMethodId] = useState<number | null>(
        methods[0]?.id ?? null,
    );
    const [reference, setReference] = useState('');
    const [note, setNote] = useState('');
    const [manual, setManual] = useState(false);
    const [selected, setSelected] = useState<Set<number>>(() => new Set());
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    useHotkeyScope('dialog', open);

    const method = methods.find((m) => m.id === methodId);
    const preview = useMemo(
        () =>
            manual
                ? allocateSelected(invoices, selected, amount ?? 0)
                : allocateOldestFirst(invoices, amount ?? 0),
        [manual, invoices, selected, amount],
    );
    const allocated = allocatedTotal(preview);
    const advance = (amount ?? 0) - allocated;

    const toggle = (id: number) =>
        setSelected((current) => {
            const next = new Set(current);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });

    const submit = () => {
        const allocations = manual
            ? Object.entries(preview.allocations).map(([saleId, share]) => ({
                  sale_id: Number(saleId),
                  amount: share,
              }))
            : null;
        router.post(
            action,
            {
                amount: amount ?? 0,
                payment_method_id: methodId,
                reference: reference.trim() || null,
                note: note.trim() || null,
                ...(allocations && allocations.length > 0
                    ? { allocations }
                    : {}),
            },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onError: (e) => setErrors(e),
                onSuccess: () => {
                    setErrors({});
                    onOpenChange(false);
                    onCollected?.();
                },
            },
        );
    };

    const invalid =
        !amount ||
        amount <= 0 ||
        methodId === null ||
        (method?.requires_reference === true && reference.trim() === '') ||
        (manual && advance > 0 && selected.size === 0);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => !busy && onOpenChange(next)}
        >
            <DialogContent className="max-w-160">
                <form
                    className="grid gap-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (!invalid) {
                            submit();
                        }
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>
                            {t('dues.collect_hint', {
                                due: digits.money(totalDue),
                            })}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="collect-amount">
                                {t('dues.amount')}
                            </Label>
                            <MoneyInput
                                id="collect-amount"
                                value={amount}
                                onChange={setAmount}
                                invalid={errors['amount'] !== undefined}
                            />
                            <InputError message={errors['amount']} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="collect-method">
                                {t('dues.method')}
                            </Label>
                            <NativeSelect
                                id="collect-method"
                                value={methodId ?? ''}
                                onChange={(e) =>
                                    setMethodId(Number(e.target.value) || null)
                                }
                            >
                                {methods.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name}
                                    </option>
                                ))}
                            </NativeSelect>
                            <InputError message={errors['payment_method_id']} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="collect-reference">
                                {t('dues.reference')}
                                {method?.requires_reference ? ' *' : ''}
                            </Label>
                            <Input
                                id="collect-reference"
                                className="min-h-11 md:min-h-9"
                                value={reference}
                                maxLength={100}
                                onChange={(e) => setReference(e.target.value)}
                            />
                            <InputError message={errors['reference']} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="collect-note">
                                {t('dues.note')}
                            </Label>
                            <Input
                                id="collect-note"
                                className="min-h-11 md:min-h-9"
                                value={note}
                                maxLength={255}
                                onChange={(e) => setNote(e.target.value)}
                            />
                        </div>
                    </div>
                    {invoices.length > 1 ? (
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={manual ? 'manual' : 'auto'}
                            onValueChange={(v) =>
                                v && setManual(v === 'manual')
                            }
                            aria-label={t('dues.allocation')}
                        >
                            <ToggleGroupItem
                                value="auto"
                                className="min-h-11 md:min-h-9"
                            >
                                {t('dues.oldest_first')}
                            </ToggleGroupItem>
                            <ToggleGroupItem
                                value="manual"
                                className="min-h-11 md:min-h-9"
                            >
                                {t('dues.choose_invoices')}
                            </ToggleGroupItem>
                        </ToggleGroup>
                    ) : null}
                    <div className="max-h-64 overflow-y-auto rounded-md border">
                        <table className="w-full text-sm">
                            <caption className="sr-only">
                                {t('dues.allocation')}
                            </caption>
                            <thead className="bg-muted text-muted-foreground">
                                <tr>
                                    {manual ? (
                                        <th className="w-10 p-2" scope="col">
                                            <span className="sr-only">
                                                {t('table.select_row')}
                                            </span>
                                        </th>
                                    ) : null}
                                    <th className="p-2 text-left" scope="col">
                                        {t('dues.invoice')}
                                    </th>
                                    <th className="p-2 text-right" scope="col">
                                        {t('dues.due')}
                                    </th>
                                    <th className="p-2 text-right" scope="col">
                                        {t('dues.applied')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.map((invoice) => (
                                    <tr key={invoice.id} className="border-t">
                                        {manual ? (
                                            <td className="p-2">
                                                <Checkbox
                                                    checked={selected.has(
                                                        invoice.id,
                                                    )}
                                                    onCheckedChange={() =>
                                                        toggle(invoice.id)
                                                    }
                                                    aria-label={t(
                                                        'dues.pick_invoice',
                                                        {
                                                            number: invoice.number,
                                                        },
                                                    )}
                                                    className="size-5"
                                                />
                                            </td>
                                        ) : null}
                                        <td className="p-2">
                                            <span className="num font-medium">
                                                {invoice.number}
                                            </span>
                                            <span className="num ml-2 text-xs text-muted-foreground">
                                                {digits.n(
                                                    invoice.sold_at ?? '',
                                                )}
                                            </span>
                                        </td>
                                        <td className="num p-2 text-right">
                                            {digits.money(invoice.due)}
                                        </td>
                                        <td className="num p-2 text-right font-semibold">
                                            {preview.allocations[invoice.id]
                                                ? digits.money(
                                                      preview.allocations[
                                                          invoice.id
                                                      ] ?? 0,
                                                  )
                                                : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <dl className="grid grid-cols-2 gap-1 text-sm">
                        <dt className="text-muted-foreground">
                            {t('dues.allocated')}
                        </dt>
                        <dd className="num text-right font-semibold">
                            {digits.money(allocated)}
                        </dd>
                        <dt className="text-muted-foreground">
                            {t('dues.advance')}
                        </dt>
                        <dd className="num text-right font-semibold">
                            {digits.money(Math.max(0, advance))}
                        </dd>
                    </dl>
                    <InputError
                        message={errors['allocations'] ?? errors['form']}
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11"
                            disabled={busy}
                            onClick={() => onOpenChange(false)}
                        >
                            {t('confirm.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            className="min-h-11"
                            disabled={busy || invalid}
                        >
                            {t('dues.collect_confirm', {
                                amount: digits.money(amount ?? 0),
                            })}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
