import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { registerDefaultActions } from '@/command/defaults';
import { usePreferencesStore } from '@/lib/preferences';

/** Copies the server's `preferences` prop into the client store (and the document), once per change. */
export function PreferencesSync() {
    const server = usePage().props.preferences;
    const hydrate = usePreferencesStore((s) => s.hydrate);

    useEffect(() => {
        if (server) {
            hydrate(server);
        }
    }, [server, hydrate]);

    useEffect(() => {
        registerDefaultActions();
    }, []);

    return null;
}
