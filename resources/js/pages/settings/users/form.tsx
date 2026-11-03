import { PageHeader } from '@/components/page-header';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { CheckField } from '@/components/admin/check-field';
import { FormField } from '@/components/form-field';
import { FormSection } from '@/components/form-section';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';

type Role = { id: number; name: string; is_system: boolean };
type Branch = { id: number; name: string; code: string };
type UserData = {
    id: number;
    name: string;
    email: string | null;
    username: string | null;
    phone: string | null;
    is_active: boolean;
    must_change_password: boolean;
    role_ids: number[];
    branch_ids: number[];
};

type FormData = {
    name: string;
    email: string;
    username: string;
    phone: string;
    password: string;
    is_active: boolean;
    must_change_password: boolean;
    role_ids: number[];
    branch_ids: number[];
};

const toggle = (list: number[], id: number) =>
    list.includes(id) ? list.filter((x) => x !== id) : [...list, id];

export default function UserForm({
    user,
    roles,
    branches,
}: {
    user: UserData | null;
    roles: Role[];
    branches: Branch[];
}) {
    const { t } = useT();
    const form = useForm<FormData>({
        name: user?.name ?? '',
        email: user?.email ?? '',
        username: user?.username ?? '',
        phone: user?.phone ?? '',
        password: '',
        is_active: user?.is_active ?? true,
        must_change_password: user?.must_change_password ?? false,
        role_ids: user?.role_ids ?? [],
        branch_ids: user?.branch_ids ?? [],
    });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event?: FormEvent) => {
        event?.preventDefault();
        if (user) {
            form.put(`/app/settings/users/${user.id}`, {
                preserveScroll: true,
            });
        } else {
            form.post('/app/settings/users', { preserveScroll: true });
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

    const title = user ? t('users.edit') : t('users.new');

    return (
        <>
            <Head title={title} />
            <form onSubmit={submit} className="flex flex-col gap-8 p-page">
                <PageHeader title={title} />
                {errors['form'] ? (
                    <p
                        role="alert"
                        className="max-w-180 rounded-md bg-danger-soft px-3 py-2 text-sm text-destructive"
                    >
                        {errors['form']}
                    </p>
                ) : null}

                <FormSection
                    title={t('users.section_person')}
                    description={t('users.login_hint')}
                >
                    <FormField label={t('admin.name')} error={errors['name']}>
                        <Input
                            value={form.data.name}
                            required
                            autoFocus
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                    </FormField>
                    <FormField label={t('admin.email')} error={errors['email']}>
                        <Input
                            type="email"
                            value={form.data.email}
                            autoComplete="off"
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        label={t('users.username')}
                        error={errors['username']}
                    >
                        <Input
                            value={form.data.username}
                            autoComplete="off"
                            onChange={(e) =>
                                form.setData('username', e.target.value)
                            }
                        />
                    </FormField>
                    <FormField label={t('admin.phone')} error={errors['phone']}>
                        <Input
                            type="tel"
                            value={form.data.phone}
                            autoComplete="off"
                            onChange={(e) =>
                                form.setData('phone', e.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        label={
                            user ? t('users.new_password') : t('users.password')
                        }
                        hint={user ? t('users.password_keep') : undefined}
                        error={errors['password']}
                    >
                        <Input
                            type="password"
                            value={form.data.password}
                            autoComplete="new-password"
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                        />
                    </FormField>
                    <div className="grid content-end">
                        <CheckField
                            label={t('users.must_change')}
                            checked={form.data.must_change_password}
                            onChange={(v) =>
                                form.setData('must_change_password', v)
                            }
                        />
                        {user ? (
                            <CheckField
                                label={t('admin.active')}
                                checked={form.data.is_active}
                                onChange={(v) => form.setData('is_active', v)}
                            />
                        ) : null}
                    </div>
                </FormSection>

                <FormSection
                    title={t('users.roles')}
                    description={errors['role_ids'] ?? t('users.roles_hint')}
                >
                    <fieldset className="grid gap-1 lg:col-span-2">
                        <legend className="sr-only">{t('users.roles')}</legend>
                        {roles.map((role) => (
                            <CheckField
                                key={role.id}
                                label={role.name}
                                checked={form.data.role_ids.includes(role.id)}
                                onChange={() =>
                                    form.setData(
                                        'role_ids',
                                        toggle(form.data.role_ids, role.id),
                                    )
                                }
                            />
                        ))}
                    </fieldset>
                </FormSection>

                <FormSection
                    title={t('users.branches')}
                    description={
                        errors['branch_ids'] ?? t('users.branches_hint')
                    }
                >
                    <fieldset className="grid gap-1 lg:col-span-2">
                        <legend className="sr-only">
                            {t('users.branches')}
                        </legend>
                        {branches.map((branch) => (
                            <CheckField
                                key={branch.id}
                                label={`${branch.name} (${branch.code})`}
                                checked={form.data.branch_ids.includes(
                                    branch.id,
                                )}
                                onChange={() =>
                                    form.setData(
                                        'branch_ids',
                                        toggle(form.data.branch_ids, branch.id),
                                    )
                                }
                            />
                        ))}
                    </fieldset>
                </FormSection>

                <div className="flex max-w-180 justify-end gap-2">
                    <Button asChild variant="outline" className="min-h-11">
                        <Link href="/app/settings/users">
                            {t('admin.cancel')}
                        </Link>
                    </Button>
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
        </>
    );
}
