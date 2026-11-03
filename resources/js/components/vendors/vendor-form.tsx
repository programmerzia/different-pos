import { Link } from '@inertiajs/react';
import { Truck } from 'lucide-react';
import { useState } from 'react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { DialogActions, DialogBar, DialogBody } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useFormSubmit } from '@/hooks/use-form-submit';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

export type VendorFormValue = {
    id: number;
    uuid: string;
    name: string;
    company: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    notes: string | null;
};

/**
 * The mockup "Register New Supplier / Vendor" modal body: sunken title bar, two-column fields,
 * Cancel / Create actions. `dialog` renders inside a `<Dialog layout="panel">`; `page` is the same
 * panel as a card on the /app/vendors/create and /edit routes.
 */
export function VendorForm({
    vendor,
    variant,
    onCancel,
}: {
    vendor: VendorFormValue | null;
    variant: 'dialog' | 'page';
    onCancel?: () => void;
}) {
    const { t } = useT();
    const { submit, processing, errors } = useFormSubmit();
    const [f, setF] = useState({
        name: vendor?.name ?? '',
        company: vendor?.company ?? '',
        phone: vendor?.phone ?? '',
        email: vendor?.email ?? '',
        address: vendor?.address ?? '',
        notes: vendor?.notes ?? '',
    });
    const set = (key: keyof typeof f, value: string) =>
        setF((p) => ({ ...p, [key]: value }));
    const save = () =>
        submit(
            vendor ? 'put' : 'post',
            vendor ? `/app/vendors/${vendor.uuid}` : '/app/vendors',
            f,
        );
    const title = vendor ? t('vendors.edit_title') : t('vendors.new_title');
    const subtitle = t('vendors.new_subtitle');

    useHotkeys('form', [
        {
            id: 'vendor.save',
            keys: 'mod+s',
            labelKey: 'hotkeys.save',
            allowInInput: true,
            handler: (e) => {
                e.preventDefault();
                if (!processing) save();
            },
        },
    ]);

    const fields = (
        <>
            {errors['form'] ? (
                <p
                    role="alert"
                    className="rounded-md bg-danger-soft p-3 text-sm text-destructive"
                >
                    {errors['form']}
                </p>
            ) : null}
            <div className="grid gap-3 sm:grid-cols-2">
                <FormField label={t('vendors.f_name')} error={errors['name']}>
                    <Input
                        autoFocus
                        value={f.name}
                        placeholder={t('vendors.f_name_ph')}
                        onChange={(e) => set('name', e.target.value)}
                    />
                </FormField>
                <FormField
                    label={t('vendors.col_company')}
                    error={errors['company']}
                >
                    <Input
                        value={f.company}
                        onChange={(e) => set('company', e.target.value)}
                    />
                </FormField>
                <FormField label={t('vendors.f_phone')} error={errors['phone']}>
                    <Input
                        className="num font-mono"
                        inputMode="tel"
                        value={f.phone}
                        placeholder="01711-223344"
                        onChange={(e) => set('phone', e.target.value)}
                    />
                </FormField>
                <FormField label={t('vendors.email')} error={errors['email']}>
                    <Input
                        type="email"
                        value={f.email}
                        onChange={(e) => set('email', e.target.value)}
                    />
                </FormField>
                <FormField
                    label={t('vendors.f_address')}
                    error={errors['address']}
                >
                    <Input
                        value={f.address}
                        placeholder={t('vendors.f_address_ph')}
                        onChange={(e) => set('address', e.target.value)}
                    />
                </FormField>
                <FormField label={t('vendors.notes')} error={errors['notes']}>
                    <Input
                        value={f.notes}
                        onChange={(e) => set('notes', e.target.value)}
                    />
                </FormField>
            </div>
        </>
    );

    const cancel =
        variant === 'dialog' ? (
            <Button type="button" variant="outline" onClick={onCancel}>
                {t('common.cancel')}
            </Button>
        ) : (
            <Button asChild variant="outline">
                <Link
                    href={
                        vendor ? `/app/vendors/${vendor.uuid}` : '/app/vendors'
                    }
                >
                    {t('common.cancel')}
                </Link>
            </Button>
        );
    const submitButton = (
        <Button type="submit" disabled={processing}>
            {vendor ? t('common.save') : t('vendors.create_profile')}
        </Button>
    );

    return (
        <form
            noValidate
            className={cn(
                'flex min-h-0 flex-col',
                variant === 'page' &&
                    'max-w-2xl overflow-hidden rounded-xl border bg-surface shadow-xs',
            )}
            onSubmit={(e) => {
                e.preventDefault();
                save();
            }}
        >
            {variant === 'dialog' ? (
                <DialogBar
                    title={title}
                    subtitle={subtitle}
                    icon={Truck}
                    closeLabel={t('common.close')}
                />
            ) : (
                <div className="flex items-center gap-2 border-b bg-surface-sunken p-3.5">
                    <div className="flex size-6 items-center justify-center rounded-lg bg-primary-soft text-primary">
                        <Truck className="size-3.5" aria-hidden="true" />
                    </div>
                    <div>
                        <h1 className="text-sm font-bold text-foreground">
                            {title}
                        </h1>
                        <p className="mt-0.5 text-[11px] text-muted-foreground">
                            {subtitle}
                        </p>
                    </div>
                </div>
            )}
            {variant === 'dialog' ? (
                <DialogBody>{fields}</DialogBody>
            ) : (
                <div className="space-y-3 p-4">{fields}</div>
            )}
            {variant === 'dialog' ? (
                <DialogActions>
                    {cancel}
                    {submitButton}
                </DialogActions>
            ) : (
                <div className="flex justify-end gap-2 border-t p-3">
                    {cancel}
                    {submitButton}
                </div>
            )}
        </form>
    );
}
