import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { KeyRound } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    label?: string;
    loadingLabel?: string;
    separator?: string;
    /** Draw the "or" separator above the button (the passkey offered after the password form). */
    separatorFirst?: boolean;
};

export default function PasskeyVerify({
    routes,
    label,
    loadingLabel,
    separator,
    separatorFirst = false,
}: Props = {}) {
    const { verify, isLoading, error, isSupported } = usePasskeyVerify({
        ...(routes && {
            routes: {
                options: routes.options.url,
                submit: routes.submit.url,
            },
        }),
        onSuccess: (response) => {
            router.visit(response.redirect ?? '/dashboard');
        },
    });

    if (!isSupported) {
        return null;
    }

    const button = (
        <div className="grid gap-2">
            <Button
                type="button"
                variant="outline"
                className="min-h-11 w-full rounded-lg md:h-9 md:min-h-9 md:text-xs"
                onClick={verify}
                disabled={isLoading}
            >
                {isLoading ? <Spinner /> : <KeyRound className="h-4 w-4" />}
                {isLoading
                    ? (loadingLabel ?? 'Authenticating...')
                    : (label ?? 'Sign in with a passkey')}
            </Button>
            {error && <InputError message={error} className="text-center" />}
        </div>
    );
    const divider = (
        <div className="relative">
            <div className="absolute inset-0 flex items-center">
                <Separator className="w-full" />
            </div>
            <div className="relative flex justify-center text-xs uppercase">
                <span className="bg-card px-2 text-muted-foreground">
                    {separator ?? 'Or continue with email'}
                </span>
            </div>
        </div>
    );

    return (
        <>
            {separatorFirst ? divider : button}
            {separatorFirst ? button : divider}
        </>
    );
}
