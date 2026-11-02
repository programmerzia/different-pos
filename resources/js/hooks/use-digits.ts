import { toBanglaDigits, toLatinDigits } from '@/engine/digits';
import { formatMoney, type Paisa } from '@/engine/money';
import { formatQty, type QtyMilli } from '@/engine/quantity';
import { useT } from '@/i18n';
import { usePreferencesStore } from '@/lib/preferences';

export type Digits = {
    /** True when Bangla digits are in effect (locale bn and the bn_digits option on). */
    bn: boolean;
    /** Any number/text containing 0-9 → displayed digits. */
    n: (value: number | string) => string;
    money: (paisa: Paisa, symbol?: boolean) => string;
    qty: (milli: QtyMilli) => string;
    /** Normalises typed input (either digit set) back to 0-9. */
    latin: (value: string) => string;
};

/** Pure builder (tested without React). */
export function makeDigits(bn: boolean): Digits {
    const locale = bn ? 'bn' : 'en';

    return {
        bn,
        n: (value) => (bn ? toBanglaDigits(String(value)) : String(value)),
        money: (paisa, symbol = true) => formatMoney(paisa, locale, symbol),
        qty: (milli) => formatQty(milli, locale),
        latin: toLatinDigits,
    };
}

/** Number formatting that follows the language switch and the Bangla-digits preference. */
export function useDigits(): Digits {
    const { locale } = useT();
    const bnDigits = usePreferencesStore((s) => s.bn_digits);

    return makeDigits(locale === 'bn' && bnDigits);
}
