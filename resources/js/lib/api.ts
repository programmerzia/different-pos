/** Thin fetch wrapper for the session JSON API (/app/api/*): CSRF header, JSON in/out. */
function xsrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }
    const match = /(?:^|;\s*)XSRF-TOKEN=([^;]+)/.exec(document.cookie);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

export class ApiError extends Error {
    override name = 'ApiError';

    constructor(
        public readonly status: number,
        message: string,
        /** The server's own `message` (a domain error, or the first validation error) when the body was JSON. */
        public readonly detail: string | null = null,
        /** First message per field of a 422 validation body. */
        public readonly fieldErrors: Record<string, string> = {},
    ) {
        super(message);
    }
}

/** Pulls `{message}` or the first `{errors}` entry (and every field's first message) out of a JSON error body. */
async function errorDetail(
    response: Response,
): Promise<{ detail: string | null; fields: Record<string, string> }> {
    try {
        const data = (await response.json()) as {
            message?: unknown;
            errors?: Record<string, unknown>;
        };
        const fields: Record<string, string> = {};
        for (const [key, value] of Object.entries(data.errors ?? {})) {
            if (Array.isArray(value) && typeof value[0] === 'string') {
                fields[key] = value[0];
            }
        }
        const first = Object.values(fields)[0];
        if (first !== undefined) {
            return { detail: first, fields };
        }

        return {
            detail:
                typeof data.message === 'string' && data.message !== ''
                    ? data.message
                    : null,
            fields,
        };
    } catch {
        return { detail: null, fields: {} };
    }
}

export async function apiRequest<T>(
    url: string,
    init: { method?: string; body?: unknown; signal?: AbortSignal } = {},
): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrfToken(),
    };
    if (init.body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(url, {
        method: init.method ?? 'GET',
        headers,
        credentials: 'same-origin',
        ...(init.body !== undefined ? { body: JSON.stringify(init.body) } : {}),
        ...(init.signal ? { signal: init.signal } : {}),
    });

    if (!response.ok) {
        const { detail, fields } = await errorDetail(response);
        throw new ApiError(
            response.status,
            response.statusText,
            detail,
            fields,
        );
    }

    return (await response.json()) as T;
}
