import { Form, Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/i18n';
import { TranslatedAuthLayout } from '@/layouts/auth/translated-auth-layout';

export default function ChangePassword({
    passwordRules,
}: {
    passwordRules: string;
}) {
    const { t } = useT();

    return (
        <>
            <Head title={t('password_change.title')} />
            <Form
                action="/app/password/change"
                method="put"
                resetOnSuccess
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                {t('password_change.new')}
                            </Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoFocus
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                            />
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                {t('password_change.confirm')}
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>
                        <Button
                            type="submit"
                            className="min-h-11 w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {t('password_change.submit')}
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

ChangePassword.layout = (page: ReactNode) => (
    <TranslatedAuthLayout
        titleKey="password_change.heading"
        descriptionKey="password_change.description"
    >
        {page}
    </TranslatedAuthLayout>
);
