import { router } from '@inertiajs/react';
import { useState } from 'react';

type Method = 'post' | 'put' | 'patch' | 'delete';
type Errors = Record<string, string>;

/**
 * A plain submit helper for pages that build their own payload (nested lines, files):
 * tracks `processing` and the server's validation `errors`, and clears them on the next try.
 * A PUT/PATCH carrying a file is sent as POST with `_method`, which Laravel honours.
 */
export function useFormSubmit() {
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Errors>({});

    const submit = (
        method: Method,
        url: string,
        data: Record<string, unknown> = {},
        options: { files?: boolean; onSuccess?: () => void } = {},
    ) => {
        const files = options.files ?? false;
        const spoof = files && (method === 'put' || method === 'patch');
        const payload = spoof ? { ...data, _method: method } : data;
        const visit = spoof ? 'post' : method;

        router.visit(url, {
            method: visit,
            data: payload as never,
            forceFormData: files,
            preserveScroll: true,
            onStart: () => {
                setProcessing(true);
                setErrors({});
            },
            onError: (bag) => setErrors(bag as Errors),
            onSuccess: () => options.onSuccess?.(),
            onFinish: () => setProcessing(false),
        });
    };

    return { submit, processing, errors, clearError: (key: string) => setErrors((e) => ({ ...e, [key]: '' })) };
}
