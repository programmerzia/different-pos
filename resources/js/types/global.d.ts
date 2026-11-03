import type { Preferences } from '@/lib/preferences';
import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            /** The current business inside a tenant context; null for guests and the platform console. */
            tenant: { name: string; logo_url: string | null } | null;
            preferences: Preferences;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
