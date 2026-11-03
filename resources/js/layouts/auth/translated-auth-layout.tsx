import type { ReactNode } from 'react';
import { useT } from '@/i18n';
import AuthLayout from '@/layouts/auth-layout';

/** The auth card with a title and description looked up through i18n (static `layout` props cannot call hooks). */
export function TranslatedAuthLayout({
    titleKey,
    descriptionKey,
    titleSrOnly = false,
    children,
}: {
    titleKey: string;
    descriptionKey: string;
    titleSrOnly?: boolean;
    children: ReactNode;
}) {
    const { t } = useT();

    return (
        <AuthLayout
            title={t(titleKey)}
            description={t(descriptionKey)}
            titleSrOnly={titleSrOnly}
        >
            {children}
        </AuthLayout>
    );
}
