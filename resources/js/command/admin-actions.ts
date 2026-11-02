import { Ban, Eye, UserPlus } from 'lucide-react';
import { actions } from './actions';

let registered = false;

/** Staff row actions and the "new user" palette command. Idempotent; wired in defaults.ts. */
export function registerAdminActions(): void {
    if (registered) {
        return;
    }
    registered = true;
    actions.register(
        {
            id: 'user.deactivate',
            entity: 'user',
            labelKey: 'users.deactivate',
            icon: Ban,
            permission: 'users.manage',
            group: 'danger',
            requires: 'handler',
            visible: (row: { is_active?: boolean }) => row.is_active === true,
            run: ({ row, handlers }) => handlers?.['user.deactivate']?.(row),
        },
        {
            id: 'audit.details',
            entity: 'audit',
            labelKey: 'audit.details',
            icon: Eye,
            permission: 'audit.view',
            requires: 'handler',
            run: ({ row, handlers }) => handlers?.['audit.details']?.(row),
        },
        {
            id: 'cmd.new_user',
            entity: null,
            labelKey: 'users.new',
            icon: UserPlus,
            permission: 'users.manage',
            href: '/app/settings/users/create',
        },
    );
}
