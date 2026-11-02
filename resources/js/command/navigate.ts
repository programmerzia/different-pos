import { router } from '@inertiajs/react';

/** Inertia visit for in-app pages, a real navigation for non-Inertia ones (/pos), a new tab on request. */
export function navigateTo(
    url: string,
    options: { newTab?: boolean; external?: boolean } = {},
): void {
    if (options.newTab === true) {
        window.open(url, '_blank', 'noopener');

        return;
    }
    if (options.external === true || url === '/pos') {
        window.location.assign(url);

        return;
    }
    router.visit(url);
}
