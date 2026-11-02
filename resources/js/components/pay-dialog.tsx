import { useState } from 'react';
import { FormField } from '@/components/form-field';
import { MoneyInput } from '@/components/money-input';
import { NativeSelect } from '@/components/native-select';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useFormSubmit } from '@/hooks/use-form-submit';
import { useHotkeyScope } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';

export type PaymentMethodOption = { id: number; name: string; requires_reference: boolean };

/** Pay-the-vendor dialog: amount (defaults to the amount due), method, reference. Posts to `url`. */
export function PayDialog({
    open,
    onOpenChange,
    url,
    due,
    methods,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    url: string;
    due: number;
    methods: PaymentMethodOption[];
}) {
    const { t } = useT();
    const { submit, processing, errors } = useFormSubmit();
    const [amount, setAmount] = useState<number | null>(due > 0 ? due : null);
    const [methodId, setMethodId] = useState<string>(methods[0] ? String(methods[0].id) : '');
    const [reference, setReference] = useState('');
    const method = methods.find((m) => String(m.id) === methodId);
    useHotkeyScope('dialog', open);

    const pay = () =>
        submit(
            'post',
            url,
            { amount, payment_method_id: methodId === '' ? null : Number(methodId), reference: reference || null },
            { onSuccess: () => onOpenChange(false) },
        );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-140">
                <DialogHeader><DialogTitle>{t('vendors.pay')}</DialogTitle></DialogHeader>
                <form className="grid gap-4" noValidate onSubmit={(e) => { e.preventDefault(); pay(); }}>
                    <FormField label={t('vendors.pay_amount')} error={errors['amount'] || errors['form']}>
                        <MoneyInput value={amount} onChange={setAmount} />
                    </FormField>
                    <FormField label={t('vendors.pay_method')} error={errors['payment_method_id']}>
                        <NativeSelect value={methodId} onChange={(e) => setMethodId(e.target.value)}>
                            {methods.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                        </NativeSelect>
                    </FormField>
                    <FormField label={t('vendors.pay_reference')} error={errors['reference']} hint={method?.requires_reference ? t('vendors.pay_reference_required') : undefined}>
                        <Input className="min-h-11" value={reference} onChange={(e) => setReference(e.target.value)} />
                    </FormField>
                    <DialogFooter>
                        <Button type="button" variant="outline" className="min-h-11" onClick={() => onOpenChange(false)}>{t('common.cancel')}</Button>
                        <Button type="submit" className="min-h-11" disabled={processing || amount === null || amount <= 0}>{t('vendors.pay_confirm')}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
