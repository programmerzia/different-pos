import { useEffect, useState } from 'react';
import { ApiError, apiRequest } from '@/lib/api';
import type { ExchangeQuote } from '@/lib/exchange-settlement';

export type ExchangeQuoteRequest = {
    sale_id: number;
    lines: { sale_item_id: number; qty: number; restock: boolean }[];
    items: { product_id: number; qty: number }[];
};

type Answer = {
    key: string;
    quote: ExchangeQuote | null;
    error: string | null;
};

export type QuoteState = {
    /** True while the answer for the current lines and cart is outstanding. */
    loading: boolean;
    /** The exact figures for the current lines and cart; null until they arrive (never a stale answer). */
    quote: ExchangeQuote | null;
    /** The server's refusal for the current lines and cart ('' = it could not be reached). */
    error: string | null;
};

/**
 * Asks the server for the exact amounts of an exchange (debounced), whenever the request changes.
 * `request` is null while the exchange is incomplete. An answer only counts for the request it was
 * asked for, so a late reply to an older cart is dropped, never shown.
 */
export function useExchangeQuote(
    request: ExchangeQuoteRequest | null,
    delay = 250,
): QuoteState {
    const key = request ? JSON.stringify(request) : null;
    const [answer, setAnswer] = useState<Answer | null>(null);

    useEffect(() => {
        if (key === null) {
            return;
        }
        const controller = new AbortController();
        const timer = setTimeout(() => {
            apiRequest<ExchangeQuote>('/app/exchanges/quote', {
                method: 'POST',
                body: JSON.parse(key) as ExchangeQuoteRequest,
                signal: controller.signal,
            })
                .then((quote) => setAnswer({ key, quote, error: null }))
                .catch((e: unknown) => {
                    if (controller.signal.aborted) {
                        return;
                    }
                    const message =
                        e instanceof ApiError
                            ? (Object.values(e.fieldErrors)[0] ??
                              e.detail ??
                              '')
                            : '';
                    setAnswer({ key, quote: null, error: message });
                });
        }, delay);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [key, delay]);

    const current = key !== null && answer?.key === key ? answer : null;

    return {
        loading: key !== null && current === null,
        quote: current?.quote ?? null,
        error: current?.error ?? null,
    };
}
