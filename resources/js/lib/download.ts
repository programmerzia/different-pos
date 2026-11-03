function xsrfToken(): string {
    const match = /(?:^|;\s*)XSRF-TOKEN=([^;]+)/.exec(document.cookie);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

/**
 * The server's reason for refusing an export (a 422 such as "too many rows for a PDF"), or a plain
 * status message. `message` is safe to show in a toast.
 */
export class DownloadError extends Error {
    constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
    }
}

async function refusal(response: Response): Promise<string | null> {
    try {
        const body: unknown = await response.json();
        if (typeof body === 'object' && body !== null) {
            const errors = (body as { errors?: Record<string, unknown> })
                .errors;
            const first = errors ? Object.values(errors)[0] : undefined;
            if (Array.isArray(first) && typeof first[0] === 'string') {
                return first[0];
            }
            const message = (body as { message?: unknown }).message;
            if (typeof message === 'string' && message !== '') {
                return message;
            }
        }
    } catch {
        // not JSON
    }

    return null;
}

/**
 * Fetches a file-producing endpoint (CSV / XLSX / PDF exports) and saves the response under the
 * server's filename. Throws a DownloadError on a non-2xx answer, carrying the server's message
 * when it sent one.
 */
export async function downloadFile(
    url: string,
    { method = 'GET' }: { method?: 'GET' | 'POST' } = {},
): Promise<void> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json, */*',
            'X-XSRF-TOKEN': xsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    if (!response.ok) {
        throw new DownloadError(
            (await refusal(response)) ?? `Export failed (${response.status})`,
            response.status,
        );
    }
    const disposition = response.headers.get('Content-Disposition') ?? '';
    const name = /filename="?([^";]+)"?/.exec(disposition)?.[1] ?? 'download';
    const href = URL.createObjectURL(await response.blob());
    const link = document.createElement('a');
    link.href = href;
    link.download = name;
    link.click();
    URL.revokeObjectURL(href);
}
