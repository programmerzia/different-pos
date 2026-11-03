import {
    Ban,
    BadgeDollarSign,
    CreditCard,
    ExternalLink,
    FileSpreadsheet,
    FileText,
    ListPlus,
    Printer,
    RefreshCw,
    GitMerge,
    IdCard,
    ShoppingCart,
} from 'lucide-react';
import { actions, type AppAction } from './actions';
import type { CardMember } from '@/components/cards/card-face';
import type { CardRow, CustomerRow } from '@/types/customers';

/** CR80 print view of a customer's active card (opens in a new tab; the browser saves the PDF). */
export const cardPrintUrl = (customerUuid: string): string =>
    `/app/membership-cards/print?customers=${customerUuid}&layout=cr80`;

/** The customer list as CSV, with the list's query string (`q`, `filter[...]`, `sort`). */
export const customersExportUrl = (
    query: Record<string, string | number> = {},
): string => {
    const params = new URLSearchParams(
        Object.entries(query)
            .filter(([key]) => key !== 'page' && key !== 'per_page')
            .map(([key, value]) => [key, String(value)]),
    ).toString();

    return `/app/customers/export.csv${params ? `?${params}` : ''}`;
};

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
        id: 'customer.print_card',
        entity: 'customer',
        labelKey: 'customers.actions.print_card',
        icon: IdCard,
        permission: 'cards.issue',
        visible: (row) => row.card_status === 'active',
        run: ({ row, navigate }) =>
            navigate(cardPrintUrl(row.uuid), { newTab: true }),
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

/**
 * A member in the card studio (queue rows): print, press CSV, report lost → reissue (page handlers),
 * open the customer. Same registry as the palette and the context menu.
 */
export const memberCardActions: AppAction<CardMember>[] = [
    {
        id: 'member_card.print',
        entity: 'member_card',
        labelKey: 'cards.studio.print_card',
        icon: Printer,
        permission: 'cards.issue',
        requires: 'handler',
        run: ({ row, handlers }) => handlers?.['member_card.print']?.(row),
    },
    {
        id: 'member_card.export',
        entity: 'member_card',
        labelKey: 'cards.press.csv',
        icon: FileSpreadsheet,
        permission: 'cards.issue',
        requires: 'handler',
        run: ({ row, handlers }) => handlers?.['member_card.export']?.(row),
    },
    {
        id: 'member_card.queue',
        entity: 'member_card',
        labelKey: 'cards.studio.queue_toggle',
        icon: ListPlus,
        requires: 'handler',
        run: ({ row, handlers }) => handlers?.['member_card.queue']?.(row),
    },
    {
        id: 'member_card.open',
        entity: 'member_card',
        labelKey: 'cards.studio.open_customer',
        icon: ExternalLink,
        permission: 'customers.view',
        run: ({ row, navigate }) => navigate(`/app/customers/${row.uuid}`),
    },
    {
        id: 'member_card.reissue',
        entity: 'member_card',
        labelKey: 'cards.studio.lost_reissue',
        icon: RefreshCw,
        permission: 'cards.issue',
        group: 'danger',
        requires: 'handler',
        visible: (row) =>
            row.card?.status === 'active' || row.card?.status === 'lost',
        run: ({ row, handlers }) => handlers?.['member_card.reissue']?.(row),
    },
];

let memberCardsRegistered = false;

/** Idempotent; called by the card studio page. */
export function registerMemberCardActions(): void {
    if (memberCardsRegistered) {
        return;
    }
    memberCardsRegistered = true;
    actions.register(...memberCardActions);
}

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
    actions.register({
        id: 'cmd.customers_export',
        entity: null,
        labelKey: 'customers.export_csv',
        icon: FileSpreadsheet,
        permission: 'customers.view',
        run: () => window.location.assign(customersExportUrl()),
    });
}
