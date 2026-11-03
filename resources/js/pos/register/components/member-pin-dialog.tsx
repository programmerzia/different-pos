import { KeyRound, ShieldCheck, UserX } from 'lucide-react';
import { useState } from 'react';
import { PinPad } from '@/components/pin-pad';
import { Button } from '@/components/ui/button';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { setMemberPin, verifyMemberPin } from '../api';
import type { MemberStep } from '../member-auth';
import type { CustomerCard } from '../types';
import { PosDialog } from './pos-dialog';

type Mode = 'verify' | 'offer' | 'set_first' | 'set_second' | 'locked';

type Props = {
    /** null = closed. */
    step: MemberStep | null;
    customer: CustomerCard | null;
    clientUuid: string;
    /** The cashier may ask a manager (ManagerDialog, pos.customer_pin_override). */
    onOverride: () => void;
    /** Optional policy: continue as a member without a PIN check. */
    onSkip: () => void;
    /** Drop the customer: the sale goes on as a walk-in. */
    onWalkIn: () => void;
    onVerified: (token: string, expiresIn: number) => void;
    /** The member's first PIN was saved; the card's pin_status is now `set`. */
    onPinSet: () => void;
    onClose: () => void;
};

const modeFor = (step: MemberStep): Mode =>
    step.kind === 'verify'
        ? 'verify'
        : step.kind === 'locked'
          ? 'locked'
          : 'offer';

/**
 * The member PIN step after F4 (02_AppFlow §4.4, online): the dialog is turned to the customer, who
 * types on the pad or the keyboard; digits show as dots only. A member without a PIN is offered to
 * set one (twice). Locked or forgotten → manager override (or walk-in / skip when the policy allows).
 */
export function MemberPinDialog({ step, customer, onClose, ...rest }: Props) {
    const { t } = useT();
    const open = step !== null && customer !== null;

    return (
        <PosDialog
            icon={KeyRound}
            open={open}
            onClose={onClose}
            title={t('pos.pin.title')}
            description={
                customer ? t('pos.pin.customer', { name: customer.name }) : undefined
            }
            className="sm:max-w-sm"
        >
            {open && (
                <MemberPinBody
                    key={`${customer.id}-${step.kind}`}
                    step={step}
                    customer={customer}
                    onClose={onClose}
                    {...rest}
                />
            )}
        </PosDialog>
    );
}

function MemberPinBody({
    step,
    customer,
    clientUuid,
    onOverride,
    onSkip,
    onWalkIn,
    onVerified,
    onPinSet,
}: Omit<Props, 'step' | 'customer'> & {
    step: MemberStep;
    customer: CustomerCard;
}) {
    const { t } = useT();
    const d = useDigits();
    const [mode, setMode] = useState<Mode>(() => modeFor(step));
    const [first, setFirst] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [entry, setEntry] = useState(0);

    const skippable =
        (step.kind === 'offer_set' || step.kind === 'locked') && step.skippable;

    const verify = async (pin: string) => {
        setBusy(true);
        setError(null);
        const result = await verifyMemberPin(customer.uuid, pin, clientUuid);
        setBusy(false);
        setEntry((n) => n + 1);
        if (result.ok) {
            setSuccess(t('pos.pin.verified'));
            onVerified(result.data.token ?? '', result.data.expires_in ?? 600);

            return;
        }
        if (result.error.status === 0) {
            setError(t('pos.errors.network'));

            return;
        }
        if (result.error.status === 429) {
            setError(t('pos.pin.too_many'));

            return;
        }
        const body = result.error.body ?? {};
        const status = body['status'];
        const left = body['attempts_left'];
        if (status === 'locked') {
            setMode('locked');
            setError(t('pos.pin.locked'));
        } else if (status === 'not_set') {
            setMode('offer');
        } else if (status === 'wrong') {
            setError(
                t('pos.pin.wrong', {
                    n: d.n(typeof left === 'number' ? left : 0),
                }),
            );
        } else {
            setError(result.error.message || t('pos.errors.generic'));
        }
    };

    const setPin = async (pin: string) => {
        setError(null);
        if (first === null) {
            setFirst(pin);
            setMode('set_second');
            setEntry((n) => n + 1);

            return;
        }
        if (pin !== first) {
            setFirst(null);
            setMode('set_first');
            setEntry((n) => n + 1);
            setError(t('pos.pin.mismatch'));

            return;
        }
        setBusy(true);
        const result = await setMemberPin(customer.uuid, pin, first);
        setBusy(false);
        setFirst(null);
        setEntry((n) => n + 1);
        if (result.ok) {
            setSuccess(t('pos.pin.set_done'));
            onPinSet();
            setMode('verify');

            return;
        }
        setMode('set_first');
        setError(
            result.error.status === 0
                ? t('pos.errors.network')
                : result.error.message || t('pos.pin.set_failed'),
        );
    };

    const escapeHatches = (
        <div className="flex flex-wrap justify-end gap-2 border-t pt-3">
            {skippable && (
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-11"
                    onClick={onSkip}
                >
                    {t('pos.pin.skip')}
                </Button>
            )}
            <Button
                type="button"
                variant="outline"
                className="min-h-11"
                onClick={onWalkIn}
            >
                <UserX aria-hidden="true" /> {t('pos.pin.walk_in')}
            </Button>
            <Button
                type="button"
                variant="outline"
                className="min-h-11"
                onClick={onOverride}
            >
                <ShieldCheck aria-hidden="true" /> {t('pos.pin.override')}
            </Button>
        </div>
    );

    if (mode === 'offer') {
        return (
            <div className="space-y-3">
                <p className="text-sm">{t('pos.pin.offer')}</p>
                <Button
                    type="button"
                    className="h-12 w-full text-base font-bold"
                    autoFocus
                    onClick={() => {
                        setMode('set_first');
                        setEntry((n) => n + 1);
                    }}
                >
                    <KeyRound aria-hidden="true" /> {t('pos.pin.set_now')}
                </Button>
                {escapeHatches}
            </div>
        );
    }

    if (mode === 'locked') {
        return (
            <div className="space-y-3">
                <p className="text-sm font-semibold text-danger" role="alert">
                    {t('pos.pin.locked')}
                </p>
                <p className="text-xs text-muted-foreground">
                    {t('pos.pin.locked_hint')}
                </p>
                {escapeHatches}
            </div>
        );
    }

    const setting = mode === 'set_first' || mode === 'set_second';

    return (
        <div className="space-y-3">
            <p className="text-center text-base font-semibold">
                {mode === 'set_first'
                    ? t('pos.pin.choose')
                    : mode === 'set_second'
                      ? t('pos.pin.repeat')
                      : t('pos.pin.enter')}
            </p>
            <PinPad
                key={`${mode}-${entry}`}
                variant="customer"
                busy={busy}
                error={error}
                success={success}
                confirmLabel={setting ? t('pos.pin.save') : t('pos.pin.verify')}
                onSubmit={setting ? setPin : verify}
            />
            {!setting && (
                <p className="text-center text-[11px] text-muted-foreground">
                    {t('pos.pin.forgot')}
                </p>
            )}
            {escapeHatches}
        </div>
    );
}
