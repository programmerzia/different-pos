import { Head, useForm } from '@inertiajs/react';
import { Page, PageHeader } from '@/components/page-header';
import type { FormEvent } from 'react';
import { BusinessLogo } from '@/components/admin/business-logo';
import { CheckField } from '@/components/admin/check-field';
import { FormField } from '@/components/form-field';
import { FormSection } from '@/components/form-section';
import { MoneyInput } from '@/components/money-input';
import { NativeSelect } from '@/components/native-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';

type Business = {
    name: string;
    slug: string;
    legal_name: string | null;
    vat_bin: string | null;
    vat_registered_address: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    currency: string;
    timezone: string;
    locale: string;
    tax_inclusive: boolean;
    backdate_days: number;
    return_days: number | null;
    default_credit_limit: number | null;
    customer_pin_policy: string;
    offline_credit_cap: number;
    logo_url: string | null;
};

export default function BusinessSettings({
    business,
    timezones,
}: {
    business: Business;
    timezones: string[];
}) {
    const { t } = useT();
    const form = useForm({
        name: business.name,
        legal_name: business.legal_name ?? '',
        vat_bin: business.vat_bin ?? '',
        vat_registered_address: business.vat_registered_address ?? '',
        phone: business.phone ?? '',
        email: business.email ?? '',
        address: business.address ?? '',
        currency: business.currency,
        timezone: business.timezone,
        locale: business.locale,
        tax_inclusive: business.tax_inclusive,
        backdate_days: business.backdate_days,
        return_days: business.return_days as number | null,
        default_credit_limit: business.default_credit_limit as number | null,
        customer_pin_policy: business.customer_pin_policy,
        offline_credit_cap: business.offline_credit_cap,
    });
    const errors = form.errors as Record<string, string | undefined>;
    const text = (
        key:
            | 'name'
            | 'legal_name'
            | 'vat_bin'
            | 'vat_registered_address'
            | 'phone'
            | 'email'
            | 'address'
            | 'currency',
    ) => form.data[key];

    const submit = (event?: FormEvent) => {
        event?.preventDefault();
        form.put('/app/settings/business', { preserveScroll: true });
    };

    useHotkeys('form', [
        {
            id: 'form.save',
            keys: 'mod+s',
            labelKey: 'hotkeys.save',
            allowInInput: true,
            handler: (event) => {
                event.preventDefault();
                submit();
            },
        },
    ]);

    const field = (
        key:
            | 'name'
            | 'legal_name'
            | 'vat_bin'
            | 'vat_registered_address'
            | 'phone'
            | 'email'
            | 'address'
            | 'currency',
        label: string,
        extra: React.ComponentProps<typeof Input> = {},
    ) => (
        <FormField label={label} error={errors[key]}>
            <Input
                value={text(key)}
                onChange={(e) => form.setData(key, e.target.value)}
                {...extra}
            />
        </FormField>
    );

    return (
        <>
            <Head title={t('business.title')} />
            <Page className="gap-8">
                <PageHeader
                    title={t('business.title')}
                    subtitle={
                        <>
                            {t('business.code_is')}{' '}
                            <span className="font-mono">{business.slug}</span>
                        </>
                    }
                />

                {/* Its own form (multipart), outside the settings form: nested forms are invalid HTML. */}
                <BusinessLogo
                    name={business.name}
                    logoUrl={business.logo_url}
                />

                <form onSubmit={submit} className="flex flex-col gap-8">
                    <FormSection title={t('business.section_profile')}>
                        {field('name', t('business.name'), {
                            required: true,
                            autoFocus: true,
                        })}
                        {field('legal_name', t('business.legal_name'))}
                        {field('phone', t('admin.phone'), { type: 'tel' })}
                        {field('email', t('admin.email'), { type: 'email' })}
                        {field('address', t('admin.address'))}
                        {field('vat_bin', t('business.vat_bin'))}
                        {field(
                            'vat_registered_address',
                            t('business.vat_address'),
                        )}
                    </FormSection>

                    <FormSection title={t('business.section_regional')}>
                        <FormField
                            label={t('business.locale')}
                            error={errors['locale']}
                        >
                            <NativeSelect
                                value={form.data.locale}
                                onChange={(e) =>
                                    form.setData('locale', e.target.value)
                                }
                            >
                                <option value="en">English</option>
                                <option value="bn">বাংলা</option>
                            </NativeSelect>
                        </FormField>
                        <FormField
                            label={t('business.timezone')}
                            error={errors['timezone']}
                        >
                            <NativeSelect
                                value={form.data.timezone}
                                onChange={(e) =>
                                    form.setData('timezone', e.target.value)
                                }
                            >
                                {timezones.map((tz) => (
                                    <option key={tz} value={tz}>
                                        {tz}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        {field('currency', t('business.currency'), {
                            maxLength: 3,
                            className: 'uppercase',
                        })}
                    </FormSection>

                    <FormSection title={t('business.section_sales')}>
                        <div className="lg:col-span-2">
                            <CheckField
                                label={t('business.tax_inclusive')}
                                checked={form.data.tax_inclusive}
                                onChange={(v) =>
                                    form.setData('tax_inclusive', v)
                                }
                            />
                        </div>
                        <FormField
                            label={t('business.backdate_days')}
                            hint={t('business.backdate_hint')}
                            error={errors['backdate_days']}
                        >
                            <Input
                                type="number"
                                min={0}
                                max={365}
                                className="num"
                                value={form.data.backdate_days}
                                onChange={(e) =>
                                    form.setData(
                                        'backdate_days',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label={t('business.return_days')}
                            hint={t('business.return_hint')}
                            error={errors['return_days']}
                        >
                            <Input
                                type="number"
                                min={0}
                                className="num"
                                value={form.data.return_days ?? ''}
                                onChange={(e) =>
                                    form.setData(
                                        'return_days',
                                        e.target.value === ''
                                            ? null
                                            : Number(e.target.value),
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label={t('business.credit_limit')}
                            hint={t('business.credit_limit_hint')}
                            error={errors['default_credit_limit']}
                        >
                            <MoneyInput
                                value={form.data.default_credit_limit}
                                onChange={(v) =>
                                    form.setData('default_credit_limit', v)
                                }
                            />
                        </FormField>
                        <FormField
                            label={t('business.offline_credit_cap')}
                            hint={t('business.offline_credit_hint')}
                            error={errors['offline_credit_cap']}
                        >
                            <MoneyInput
                                value={form.data.offline_credit_cap}
                                onChange={(v) =>
                                    form.setData('offline_credit_cap', v ?? 0)
                                }
                            />
                        </FormField>
                        <FormField
                            label={t('business.pin_policy')}
                            error={errors['customer_pin_policy']}
                        >
                            <NativeSelect
                                value={form.data.customer_pin_policy}
                                onChange={(e) =>
                                    form.setData(
                                        'customer_pin_policy',
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="always">
                                    {t('business.pin_always')}
                                </option>
                                <option value="credit_only">
                                    {t('business.pin_credit_only')}
                                </option>
                                <option value="off">
                                    {t('business.pin_off')}
                                </option>
                            </NativeSelect>
                        </FormField>
                    </FormSection>

                    <div className="flex max-w-180 justify-end">
                        <Button
                            type="submit"
                            className="min-h-11"
                            disabled={form.processing}
                        >
                            {form.processing ? <Spinner /> : null}
                            {t('admin.save')}
                        </Button>
                    </div>
                </form>
            </Page>
        </>
    );
}
