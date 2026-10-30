import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login } from '@/routes';

export default function Welcome() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col items-center justify-center gap-6 bg-background p-6 text-foreground">
                <h1 className="text-2xl font-semibold">{name}</h1>
                {auth.user ? (
                    <Link
                        href={dashboard()}
                        className="inline-block rounded-md border border-border px-5 py-2 text-sm hover:bg-accent"
                    >
                        Dashboard
                    </Link>
                ) : (
                    <Link
                        href={login()}
                        className="inline-block rounded-md border border-border px-5 py-2 text-sm hover:bg-accent"
                    >
                        Log in
                    </Link>
                )}
            </div>
        </>
    );
}
