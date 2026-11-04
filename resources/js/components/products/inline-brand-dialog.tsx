import { useState, type FormEvent, type KeyboardEvent } from 'react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useHotkeyScope } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { ApiError, apiRequest } from '@/lib/api';

export type CreatedBrand = { id: number; name: string };

/**
 * "+" beside the Brand select of the product form: creates a brand over JSON (`brands.store`) without
 * leaving, or losing, the product form. A duplicate name comes back as a field error.
 */
export function InlineBrandDialog({
    open,
    onOpenChange,
    onCreated,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCreated: (brand: CreatedBrand) => void;
}) {
    const { t } = useT();
    useHotkeyScope('dialog', open);
    const [name, setName] = useState('');
    const [error, setError] = useState<string | undefined>();
    const [busy, setBusy] = useState(false);

    const reset = () => {
        setName('');
        setError(undefined);
    };

    const submit = async (event?: FormEvent) => {
        event?.preventDefault();
        event?.stopPropagation();
        if (busy) {
            return;
        }
        if (name.trim() === '') {
            setError(t('products.err_brand_name'));

            return;
        }
        setBusy(true);
        setError(undefined);
        try {
            const brand = await apiRequest<CreatedBrand>(
                '/app/products/brands',
                { method: 'POST', body: { name: name.trim() } },
            );
            onCreated(brand);
            reset();
            onOpenChange(false);
        } catch (e) {
            setError(
                e instanceof ApiError && e.detail
                    ? e.detail
                    : t('products.brand_failed'),
            );
        } finally {
            setBusy(false);
        }
    };

    // Ctrl+Enter saves this small dialog; the product form's own Ctrl+Enter must not also fire.
    const onKeyDown = (event: KeyboardEvent) => {
        if (event.key === 'Enter') {
            event.nativeEvent.stopPropagation();
            if (event.ctrlKey || event.metaKey) {
                event.preventDefault();
                void submit();
            }
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    reset();
                }
                onOpenChange(next);
            }}
        >
            <DialogContent className="max-w-100" onKeyDown={onKeyDown}>
                <form noValidate onSubmit={(e) => void submit(e)}>
                    <DialogHeader>
                        <DialogTitle>
                            {t('products.brand_new_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('products.brand_new_hint')}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="my-4">
                        <FormField
                            label={t('products.brand_name')}
                            error={error}
                        >
                            <Input
                                className="min-h-11"
                                value={name}
                                autoComplete="off"
                                maxLength={120}
                                autoFocus
                                onChange={(e) => {
                                    setName(e.target.value);
                                    setError(undefined);
                                }}
                            />
                        </FormField>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11"
                            onClick={() => onOpenChange(false)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            className="min-h-11"
                            disabled={busy}
                        >
                            {t('products.brand_add')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
