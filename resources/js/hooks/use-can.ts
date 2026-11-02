import { usePage } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';

export type Can = (permission?: string | readonly string[] | null) => boolean;

/** Builds the checker from a permission list. `undefined`/empty requirement = any signed-in user. */
export function makeCan(permissions: readonly string[]): Can {
    const set = new Set(permissions);

    return (required) => {
        if (required === undefined || required === null) {
            return true;
        }
        const list = typeof required === 'string' ? [required] : required;

        return list.length === 0 || list.some((p) => set.has(p));
    };
}

/** `can('sales.void')`: the shared `auth.permissions` prop, for hiding (never disabling) UI. Server still authorizes. */
export function useCan(): Can {
    const permissions = usePage().props.auth.permissions;
    const list = useMemo(() => permissions ?? [], [permissions]);

    return useCallback((required) => makeCan(list)(required), [list]);
}
