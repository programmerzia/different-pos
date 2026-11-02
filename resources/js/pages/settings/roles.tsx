import { router } from '@inertiajs/react';
import { Save, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AdminPage } from '@/components/admin/admin-page';
import { RecordDialog } from '@/components/admin/record-dialog';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';

type Role = {
    id: number;
    name: string;
    is_system: boolean;
    max_discount_pct_bp: number;
    permissions: string[];
    users: number;
};

type Draft = { permissions: string[]; max_discount_pct_bp: number };

const sameSet = (a: string[], b: string[]) =>
    a.length === b.length && a.every((p) => b.includes(p));

/** Basis points <-> a percent text with up to two decimals ("12.5"). */
const toPercent = (bp: number) => String(bp / 100);
const fromPercent = (text: string) =>
    Math.min(
        10000,
        Math.max(0, Math.round(Number.parseFloat(text || '0') * 100) || 0),
    );

export default function Roles({
    permissions,
    roles,
}: {
    permissions: string[];
    roles: Role[];
}) {
    const { t } = useT();
    const digits = useDigits();
    const [drafts, setDrafts] = useState<Record<number, Draft>>({});
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<Role | null>(null);

    const groups = useMemo(() => {
        const map = new Map<string, string[]>();
        for (const name of permissions) {
            const group = name.split('.')[0] ?? name;
            map.set(group, [...(map.get(group) ?? []), name]);
        }

        return [...map.entries()];
    }, [permissions]);

    const draftOf = (role: Role): Draft =>
        drafts[role.id] ?? {
            permissions: role.permissions,
            max_discount_pct_bp: role.max_discount_pct_bp,
        };
    const dirty = (role: Role) => {
        const d = drafts[role.id];

        return (
            d !== undefined &&
            (!sameSet(d.permissions, role.permissions) ||
                d.max_discount_pct_bp !== role.max_discount_pct_bp)
        );
    };
    // The Owner always holds every permission.
    const locked = (role: Role) => role.is_system && role.name === 'Owner';

    const toggle = (role: Role, permission: string) => {
        const current = draftOf(role);
        const next = current.permissions.includes(permission)
            ? current.permissions.filter((p) => p !== permission)
            : [...current.permissions, permission];
        setDrafts({ ...drafts, [role.id]: { ...current, permissions: next } });
    };

    const save = (role: Role) => {
        const d = draftOf(role);
        router.put(
            `/app/settings/roles/${role.id}`,
            {
                permissions: d.permissions,
                max_discount_pct_bp: d.max_discount_pct_bp,
            },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setDrafts(({ [role.id]: _gone, ...rest }) => rest),
            },
        );
    };

    return (
        <AdminPage
            title={t('roles.title')}
            newLabel={t('roles.new')}
            onNew={() => setCreating(true)}
        >
            <p className="text-sm text-muted-foreground">{t('roles.hint')}</p>
            <div className="overflow-x-auto rounded-lg border border-border">
                <table className="w-full text-sm">
                    <caption className="sr-only">{t('roles.title')}</caption>
                    <thead className="bg-muted">
                        <tr>
                            <th
                                scope="col"
                                className="sticky left-0 bg-muted px-3 py-2 text-left font-medium"
                            >
                                {t('roles.permission')}
                            </th>
                            {roles.map((role) => (
                                <th
                                    key={role.id}
                                    scope="col"
                                    className="min-w-32 px-3 py-2 text-center align-bottom font-medium"
                                >
                                    <div>{role.name}</div>
                                    <div className="text-xs font-normal text-muted-foreground">
                                        {t('roles.users_count', {
                                            n: digits.n(role.users),
                                        })}
                                    </div>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        <tr className="border-t border-border">
                            <th
                                scope="row"
                                className="sticky left-0 bg-background px-3 py-2 text-left font-medium"
                            >
                                {t('roles.max_discount')}
                            </th>
                            {roles.map((role) => (
                                <td
                                    key={role.id}
                                    className="px-3 py-2 text-center"
                                >
                                    <Input
                                        type="text"
                                        inputMode="decimal"
                                        aria-label={`${role.name}: ${t('roles.max_discount')}`}
                                        className="num mx-auto w-20 text-right"
                                        value={toPercent(
                                            draftOf(role).max_discount_pct_bp,
                                        )}
                                        onChange={(e) =>
                                            setDrafts({
                                                ...drafts,
                                                [role.id]: {
                                                    ...draftOf(role),
                                                    max_discount_pct_bp:
                                                        fromPercent(
                                                            e.target.value,
                                                        ),
                                                },
                                            })
                                        }
                                    />
                                </td>
                            ))}
                        </tr>
                        {groups.map(([group, names]) => (
                            <FragmentRows
                                key={group}
                                group={group}
                                names={names}
                                roles={roles}
                                draftOf={draftOf}
                                locked={locked}
                                toggle={toggle}
                            />
                        ))}
                        <tr className="border-t border-border">
                            <th
                                scope="row"
                                className="sticky left-0 bg-background px-3 py-2"
                            >
                                <span className="sr-only">
                                    {t('admin.actions')}
                                </span>
                            </th>
                            {roles.map((role) => (
                                <td
                                    key={role.id}
                                    className="px-3 py-2 text-center"
                                >
                                    <div className="flex justify-center gap-1">
                                        <Button
                                            type="button"
                                            size="sm"
                                            className="min-h-11 md:min-h-8"
                                            disabled={!dirty(role)}
                                            onClick={() => save(role)}
                                        >
                                            <Save aria-hidden="true" />
                                            {t('admin.save')}
                                        </Button>
                                        {!role.is_system ? (
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                className="size-11 md:size-8"
                                                aria-label={t('roles.delete')}
                                                onClick={() =>
                                                    setDeleting(role)
                                                }
                                            >
                                                <Trash2 aria-hidden="true" />
                                            </Button>
                                        ) : null}
                                    </div>
                                </td>
                            ))}
                        </tr>
                    </tbody>
                </table>
            </div>

            {creating ? (
                <RecordDialog
                    title={t('roles.new')}
                    initial={{
                        name: '',
                        permissions: [] as string[],
                        max_discount_pct_bp: 0,
                    }}
                    request={['post', '/app/settings/roles']}
                    onClose={() => setCreating(false)}
                >
                    {(f) => (
                        <FormField
                            label={t('admin.name')}
                            error={f.error('name')}
                        >
                            <Input
                                value={f.data.name}
                                required
                                autoFocus
                                onChange={(e) => f.set('name', e.target.value)}
                            />
                        </FormField>
                    )}
                </RecordDialog>
            ) : null}
            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => (open ? undefined : setDeleting(null))}
                title={t('roles.delete')}
                description={t('roles.delete_body')}
                recordName={deleting?.name ?? ''}
                destructive
                onConfirm={() =>
                    deleting
                        ? router.delete(`/app/settings/roles/${deleting.id}`, {
                              preserveScroll: true,
                          })
                        : undefined
                }
            />
        </AdminPage>
    );
}

function FragmentRows({
    group,
    names,
    roles,
    draftOf,
    locked,
    toggle,
}: {
    group: string;
    names: string[];
    roles: Role[];
    draftOf: (role: Role) => Draft;
    locked: (role: Role) => boolean;
    toggle: (role: Role, permission: string) => void;
}) {
    const { t } = useT();

    return (
        <>
            <tr className="border-t border-border bg-muted/50">
                <th
                    scope="colgroup"
                    colSpan={roles.length + 1}
                    className="px-3 py-1.5 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {t(`roles.group.${group}`)}
                </th>
            </tr>
            {names.map((name) => (
                <tr key={name} className="border-t border-border">
                    <th
                        scope="row"
                        className="sticky left-0 bg-background px-3 py-1.5 text-left font-normal"
                    >
                        <span className="font-mono text-xs">{name}</span>
                    </th>
                    {roles.map((role) => (
                        <td key={role.id} className="px-3 py-1.5 text-center">
                            <Checkbox
                                aria-label={`${role.name}: ${name}`}
                                checked={
                                    locked(role) ||
                                    draftOf(role).permissions.includes(name)
                                }
                                disabled={locked(role)}
                                onCheckedChange={() => toggle(role, name)}
                                className="size-5 md:size-4"
                            />
                        </td>
                    ))}
                </tr>
            ))}
        </>
    );
}
