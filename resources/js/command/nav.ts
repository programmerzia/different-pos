import {
    BarChart3,
    Boxes,
    Contact,
    LayoutGrid,
    type LucideIcon,
    Receipt,
    Settings,
    ShoppingCart,
    Truck,
} from 'lucide-react';
import { dashboard } from '@/routes';

/**
 * Sidebar + command-palette pages, one list (02_AppFlow §1).
 * Wayfinder helpers replace these literals as each module's routes land (D3.3+); until then the
 * paths follow the route table in 02_AppFlow §2.
 */
export type NavLeaf = {
    id: string;
    labelKey: string;
    href: string;
    permission?: string | readonly string[];
};

export type NavGroup = {
    id: string;
    labelKey: string;
    icon: LucideIcon;
    /** The chord after `G`. */
    chord?: string;
    href: string;
    permission?: string | readonly string[];
    /** A full page load (a non-Inertia page). The online register at /app/pos is an Inertia page. */
    external?: boolean;
    keys?: string;
    children?: NavLeaf[];
};

export const NAV: NavGroup[] = [
    {
        id: 'dashboard',
        labelKey: 'nav.dashboard',
        icon: LayoutGrid,
        chord: 'd',
        href: dashboard().url,
        permission: 'dashboard.view',
    },
    {
        id: 'pos',
        labelKey: 'nav.pos',
        icon: ShoppingCart,
        href: '/app/pos',
        keys: 'f1',
        permission: 'pos.sell',
    },
    {
        id: 'sales',
        labelKey: 'nav.sales',
        icon: Receipt,
        chord: 's',
        href: '/app/sales',
        permission: ['sales.view', 'returns.view'],
        children: [
            {
                id: 'sales.list',
                labelKey: 'nav.sales_list',
                href: '/app/sales',
                permission: 'sales.view',
            },
            {
                id: 'sales.returns',
                labelKey: 'nav.returns',
                href: '/app/returns',
                permission: 'returns.view',
            },
        ],
    },
    {
        id: 'customers',
        labelKey: 'nav.customers',
        icon: Contact,
        chord: 'c',
        href: '/app/customers',
        permission: ['customers.view', 'cards.view'],
        children: [
            {
                id: 'customers.list',
                labelKey: 'nav.customers_list',
                href: '/app/customers',
                permission: 'customers.view',
            },
            {
                id: 'customers.dues',
                labelKey: 'nav.dues',
                href: '/app/dues',
                permission: 'customers.view',
            },
            {
                id: 'customers.messages',
                labelKey: 'sms.nav_messages',
                href: '/app/messages',
                permission: 'customers.view',
            },
            {
                id: 'customers.cards',
                labelKey: 'nav.cards',
                href: '/app/membership-cards',
                permission: 'cards.view',
            },
        ],
    },
    {
        id: 'inventory',
        labelKey: 'nav.inventory',
        icon: Boxes,
        chord: 'i',
        href: '/app/products',
        permission: ['products.view', 'stock.view'],
        children: [
            {
                id: 'inventory.products',
                labelKey: 'nav.products',
                href: '/app/products',
                permission: 'products.view',
            },
            {
                id: 'inventory.stock',
                labelKey: 'nav.stock',
                href: '/app/stock',
                permission: 'stock.view',
            },
            {
                id: 'inventory.adjustments',
                labelKey: 'nav.adjustments',
                href: '/app/adjustments',
                permission: 'stock.adjust',
            },
        ],
    },
    {
        id: 'purchases',
        labelKey: 'nav.purchases',
        icon: Truck,
        chord: 'p',
        href: '/app/purchases',
        permission: ['purchases.view', 'vendors.view'],
        children: [
            {
                id: 'purchases.list',
                labelKey: 'nav.purchases_list',
                href: '/app/purchases',
                permission: 'purchases.view',
            },
            {
                id: 'purchases.vendors',
                labelKey: 'nav.vendors',
                href: '/app/vendors',
                permission: 'vendors.view',
            },
        ],
    },
    {
        id: 'reports',
        labelKey: 'nav.reports',
        icon: BarChart3,
        chord: 'r',
        href: '/app/reports',
        permission: 'reports.view',
    },
    {
        id: 'settings',
        labelKey: 'nav.settings',
        icon: Settings,
        chord: ',',
        href: '/app/settings/business',
        permission: ['settings.manage', 'users.manage', 'audit.view'],
        children: [
            {
                id: 'settings.business',
                labelKey: 'nav.business',
                href: '/app/settings/business',
                permission: 'settings.manage',
            },
            {
                id: 'settings.branches',
                labelKey: 'nav.branches',
                href: '/app/settings/branches',
                permission: 'settings.manage',
            },
            {
                id: 'settings.registers',
                labelKey: 'nav.registers',
                href: '/app/settings/registers',
                permission: 'settings.manage',
            },
            {
                id: 'settings.payment_methods',
                labelKey: 'nav.payment_methods',
                href: '/app/settings/payment-methods',
                permission: 'settings.manage',
            },
            {
                id: 'settings.taxes',
                labelKey: 'nav.taxes',
                href: '/app/settings/taxes',
                permission: 'settings.manage',
            },
            {
                id: 'settings.units',
                labelKey: 'nav.units',
                href: '/app/settings/units',
                permission: 'settings.manage',
            },
            {
                id: 'settings.users',
                labelKey: 'nav.users',
                href: '/app/settings/users',
                permission: 'users.manage',
            },
            {
                id: 'settings.roles',
                labelKey: 'nav.roles',
                href: '/app/settings/roles',
                permission: 'users.manage',
            },
            {
                id: 'settings.sms',
                labelKey: 'sms.nav_settings',
                href: '/app/settings/sms',
                permission: 'settings.manage',
            },
            {
                id: 'settings.audit',
                labelKey: 'nav.audit',
                href: '/app/settings/audit',
                permission: 'audit.view',
            },
        ],
    },
];
