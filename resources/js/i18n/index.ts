import i18n from 'i18next';
import { initReactI18next, useTranslation } from 'react-i18next';
import bn from './bn.json';
import en from './en.json';

export type Locale = 'en' | 'bn';

function initialLocale(): Locale {
    if (typeof document === 'undefined') {
        return 'en';
    }

    return document.documentElement.lang.startsWith('bn') ? 'bn' : 'en';
}

if (!i18n.isInitialized) {
    void i18n.use(initReactI18next).init({
        resources: { en: { translation: en }, bn: { translation: bn } },
        lng: initialLocale(),
        fallbackLng: 'en',
        interpolation: { escapeValue: false }, // React escapes
        returnNull: false,
    });
}

export function setLanguage(locale: Locale): Promise<unknown> {
    return i18n.changeLanguage(locale);
}

/** `useT()`: the app's only translation hook. Keys are namespaced: `nav.sales`, `table.empty`. */
export function useT() {
    const { t, i18n: instance } = useTranslation();

    return {
        t,
        locale: (instance.language.startsWith('bn') ? 'bn' : 'en') as Locale,
    };
}

export { i18n };
