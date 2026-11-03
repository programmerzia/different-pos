import { useForm } from '@inertiajs/react';
import { AlertCircle, KeyRound } from 'lucide-react';
import type { FormEvent } from 'react';
import { pinDigits } from '@/components/admin/staff-dialog';
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
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

/** Client check before posting; the server validates again (4–6 digits). */
export const validPin = (pin: string) => /^\d{4,6}$/.test(pin);

/**
 * Mockup "Reset PIN: {name}" dialog: one masked 4–6 digit field, Enter updates. Clears any PIN lockout
 * (users.pin.reset, users.manage). The PIN never leaves this form except in the POST body.
 */
export function ResetPinDialog({
    user,
    onClose,
}: {
    user: { id: number; name: string };
    onClose: () => void;
}) {
    const { t } = useT();
    const form = useForm({ pin: '' });
    const error = form.errors.pin;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!validPin(form.data.pin)) {
            form.setError('pin', t('users.pin_invalid'));

            return;
        }
        form.post(`/app/settings/users/${user.id}/pin`, {
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => form.reset('pin'),
        });
    };

    return (
        <Dialog open onOpenChange={(open) => (open ? undefined : onClose())}>
            <DialogContent
                layout="panel"
                size="sm"
                aria-describedby={undefined}
            >
                <DialogBar
                    icon={KeyRound}
                    title={t('users.reset_pin_title', { name: user.name })}
                    closeLabel={t('admin.cancel')}
                />
                <form
                    onSubmit={submit}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <DialogBody>
                        <div className="grid gap-1.5">
                            <Label htmlFor="reset-pin">
                                {t('users.new_pin')} *
                            </Label>
                            <Input
                                id="reset-pin"
                                type="password"
                                inputMode="numeric"
                                autoComplete="off"
                                maxLength={6}
                                autoFocus
                                placeholder="••••"
                                aria-invalid={error ? true : undefined}
                                aria-describedby={
                                    error ? 'reset-pin-error' : 'reset-pin-hint'
                                }
                                className={cn(
                                    'text-center font-mono text-sm font-bold tracking-widest',
                                    error && 'bg-danger-soft',
                                )}
                                value={form.data.pin}
                                onChange={(e) => {
                                    form.setData(
                                        'pin',
                                        pinDigits(e.target.value),
                                    );
                                    if (error) {
                                        form.clearErrors('pin');
                                    }
                                }}
                            />
                            {error ? (
                                <p
                                    id="reset-pin-error"
                                    role="alert"
                                    className="flex items-center gap-1 text-[10px] font-semibold text-destructive"
                                >
                                    <AlertCircle
                                        className="size-3"
                                        aria-hidden="true"
                                    />
                                    {error}
                                </p>
                            ) : (
                                <p
                                    id="reset-pin-hint"
                                    className="text-[11px] text-muted-foreground"
                                >
                                    {t('users.reset_pin_hint')}
                                </p>
                            )}
                        </div>
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
                            disabled={form.processing}
                        >
                            {form.processing ? <Spinner /> : null}
                            {t('users.update_pin')}
                        </Button>
                    </DialogActions>
                </form>
            </DialogContent>
        </Dialog>
    );
}
