import { router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
import { useHotkeyScope } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { salesUrl } from '@/lib/sales';

/** Same-day void with a required reason. The server re-checks the business day and returns the domain message. */
export function VoidSaleDialog({
    sale,
    onOpenChange,
}: {
    sale: { id: number; number: string } | null;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useT();
    const [reason, setReason] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    useHotkeyScope('dialog', sale !== null);

    const close = (open: boolean) => {
        if (!open && !busy) {
            setReason('');
            setError(null);
            onOpenChange(false);
        }
    };

    const submit = () => {
        if (!sale) {
            return;
        }
        // The list's filters and page ride along so the redirect lands back on the same view.
        router.post(
            `${salesUrl.void(sale.id)}${window.location.search}`,
            { reason },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onError: (errors) =>
                    setError(
                        errors['reason'] ?? Object.values(errors)[0] ?? null,
                    ),
                onSuccess: () => {
                    setReason('');
                    setError(null);
                    onOpenChange(false);
                },
            },
        );
    };

    return (
        <Dialog open={sale !== null} onOpenChange={close}>
            <DialogContent className="max-w-140">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        submit();
                    }}
                    className="grid gap-4"
                >
                    <DialogHeader>
                        <DialogTitle>
                            {t('sales.void_title', {
                                number: sale?.number ?? '',
                            })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('sales.void_hint')}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-1.5">
                        <Label htmlFor="void-reason">
                            {t('sales.void_reason')}
                        </Label>
                        <Input
                            id="void-reason"
                            value={reason}
                            autoFocus
                            maxLength={255}
                            aria-invalid={error !== null}
                            aria-describedby={
                                error ? 'void-reason-error' : undefined
                            }
                            onChange={(e) => setReason(e.target.value)}
                            className="min-h-11"
                        />
                        <InputError
                            id="void-reason-error"
                            message={error ?? undefined}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11"
                            disabled={busy}
                            onClick={() => close(false)}
                        >
                            {t('confirm.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            className="min-h-11"
                            disabled={busy || reason.trim().length < 3}
                        >
                            {t('sales.void_confirm', {
                                number: sale?.number ?? '',
                            })}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
