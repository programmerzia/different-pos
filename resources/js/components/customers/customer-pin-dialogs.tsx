import { router } from '@inertiajs/react';
import { KeyRound, LockOpen } from 'lucide-react';
import { useState } from 'react';
import { PinPad } from '@/components/pin-pad';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogActions,
    DialogBar,
    DialogBody,
    DialogContent,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/i18n';

type Target = { uuid: string; name: string };

/** Posts the PIN (twice) and nothing else; the server validates, hashes and never echoes it. */
function postPin(
    url: string,
    body: Record<string, string>,
    onDone: () => void,
    onError: (message: string) => void,
    setBusy: (busy: boolean) => void,
) {
    setBusy(true);
    router.post(url, body, {
        preserveScroll: true,
        onSuccess: onDone,
        onError: (errors) => {
            const first = Object.values(errors)[0];
            onError(typeof first === 'string' ? first : '');
        },
        onFinish: () => setBusy(false),
    });
}

/**
 * Twice-entry pad for a customer's PIN (02_AppFlow §5 customer form, Membership → Set PIN). Turned
 * to the customer: large dots, no digits; the first entry is kept in memory only until the second.
 */
function TwiceEntry({
    onSubmit,
    busy,
    serverError,
}: {
    onSubmit: (pin: string, confirmation: string) => void;
    busy: boolean;
    serverError: string | null;
}) {
    const { t } = useT();
    const [first, setFirst] = useState<string | null>(null);
    const [attempt, setAttempt] = useState(0);
    const [error, setError] = useState<string | null>(null);

    return (
        <div className="space-y-3">
            <p className="text-center text-base font-semibold">
                {first === null
                    ? t('customers.pin.choose')
                    : t('customers.pin.repeat')}
            </p>
            <PinPad
                key={`${first === null ? 'a' : 'b'}-${attempt}`}
                variant="customer"
                busy={busy}
                error={error ?? serverError}
                confirmLabel={t('customers.pin.save')}
                onSubmit={(pin) => {
                    setError(null);
                    if (first === null) {
                        setFirst(pin);
                        setAttempt((n) => n + 1);

                        return;
                    }
                    if (pin !== first) {
                        setFirst(null);
                        setAttempt((n) => n + 1);
                        setError(t('customers.pin.mismatch'));

                        return;
                    }
                    setFirst(null);
                    setAttempt((n) => n + 1);
                    onSubmit(pin, first);
                }}
            />
        </div>
    );
}

export function SetCustomerPinDialog({
    customer,
    onClose,
}: {
    customer: Target;
    onClose: () => void;
}) {
    const { t } = useT();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    return (
        <Dialog open onOpenChange={(open) => (open ? undefined : onClose())}>
            <DialogContent layout="panel" size="sm" aria-describedby={undefined}>
                <DialogBar
                    icon={KeyRound}
                    title={t('customers.pin.set_title', { name: customer.name })}
                    subtitle={t('customers.pin.set_hint')}
                    closeLabel={t('admin.cancel')}
                />
                <DialogBody>
                    <TwiceEntry
                        busy={busy}
                        serverError={error}
                        onSubmit={(pin, confirmation) => {
                            setError(null);
                            postPin(
                                `/app/customers/${customer.uuid}/pin`,
                                { pin, pin_confirmation: confirmation },
                                onClose,
                                setError,
                                setBusy,
                            );
                        }}
                    />
                </DialogBody>
            </DialogContent>
        </Dialog>
    );
}

/** Reason required (customers.reset_pin, audited); optionally the customer chooses a new PIN now. */
export function ResetCustomerPinDialog({
    customer,
    onClose,
}: {
    customer: Target;
    onClose: () => void;
}) {
    const { t } = useT();
    const [reason, setReason] = useState('');
    const [chooseNow, setChooseNow] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const url = `/app/customers/${customer.uuid}/pin/reset`;
    const reasonOk = reason.trim().length >= 3;

    return (
        <Dialog open onOpenChange={(open) => (open ? undefined : onClose())}>
            <DialogContent layout="panel" size="sm" aria-describedby={undefined}>
                <DialogBar
                    icon={KeyRound}
                    title={t('customers.pin.reset_title', { name: customer.name })}
                    subtitle={t('customers.pin.reset_hint')}
                    closeLabel={t('admin.cancel')}
                />
                <form
                    className="flex min-h-0 flex-1 flex-col"
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (!reasonOk || chooseNow) {
                            return;
                        }
                        postPin(url, { reason }, onClose, setError, setBusy);
                    }}
                >
                    <DialogBody className="space-y-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="customer-pin-reason">
                                {t('customers.pin.reason')} *
                            </Label>
                            <Input
                                id="customer-pin-reason"
                                autoFocus
                                autoComplete="off"
                                maxLength={200}
                                placeholder={t('customers.pin.reason_placeholder')}
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                            />
                        </div>
                        <label className="flex items-center gap-2 text-xs font-semibold">
                            <input
                                type="checkbox"
                                className="size-4 rounded"
                                checked={chooseNow}
                                onChange={(e) => setChooseNow(e.target.checked)}
                            />
                            {t('customers.pin.new_pin_now')}
                        </label>
                        {chooseNow && reasonOk && (
                            <TwiceEntry
                                busy={busy}
                                serverError={error}
                                onSubmit={(pin, confirmation) => {
                                    setError(null);
                                    postPin(
                                        url,
                                        { reason, pin, pin_confirmation: confirmation },
                                        onClose,
                                        setError,
                                        setBusy,
                                    );
                                }}
                            />
                        )}
                        {error && !chooseNow && (
                            <p role="alert" className="text-xs font-semibold text-danger">
                                {error}
                            </p>
                        )}
                    </DialogBody>
                    {!chooseNow && (
                        <DialogActions>
                            <Button
                                type="button"
                                variant="outline"
                                className="flex-1"
                                onClick={onClose}
                            >
                                {t('admin.cancel')}
                            </Button>
                            <Button
                                type="submit"
                                className="flex-1 font-bold"
                                disabled={busy || !reasonOk}
                            >
                                {t('customers.pin.reset_action')}
                            </Button>
                        </DialogActions>
                    )}
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function UnlockCustomerPinDialog({
    customer,
    onClose,
}: {
    customer: Target;
    onClose: () => void;
}) {
    const { t } = useT();
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const reasonOk = reason.trim().length >= 3;

    return (
        <Dialog open onOpenChange={(open) => (open ? undefined : onClose())}>
            <DialogContent layout="panel" size="sm" aria-describedby={undefined}>
                <DialogBar
                    icon={LockOpen}
                    title={t('customers.pin.unlock_title', { name: customer.name })}
                    subtitle={t('customers.pin.unlock_hint')}
                    closeLabel={t('admin.cancel')}
                />
                <form
                    className="flex min-h-0 flex-1 flex-col"
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (!reasonOk) {
                            return;
                        }
                        postPin(
                            `/app/customers/${customer.uuid}/pin/unlock`,
                            { reason },
                            onClose,
                            setError,
                            setBusy,
                        );
                    }}
                >
                    <DialogBody className="space-y-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="customer-pin-unlock-reason">
                                {t('customers.pin.reason')} *
                            </Label>
                            <Input
                                id="customer-pin-unlock-reason"
                                autoFocus
                                autoComplete="off"
                                maxLength={200}
                                placeholder={t('customers.pin.reason_placeholder')}
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                            />
                        </div>
                        {error && (
                            <p role="alert" className="text-xs font-semibold text-danger">
                                {error}
                            </p>
                        )}
                    </DialogBody>
                    <DialogActions>
                        <Button
                            type="button"
                            variant="outline"
                            className="flex-1"
                            onClick={onClose}
                        >
                            {t('admin.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            className="flex-1 font-bold"
                            disabled={busy || !reasonOk}
                        >
                            {t('customers.pin.unlock_action')}
                        </Button>
                    </DialogActions>
                </form>
            </DialogContent>
        </Dialog>
    );
}
