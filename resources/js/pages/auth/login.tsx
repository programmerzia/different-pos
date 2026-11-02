import { Form, Head } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import PasskeyVerify from '@/components/passkey-verify';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/i18n';
import { TranslatedAuthLayout } from '@/layouts/auth/translated-auth-layout';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    statusKey?: string | null;
    canResetPassword: boolean;
    business: { code: string; name: string } | null;
};

export default function Login({
    status,
    statusKey,
    canResetPassword,
    business,
}: Props) {
    const { t } = useT();
    const [login, setLogin] = useState('');
    const [changing, setChanging] = useState(false);

    // The business code is only needed for a username or phone (an email is unique everywhere).
    const needsBusiness = login.trim() !== '' && !login.includes('@');
    const remembered = business !== null && !changing;

    return (
        <>
            <Head title={t('login.title')} />

            <PasskeyVerify />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="login">
                                {t('login.identifier')}
                            </Label>
                            <Input
                                id="login"
                                name="login"
                                required
                                autoFocus
                                autoComplete="username"
                                value={login}
                                onChange={(event) =>
                                    setLogin(event.target.value)
                                }
                                placeholder={t('login.identifier_placeholder')}
                                aria-invalid={errors.login ? true : undefined}
                                className="min-h-11 md:min-h-9"
                            />
                            <InputError message={errors.login} />
                        </div>

                        {needsBusiness ? (
                            remembered ? (
                                <div className="flex items-center justify-between gap-2 rounded-md bg-muted px-3 py-2 text-sm">
                                    <span>
                                        {t('login.business')}:{' '}
                                        <strong className="uppercase">
                                            {business.name}
                                        </strong>
                                    </span>
                                    <input
                                        type="hidden"
                                        name="business"
                                        value={business.code}
                                    />
                                    <Button
                                        type="button"
                                        variant="link"
                                        className="h-auto min-h-11 p-0 md:min-h-0"
                                        onClick={() => setChanging(true)}
                                    >
                                        {t('login.change_business')}
                                    </Button>
                                </div>
                            ) : (
                                <div className="grid gap-2">
                                    <Label htmlFor="business">
                                        {t('login.business_code')}
                                    </Label>
                                    <Input
                                        id="business"
                                        name="business"
                                        required
                                        autoComplete="organization"
                                        defaultValue={business?.code ?? ''}
                                        placeholder={t(
                                            'login.business_placeholder',
                                        )}
                                        className="min-h-11 md:min-h-9"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {t('login.business_hint')}
                                    </p>
                                </div>
                            )
                        ) : null}

                        <div className="grid gap-2">
                            <div className="flex items-center">
                                <Label htmlFor="password">
                                    {t('login.password')}
                                </Label>
                                {canResetPassword && (
                                    <TextLink
                                        href={request()}
                                        className="ml-auto text-sm"
                                    >
                                        {t('login.forgot')}
                                    </TextLink>
                                )}
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="current-password"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center space-x-3">
                            <Checkbox id="remember" name="remember" />
                            <Label htmlFor="remember">
                                {t('login.remember')}
                            </Label>
                        </div>

                        <Button
                            type="submit"
                            className="mt-2 min-h-11 w-full"
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            {t('login.submit')}
                        </Button>
                    </div>
                )}
            </Form>

            {statusKey ? (
                <div
                    role="alert"
                    className="text-center text-sm font-medium text-destructive"
                >
                    {t(statusKey)}
                </div>
            ) : null}
            {status && (
                <div className="mb-4 text-center text-sm font-medium text-success">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = (page: ReactNode) => (
    <TranslatedAuthLayout
        titleKey="login.heading"
        descriptionKey="login.description"
    >
        {page}
    </TranslatedAuthLayout>
);
