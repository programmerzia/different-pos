import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useHotkeyScope } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';

/**
 * Confirmation for a consequential action. A destructive confirm repeats the record name in its
 * label; Esc cancels unless a destructive submit is in flight.
 */
export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    recordName,
    destructive = false,
    onConfirm,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    confirmLabel?: string;
    recordName?: string;
    destructive?: boolean;
    onConfirm: () => void | Promise<void>;
}) {
    const { t } = useT();
    const [busy, setBusy] = useState(false);
    useHotkeyScope('dialog', open);

    const label =
        destructive && recordName
            ? t('confirm.delete_named', { name: recordName })
            : (confirmLabel ?? t('confirm.ok'));

    const submit = async () => {
        setBusy(true);
        try {
            await onConfirm();
            onOpenChange(false);
        } finally {
            setBusy(false);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) =>
                busy && destructive ? undefined : onOpenChange(next)
            }
        >
            <DialogContent className="max-w-140">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description ? (
                        <DialogDescription>{description}</DialogDescription>
                    ) : null}
                </DialogHeader>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11"
                        disabled={busy}
                        onClick={() => onOpenChange(false)}
                    >
                        {t('confirm.cancel')}
                    </Button>
                    <Button
                        type="button"
                        variant={destructive ? 'destructive' : 'default'}
                        className="min-h-11"
                        disabled={busy}
                        onClick={() => void submit()}
                        autoFocus
                    >
                        {label}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
