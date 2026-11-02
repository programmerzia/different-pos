import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/i18n';

export type FormApi<T> = {
    data: T;
    set: <K extends keyof T>(key: K, value: T[K]) => void;
    error: (key: string) => string | undefined;
};

type LooseForm = {
    data: Record<string, unknown>;
    setData: (key: string, value: unknown) => void;
    errors: Record<string, string>;
    processing: boolean;
    post: (url: string, options?: object) => void;
    put: (url: string, options?: object) => void;
};

type Props<T extends Record<string, unknown>> = {
    title: string;
    description?: string;
    initial: T;
    /** `[method, url]` of the save request. */
    request: ['post' | 'put', string];
    onClose: () => void;
    children: (form: FormApi<T>) => ReactNode;
};

/** A small create/edit form in a dialog: Enter saves, Esc cancels, a business-rule error shows above the fields. */
export function RecordDialog<T extends Record<string, unknown>>({
    title,
    description,
    initial,
    request,
    onClose,
    children,
}: Props<T>) {
    const { t } = useT();
    const form = useForm(initial as never) as unknown as LooseForm;
    const errors: Record<string, string | undefined> = form.errors;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const [method, url] = request;
        form[method](url, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open onOpenChange={(open) => (open ? undefined : onClose())}>
            <DialogContent className="max-h-[90vh] max-w-140 overflow-y-auto">
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {description ? (
                            <DialogDescription>{description}</DialogDescription>
                        ) : (
                            <DialogDescription className="sr-only">
                                {title}
                            </DialogDescription>
                        )}
                    </DialogHeader>
                    {errors['form'] ? (
                        <p
                            role="alert"
                            className="rounded-md bg-danger-soft px-3 py-2 text-sm text-destructive"
                        >
                            {errors['form']}
                        </p>
                    ) : null}
                    <div className="grid gap-4">
                        {children({
                            data: form.data as T,
                            set: (key, value) =>
                                form.setData(key as string, value),
                            error: (key) => errors[key],
                        })}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11"
                            onClick={onClose}
                        >
                            {t('admin.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            className="min-h-11"
                            disabled={form.processing}
                        >
                            {form.processing ? <Spinner /> : null}
                            {t('admin.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
