import {
    Banknote,
    ChartNoAxesColumn,
    FileText,
    IdCard,
    Lock,
    type LucideIcon,
    Package,
    RefreshCw,
    Scale,
    ScrollText,
    Send,
    Settings,
    Shield,
    ShoppingCart,
    Tag,
    Truck,
    User,
    Users,
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
    /** Not drawn in the sidebar (palette + chords only); the mockup folds the page into another item. */
    sidebar?: false;
    /** Extra path prefixes that light this item up (pages folded into it). */
    activeFor?: readonly string[];
};

export type NavSection = {
    id: string;
    labelKey: string;
    items: NavGroup[];
};

/**
 * Sidebar sections = the baseline mockup (tools/mockup/baseline/parts/render_shell.py, commit 2078d6c):
 * SELL / CUSTOMERS / CATALOG & STOCK / MONEY / INSIGHTS / ADMIN & SYSTEM. MONEY (accounts, fixed assets),
 * Backup, SaaS admin and the states demo have no module yet, so they are absent. A section is hidden when
 * none of its items is permitted.
 */
export const NAV_SECTIONS: NavSection[] = [
    {
        id: 'sales',
        labelKey: 'nav.group_sell',
        items: [
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
                labelKey: 'nav.sales_history',
                icon: FileText,
                chord: 's',
                href: '/app/sales',
                permission: 'sales.view',
            },
            {
                id: 'returns',
                labelKey: 'nav.returns_exchange',
                icon: RefreshCw,
                chord: 't',
                href: '/app/returns',
                permission: 'returns.view',
            },
            {
                id: 'dues',
                labelKey: 'nav.due_collection',
                icon: Banknote,
                chord: 'u',
                href: '/app/dues',
                permission: 'customers.view',
            },
        ],
    },
    {
        id: 'customers',
        labelKey: 'nav.group_customers',
        items: [
            {
                id: 'customers',
                labelKey: 'nav.customers',
                icon: User,
                chord: 'c',
                href: '/app/customers',
                permission: 'customers.view',
            },
            {
                id: 'statement',
                labelKey: 'nav.customer_statement',
                icon: ScrollText,
                chord: 'a',
                href: '/app/customers/statement',
                permission: 'customers.view',
            },
            {
                id: 'cards',
                labelKey: 'nav.cards',
                icon: IdCard,
                href: '/app/membership-cards',
                permission: 'cards.view',
            },
            {
                id: 'messages',
                labelKey: 'nav.whatsapp_sms',
                icon: Send,
                chord: 'w',
                href: '/app/messages',
                permission: 'customers.view',
            },
        ],
    },
    {
        id: 'catalog',
        labelKey: 'nav.group_catalog_stock',
        items: [
            {
                id: 'products',
                labelKey: 'nav.products_skus',
                icon: Tag,
                chord: 'i',
                href: '/app/products',
                permission: 'products.view',
            },
            {
                id: 'stock',
                labelKey: 'nav.stock_transfer',
                icon: Package,
                chord: 'k',
                href: '/app/stock',
                activeFor: ['/app/adjustments'],
                permission: 'stock.view',
            },
            {
                id: 'adjustments',
                labelKey: 'nav.adjustments',
                icon: Scale,
                href: '/app/adjustments',
                permission: 'stock.adjust',
                sidebar: false,
            },
            {
                id: 'purchases',
                labelKey: 'nav.purchases_grn',
                icon: Truck,
                chord: 'p',
                href: '/app/purchases',
                permission: 'purchases.view',
            },
            {
                id: 'vendors',
                labelKey: 'nav.vendor_directory',
                icon: Users,
                chord: 'v',
                href: '/app/vendors',
                permission: 'vendors.view',
            },
        ],
    },
    {
        id: 'analytics',
        labelKey: 'nav.group_insights',
        items: [
            {
                id: 'dashboard',
                labelKey: 'nav.analytics_dashboard',
                icon: ChartNoAxesColumn,
                chord: 'd',
                href: dashboard().url,
                permission: 'dashboard.view',
            },
            {
                id: 'reports',
                labelKey: 'nav.reports_directory',
                icon: FileText,
                chord: 'r',
                href: '/app/reports',
                permission: 'reports.view',
            },
        ],
    },
    {
        id: 'system',
        labelKey: 'nav.group_admin',
        items: [
            {
                id: 'users',
                labelKey: 'nav.users_roles',
                icon: Shield,
                href: '/app/settings/users',
                permission: 'users.manage',
            },
            {
                id: 'settings',
                labelKey: 'nav.settings',
                icon: Settings,
                chord: ',',
                href: '/app/settings/business',
                permission: 'settings.manage',
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
                ],
            },
            {
                id: 'audit',
                labelKey: 'nav.audit_log',
                icon: Lock,
                href: '/app/settings/audit',
                permission: 'audit.view',
            },
        ],
    },
];

/** Flat list: drives the G-chords and the command palette. */
export const NAV: NavGroup[] = NAV_SECTIONS.flatMap((s) => s.items);

/** The sidebar draws only these: the folded-in pages (`sidebar: false`) stay palette + chord entries. */
export const SIDEBAR_SECTIONS: NavSection[] = NAV_SECTIONS.map((s) => ({
    ...s,
    items: s.items.filter((g) => g.sidebar !== false),
}));

const HOME = '/app';

const matches = (path: string, prefix: string): boolean =>
    prefix === HOME
        ? path === HOME || path === `${HOME}/`
        : path === prefix || path.startsWith(`${prefix}/`);

export type ActiveNav = { groupId: string; leafId: string | null } | null;

/**
 * The one active sidebar item for a path: the longest matching href (or `activeFor` prefix) wins, so
 * `/app/customers/statement` lights "Customer statement" only, and the dashboard (`/app`) matches exactly.
 */
export function activeNav(
    path: string,
    sections: NavSection[] = SIDEBAR_SECTIONS,
): ActiveNav {
    let best: ActiveNav = null;
    let bestLength = -1;
    const consider = (prefix: string, hit: NonNullable<ActiveNav>) => {
        if (matches(path, prefix) && prefix.length > bestLength) {
            best = hit;
            bestLength = prefix.length;
        }
    };

    for (const section of sections) {
        for (const group of section.items) {
            for (const prefix of [group.href, ...(group.activeFor ?? [])]) {
                consider(prefix, { groupId: group.id, leafId: null });
            }
            for (const leaf of group.children ?? []) {
                consider(leaf.href, { groupId: group.id, leafId: leaf.id });
            }
        }
    }

    return best;
}
