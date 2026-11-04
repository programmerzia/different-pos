import { Ban, Eye, Star, Truck, UserPlus } from 'lucide-react';
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
            id: 'courier.default',
            entity: 'courier',
            labelKey: 'couriers.set_default',
            icon: Star,
            permission: 'settings.manage',
            requires: 'handler',
            visible: (row: { is_default?: boolean; is_active?: boolean }) =>
                row.is_default !== true && row.is_active === true,
            run: ({ row, handlers }) => handlers?.['courier.default']?.(row),
        },
        {
            id: 'cmd.new_courier',
            entity: null,
            labelKey: 'couriers.new',
            icon: Truck,
            permission: 'settings.manage',
            href: '/app/settings/couriers?new=1',
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
