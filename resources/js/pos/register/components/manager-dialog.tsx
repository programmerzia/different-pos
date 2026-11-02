import { ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/i18n';
import { requestApproval } from '../api';
import type { Approval } from '../types';
import { PosDialog } from './pos-dialog';

type Props = {
    /** The permission asked for; null = closed. */
    permission: string | null;
    onApproved: (approval: Approval) => void;
    onClose: () => void;
};

/**
 * A manager types their login and password (or staff PIN) at the counter. The server answers with a
 * short-lived token that rides along with the sale; the manager's rights then count for this bill.
 */
export function ManagerDialog({ permission, onApproved, onClose }: Props) {
    const { t } = useT();

    return (
        <PosDialog
            open={permission !== null}
            onClose={onClose}
            title={t('pos.manager.title')}
            description={
                permission
                    ? t('pos.manager.hint', {
                          what: t(
                              `pos.manager.permission.${permission.replace('pos.', '')}`,
                          ),
                      })
                    : undefined
            }
            className="sm:max-w-md"
        >
            {permission !== null && (
                <ManagerBody
                    permission={permission}
                    onApproved={onApproved}
                    onClose={onClose}
                />
            )}
        </PosDialog>
    );
}

function ManagerBody({
    permission,
    onApproved,
    onClose,
}: Props & { permission: string }) {
    const { t } = useT();
    const [login, setLogin] = useState('');
    const [secret, setSecret] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const submit = async () => {
        if (busy) {
            return;
        }
        setBusy(true);
        setError(null);
        const result = await requestApproval(login, secret, permission);
        setBusy(false);
        setSecret('');
        if (!result.ok) {
            setError(result.error.message || t('pos.errors.network'));

            return;
        }
        onApproved({
            token: result.data.token,
            managerName: result.data.manager.name,
            capBp: result.data.discount_cap_bp,
            expiresAt: Date.now() + result.data.expires_in * 1000,
        });
    };

    return (
        <form
            className="space-y-3"
            onSubmit={(e) => {
                e.preventDefault();
                void submit();
            }}
        >
            <div className="space-y-1">
                <Label htmlFor="pos-manager-login">
                    {t('pos.manager.login')}
                </Label>
                <Input
                    id="pos-manager-login"
                    autoFocus
                    autoComplete="off"
                    className="h-11"
                    value={login}
                    onChange={(e) => setLogin(e.target.value)}
                />
            </div>
            <div className="space-y-1">
                <Label htmlFor="pos-manager-secret">
                    {t('pos.manager.secret')}
                </Label>
                <Input
                    id="pos-manager-secret"
                    type="password"
                    autoComplete="off"
                    className="h-11"
                    value={secret}
                    onChange={(e) => setSecret(e.target.value)}
                />
            </div>
            {error && (
                <p className="text-sm text-danger" role="alert">
                    {error}
                </p>
            )}
            <div className="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-11"
                    onClick={onClose}
                >
                    {t('confirm.cancel')}
                </Button>
                <Button
                    type="submit"
                    className="min-h-11"
                    disabled={busy || login === '' || secret === ''}
                >
                    <ShieldCheck aria-hidden="true" />{' '}
                    {t('pos.manager.approve')}
                </Button>
            </div>
        </form>
    );
}
