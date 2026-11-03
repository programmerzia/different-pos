import { create } from 'zustand';
import { updateAppearance as applyAppearance } from '@/hooks/use-appearance';
import { setLanguage } from '@/i18n';
import { apiRequest } from '@/lib/api';
import { preferences as preferencesRoute } from '@/routes/api';

export const SCHEMES = [
    'teal',
    'indigo',
    'violet',
    'rose',
    'ocean',
    'graphite',
] as const;
export type Scheme = (typeof SCHEMES)[number];
export type Density = 'comfortable' | 'compact';
export type Theme = 'light' | 'dark' | 'system';
export type Locale = 'en' | 'bn';

export type Preferences = {
    locale: Locale;
    theme: Theme;
    scheme: Scheme;
    density: Density;
    bn_digits: boolean;
};

export const DEFAULT_PREFERENCES: Preferences = {
    locale: 'en',
    theme: 'system',
    scheme: 'teal',
    density: 'comfortable',
    bn_digits: true,
};

type Store = Preferences & {
    hydrate: (server: Partial<Preferences>) => void;
    set: (patch: Partial<Preferences>) => void;
};

function setCookie(name: string, value: string): void {
    document.cookie = `${name}=${value};path=/;max-age=${365 * 24 * 60 * 60};SameSite=Lax`;
}

/** Applies a patch to the document (no flash: same attributes the root view renders). */
export function applyToDocument(patch: Partial<Preferences>): void {
    if (typeof document === 'undefined') {
        return;
    }
    const root = document.documentElement;
    if (patch.scheme) {
        root.dataset.scheme = patch.scheme;
        setCookie('scheme', patch.scheme);
    }
    if (patch.density) {
        root.dataset.density = patch.density;
        setCookie('density', patch.density);
    }
    if (patch.locale) {
        root.lang = patch.locale;
        // Read by SetLocale for guests (the sign-in pages); a signed-in user's saved preference wins.
        setCookie('locale', patch.locale);
        void setLanguage(patch.locale);
    }
    if (patch.theme) {
        applyAppearance(patch.theme);
    }
}

/** Hover/focus preview of a scheme; `previewScheme(current)` restores it. Not persisted. */
export function previewScheme(scheme: Scheme): void {
    document.documentElement.dataset.scheme = scheme;
}

/** Client mirror of users.preferences; the server copy is authoritative on the next page load. */
export const usePreferencesStore = create<Store>((set) => ({
    ...DEFAULT_PREFERENCES,
    hydrate: (server) => {
        const next = { ...DEFAULT_PREFERENCES, ...server };
        set(next);
        applyToDocument(next);
    },
    set: (patch) => {
        set(patch);
        applyToDocument(patch);
        // Fire and forget: the UI already changed; a failed save only means it reverts on reload.
        void apiRequest(preferencesRoute.url(), {
            method: 'PATCH',
            body: patch,
        }).catch(() => undefined);
    },
}));

export function usePreferences(): Preferences & Pick<Store, 'set'> {
    return usePreferencesStore();
}
