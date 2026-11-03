import AuthBrandLayout from '@/layouts/auth/auth-brand-layout';

export default function AuthLayout({
    title = '',
    description = '',
    titleSrOnly = false,
    children,
}: {
    title?: string;
    description?: string;
    /** The login card shows only the brand header (as in the mockup); its title stays for screen readers. */
    titleSrOnly?: boolean;
    children: React.ReactNode;
}) {
    return (
        <AuthBrandLayout
            title={title}
            description={description}
            titleSrOnly={titleSrOnly}
        >
            {children}
        </AuthBrandLayout>
    );
}
