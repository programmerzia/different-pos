/** Thin fetch wrapper for the session JSON API (/app/api/*): CSRF header, JSON in/out. */
function xsrfToken(): string {
    if (typeof document === "undefined") {
        return "";
    }
    const match = /(?:^|;\s*)XSRF-TOKEN=([^;]+)/.exec(document.cookie);

    return match?.[1] ? decodeURIComponent(match[1]) : "";
}

export class ApiError extends Error {
    override name = "ApiError";

    constructor(
        public readonly status: number,
        message: string,
        /** The server's own `message` (a domain error, or the first validation error) when the body was JSON. */
        public readonly detail: string | null = null,
    ) {
        super(message);
    }
}

/** Pulls `{message}` or the first `{errors}` entry out of a JSON error body; null when there is none. */
async function errorDetail(response: Response): Promise<string | null> {
    try {
        const data = (await response.json()) as {
            message?: unknown;
            errors?: Record<string, unknown>;
        };
        const first = data.errors ? Object.values(data.errors)[0] : undefined;
        if (Array.isArray(first) && typeof first[0] === "string") {
            return first[0];
        }

        return typeof data.message === "string" && data.message !== ""
            ? data.message
            : null;
    } catch {
        return null;
    }
}

export async function apiRequest<T>(
    url: string,
    init: { method?: string; body?: unknown; signal?: AbortSignal } = {},
): Promise<T> {
    const headers: Record<string, string> = {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": xsrfToken(),
    };
    if (init.body !== undefined) {
        headers["Content-Type"] = "application/json";
    }

    const response = await fetch(url, {
        method: init.method ?? "GET",
        headers,
        credentials: "same-origin",
        ...(init.body !== undefined ? { body: JSON.stringify(init.body) } : {}),
        ...(init.signal ? { signal: init.signal } : {}),
    });

    if (!response.ok) {
        throw new ApiError(
            response.status,
            response.statusText,
            await errorDetail(response),
        );
    }

    return (await response.json()) as T;
}
