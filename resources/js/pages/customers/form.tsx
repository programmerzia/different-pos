import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import { MoneyInput } from '@/components/money-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { apiRequest } from '@/lib/api';
import type { Paisa } from '@/engine/money';
import type { CustomerDetail, GroupOption } from '@/types/customers';

type Existing = {
    uuid: string;
    name: string;
    shop_name: string | null;
    customer_no: string | null;
    phone: string | null;
};

type FormData = {
    name: string;
    name_bn: string;
    shop_name: string;
    phone: string;
    alt_phone: string;
    email: string;
    customer_group_id: string;
    credit_limit: Paisa | null;
    address_line: string;
    area: string;
    post_code: string;
    dob: string;
    anniversary_date: string;
    notes: string;
};

const NONE = '__none';

export default function CustomerForm({
    customer,
    groups,
}: {
    customer: CustomerDetail | null;
    groups: GroupOption[];
}) {
    const { t } = useT();
    const editing = customer !== null;
    const form = useForm<FormData>({
        name: customer?.name ?? '',
        name_bn: customer?.name_bn ?? '',
        shop_name: customer?.shop_name ?? '',
        phone: customer?.phone ?? '',
        alt_phone: customer?.alt_phone ?? '',
        email: customer?.email ?? '',
        customer_group_id:
            customer?.customer_group_id != null
                ? String(customer.customer_group_id)
                : '',
        credit_limit: customer?.credit_limit ?? null,
        address_line: customer?.address_line ?? '',
        area: customer?.area ?? '',
        post_code: customer?.post_code ?? '',
        dob: customer?.dob ?? '',
        anniversary_date: customer?.anniversary_date ?? '',
        notes: customer?.notes ?? '',
    });
    const [existing, setExisting] = useState<Existing | null>(null);

    const checkPhone = async (phone: string) => {
        if (phone.trim().length < 6) {
            setExisting(null);

            return;
        }
        try {
            const query = new URLSearchParams({ phone });
            if (customer) {
                query.set('except', customer.uuid);
            }
            const data = await apiRequest<{ existing: Existing | null }>(
                `/app/customers/check-phone?${query.toString()}`,
            );
            setExisting(data.existing);
        } catch {
            setExisting(null);
        }
    };

    const submit = (event?: React.FormEvent) => {
        event?.preventDefault();
        const options = {
            preserveScroll: true,
            onError: (errors: Record<string, string>) => {
                if (errors['phone']) {
                    void checkPhone(form.data.phone);
                }
            },
        };
        if (customer) {
            form.put(`/app/customers/${customer.uuid}`, options);
        } else {
            form.post('/app/customers', options);
        }
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

    const text = (
        key: keyof FormData,
        label: string,
        extra: React.ComponentProps<typeof Input> = {},
    ) => (
        <div className="grid gap-1.5">
            <Label htmlFor={key}>{label}</Label>
            <Input
                id={key}
                name={key}
                value={String(form.data[key] ?? '')}
                onChange={(e) => form.setData(key, e.target.value)}
                aria-invalid={form.errors[key] ? true : undefined}
                className="min-h-11 md:min-h-9"
                {...extra}
            />
            <InputError message={form.errors[key]} />
        </div>
    );

    return (
        <>
            <Head
                title={editing ? t('customers.edit') : t('customers.new')}
            />
            <form
                onSubmit={submit}
                className="flex flex-col gap-8 p-4 md:p-6"
                noValidate
            >
                <h1 className="text-2xl font-bold">
                    {editing ? t('customers.edit') : t('customers.new')}
                </h1>
                <InputError
                    message={(form.errors as Record<string, string>)['form']}
                />

                <FormSection title={t('customers.form.identity')}>
                    {text('name', t('customers.form.name'), {
                        required: true,
                        autoFocus: true,
                    })}
                    {text('name_bn', t('customers.form.name_bn'))}
                    {text('shop_name', t('customers.form.shop_name'))}
                    <div className="grid gap-1.5">
                        <Label htmlFor="phone">
                            {t('customers.form.phone')}
                        </Label>
                        <Input
                            id="phone"
                            name="phone"
                            inputMode="tel"
                            required
                            value={form.data.phone}
                            onChange={(e) => form.setData('phone', e.target.value)}
                            onBlur={(e) => void checkPhone(e.target.value)}
                            aria-invalid={form.errors.phone ? true : undefined}
                            aria-describedby={existing ? 'phone-duplicate' : undefined}
                            className="min-h-11 md:min-h-9"
                        />
                        <InputError message={form.errors.phone} />
                        {existing ? (
                            <p
                                id="phone-duplicate"
                                role="alert"
                                className="rounded-md border border-warning bg-warning-soft p-2 text-sm text-warning"
                            >
                                {t('customers.form.duplicate', {
                                    name: existing.name,
                                })}{' '}
                                <Link
                                    href={`/app/customers/${existing.uuid}`}
                                    className="font-medium underline"
                                >
                                    {t('customers.form.open_existing')}
                                </Link>
                            </p>
                        ) : null}
                    </div>
                    {text('alt_phone', t('customers.form.alt_phone'), {
                        inputMode: 'tel',
                    })}
                    {text('email', t('customers.form.email'), {
                        type: 'email',
                    })}
                </FormSection>

                <FormSection title={t('customers.form.account')}>
                    <div className="grid gap-1.5">
                        <Label htmlFor="customer_group_id">
                            {t('customers.form.group')}
                        </Label>
                        <Select
                            value={form.data.customer_group_id || NONE}
                            onValueChange={(v) =>
                                form.setData(
                                    'customer_group_id',
                                    v === NONE ? '' : v,
                                )
                            }
                        >
                            <SelectTrigger
                                id="customer_group_id"
                                className="min-h-11 w-full md:min-h-9"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NONE}>
                                    {t('customers.form.no_group')}
                                </SelectItem>
                                {groups.map((g) => (
                                    <SelectItem key={g.id} value={String(g.id)}>
                                        {g.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.customer_group_id} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="credit_limit">
                            {t('customers.form.credit_limit')}
                        </Label>
                        <MoneyInput
                            id="credit_limit"
                            name="credit_limit"
                            value={form.data.credit_limit}
                            onChange={(v) => form.setData('credit_limit', v)}
                            invalid={!!form.errors.credit_limit}
                        />
                        <InputError message={form.errors.credit_limit} />
                    </div>
                </FormSection>

                <FormSection title={t('customers.form.address')}>
                    {text('address_line', t('customers.form.address_line'))}
                    {text('area', t('customers.form.area'))}
                    {text('post_code', t('customers.form.post_code'), {
                        inputMode: 'numeric',
                        maxLength: 4,
                    })}
                </FormSection>

                <FormSection title={t('customers.form.dates')}>
                    {text('dob', t('customers.form.dob'), { type: 'date' })}
                    {text('anniversary_date', t('customers.form.anniversary'), {
                        type: 'date',
                    })}
                    <div className="grid gap-1.5 lg:col-span-2">
                        <Label htmlFor="notes">
                            {t('customers.form.notes')}
                        </Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows={3}
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                            className="border-input focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-base outline-none focus-visible:ring-[3px] md:text-sm"
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                </FormSection>

                <div className="flex gap-2">
                    <Button
                        type="submit"
                        disabled={form.processing}
                        className="min-h-11 md:min-h-9"
                    >
                        {t('customers.form.save')}
                    </Button>
                    <Button
                        asChild
                        type="button"
                        variant="outline"
                        className="min-h-11 md:min-h-9"
                    >
                        <Link
                            href={
                                customer
                                    ? `/app/customers/${customer.uuid}`
                                    : '/app/customers'
                            }
                        >
                            {t('confirm.cancel')}
                        </Link>
                    </Button>
                </div>
            </form>
        </>
    );
}
