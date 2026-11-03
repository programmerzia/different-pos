import { Head, Link } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { Save } from 'lucide-react';
import { useState } from 'react';
import { FormField } from '@/components/form-field';
import { FormSection } from '@/components/form-section';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormSubmit } from '@/hooks/use-form-submit';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';

type Vendor = {
    id: number;
    uuid: string;
    name: string;
    company: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    notes: string | null;
};

export default function VendorForm({ vendor }: { vendor: Vendor | null }) {
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

    return (
        <>
            <Head title={vendor ? t('vendors.edit') : t('vendors.new')} />
            <form
                className="flex max-w-4xl flex-col gap-6 p-page"
                noValidate
                onSubmit={(e) => {
                    e.preventDefault();
                    save();
                }}
            >
                <PageHeader
                    title={vendor ? t('vendors.edit') : t('vendors.new')}
                />
                {errors['form'] ? (
                    <p
                        role="alert"
                        className="rounded-md bg-danger-soft p-3 text-sm text-destructive"
                    >
                        {errors['form']}
                    </p>
                ) : null}
                <FormSection title={t('vendors.details')}>
                    <FormField label={t('common.name')} error={errors['name']}>
                        <Input
                            className="min-h-11"
                            autoFocus
                            value={f.name}
                            onChange={(e) => set('name', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        label={t('vendors.col_company')}
                        error={errors['company']}
                    >
                        <Input
                            className="min-h-11"
                            value={f.company}
                            onChange={(e) => set('company', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        label={t('vendors.col_phone')}
                        error={errors['phone']}
                    >
                        <Input
                            className="num min-h-11"
                            inputMode="tel"
                            value={f.phone}
                            onChange={(e) => set('phone', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        label={t('vendors.email')}
                        error={errors['email']}
                    >
                        <Input
                            className="min-h-11"
                            type="email"
                            value={f.email}
                            onChange={(e) => set('email', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        label={t('vendors.address')}
                        error={errors['address']}
                        className="lg:col-span-2"
                    >
                        <Input
                            className="min-h-11"
                            value={f.address}
                            onChange={(e) => set('address', e.target.value)}
                        />
                    </FormField>
                    <FormField
                        label={t('vendors.notes')}
                        error={errors['notes']}
                        className="lg:col-span-2"
                    >
                        <Input
                            className="min-h-11"
                            value={f.notes}
                            onChange={(e) => set('notes', e.target.value)}
                        />
                    </FormField>
                </FormSection>
                <div className="flex gap-2">
                    <Button
                        type="submit"
                        className="min-h-11"
                        disabled={processing}
                    >
                        <Save aria-hidden="true" /> {t('common.save')}
                    </Button>
                    <Button asChild variant="outline" className="min-h-11">
                        <Link
                            href={
                                vendor
                                    ? `/app/vendors/${vendor.uuid}`
                                    : '/app/vendors'
                            }
                        >
                            {t('common.cancel')}
                        </Link>
                    </Button>
                </div>
            </form>
        </>
    );
}
