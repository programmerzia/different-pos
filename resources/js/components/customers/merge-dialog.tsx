import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    CustomerPicker,
    type PickedCustomer,
} from '@/components/customers/customer-picker';
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

/** Merge a duplicate customer into the one the user picks. The survivor keeps its number. */
export function MergeDialog({
    customer,
    onClose,
}: {
    customer: { uuid: string; name: string } | null;
    onClose: () => void;
}) {
    const { t } = useT();
    const [survivor, setSurvivor] = useState<PickedCustomer | null>(null);
    const [busy, setBusy] = useState(false);
    useHotkeyScope('dialog', customer !== null);

    const close = () => {
        setSurvivor(null);
        onClose();
    };

    const submit = () => {
        if (!customer || !survivor) {
            return;
        }
        router.post(
            `/app/customers/${customer.uuid}/merge`,
            { into: survivor.uuid },
            {
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onSuccess: close,
            },
        );
    };

    return (
        <Dialog open={customer !== null} onOpenChange={(o) => !o && close()}>
            <DialogContent className="max-w-140">
                <DialogHeader>
                    <DialogTitle>{t('customers.merge.title')}</DialogTitle>
                    <DialogDescription>
                        {t('customers.merge.body', { name: customer?.name })}
                    </DialogDescription>
                </DialogHeader>
                {survivor ? (
                    <p className="rounded-md border bg-muted p-3 text-sm">
                        {t('customers.merge.into', { name: survivor.name })}
                    </p>
                ) : null}
                <CustomerPicker
                    autoFocus
                    excludeUuid={customer?.uuid}
                    onPick={setSurvivor}
                />
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11"
                        onClick={close}
                    >
                        {t('confirm.cancel')}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        className="min-h-11"
                        disabled={!survivor || busy}
                        onClick={submit}
                    >
                        {t('customers.merge.confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
