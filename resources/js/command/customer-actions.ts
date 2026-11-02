import {
    Ban,
    BadgeDollarSign,
    CreditCard,
    FileText,
    GitMerge,
    ShoppingCart,
} from 'lucide-react';
import { actions, type AppAction } from './actions';
import type { CardRow, CustomerRow } from '@/types/customers';

/**
 * Customer and membership-card row actions: the same registry feeds the row context menu, row
 * buttons and the command palette. Generic ones (open, edit, copy) come from defaults.ts.
 */
export const customerActions: AppAction<CustomerRow>[] = [
    {
        id: 'customer.new_sale',
        entity: 'customer',
        labelKey: 'customers.actions.new_sale',
        icon: ShoppingCart,
        permission: 'pos.sell',
        visible: (row) => row.is_active,
        run: ({ row, navigate }) => navigate(`/app/pos?customer=${row.uuid}`),
    },
    {
        id: 'customer.collect_due',
        entity: 'customer',
        labelKey: 'customers.actions.collect_due',
        icon: BadgeDollarSign,
        permission: 'payments.collect',
        visible: (row) => row.balance > 0,
        run: ({ row, navigate }) => navigate(`/app/dues?customer=${row.uuid}`),
    },
    {
        id: 'customer.statement',
        entity: 'customer',
        labelKey: 'customers.actions.statement',
        icon: FileText,
        permission: 'customers.view',
        run: ({ row, navigate }) =>
            navigate(`/app/customers/${row.uuid}/statement`),
    },
    {
        id: 'customer.issue_card',
        entity: 'customer',
        labelKey: 'customers.actions.issue_card',
        icon: CreditCard,
        permission: 'cards.issue',
        requires: 'handler',
        visible: (row) => row.is_active && row.card_status !== 'active',
        run: ({ row, handlers }) => handlers?.['customer.issue_card']?.(row),
    },
    {
        id: 'customer.merge',
        entity: 'customer',
        labelKey: 'customers.actions.merge',
        icon: GitMerge,
        permission: 'customers.delete',
        group: 'danger',
        requires: 'handler',
        run: ({ row, handlers }) => handlers?.['customer.merge']?.(row),
    },
    {
        id: 'customer.deactivate',
        entity: 'customer',
        labelKey: 'customers.actions.deactivate',
        icon: Ban,
        permission: 'customers.delete',
        group: 'danger',
        requires: 'handler',
        visible: (row) => row.is_active,
        run: ({ row, handlers }) => handlers?.['customer.deactivate']?.(row),
    },
];

export const cardActions: AppAction<CardRow>[] = [
    {
        id: 'card.lost',
        entity: 'card',
        labelKey: 'cards.actions.lost',
        icon: Ban,
        permission: 'cards.issue',
        group: 'danger',
        requires: 'handler',
        visible: (row) => row.status === 'active',
        run: ({ row, handlers }) => handlers?.['card.lost']?.(row),
    },
    {
        id: 'card.reissue',
        entity: 'card',
        labelKey: 'cards.actions.reissue',
        icon: CreditCard,
        permission: 'cards.issue',
        requires: 'handler',
        visible: (row) => row.status === 'active' || row.status === 'lost',
        run: ({ row, handlers }) => handlers?.['card.reissue']?.(row),
    },
];

let registered = false;

/** Idempotent; wired in defaults.ts. */
export function registerCustomerActions(): void {
    if (registered) {
        return;
    }
    registered = true;
    actions.register(...customerActions);
    actions.register(...cardActions);
    actions.register({
        id: 'cmd.customer_statement',
        entity: null,
        labelKey: 'customers.statement.title',
        icon: FileText,
        permission: 'customers.view',
        href: '/app/customers/statement',
    });
}
