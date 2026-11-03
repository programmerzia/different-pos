import {
    ArrowLeftRight,
    ArrowUpRight,
    Copy,
    ExternalLink,
    Languages,
    Palette,
    Pencil,
    Plus,
    Trash2,
    CopyPlus,
    Download,
    Keyboard,
    Package,
    ShoppingCart,
    Truck,
    UserPlus,
} from 'lucide-react';
import { actions, type AppAction } from './actions';
import { registerAdminActions } from './admin-actions';
import { registerCustomerActions } from './customer-actions';
import { registerPlatformActions } from './platform-actions';
import { navigateTo } from './navigate';
import { useUiStore } from './ui-store';
import { usePreferencesStore, SCHEMES } from '@/lib/preferences';

async function copyText(text: string): Promise<void> {
    await navigator.clipboard.writeText(text);
}

/** Generic row actions (every table), 02_AppFlow §1.2 first row. */
const rowActions: AppAction<Record<string, unknown>>[] = [
    {
        id: 'row.open',
        entity: '*',
        labelKey: 'actions.open',
        icon: ArrowUpRight,
        keys: 'enter',
        requires: 'href',
        run: ({ href, navigate }) => href && navigate(href),
    },
    {
        id: 'row.open_new_tab',
        entity: '*',
        labelKey: 'actions.open_new_tab',
        icon: ExternalLink,
        keys: 'ctrl+enter',
        requires: 'href',
        run: ({ href, navigate }) => href && navigate(href, { newTab: true }),
    },
    {
        id: 'row.edit',
        entity: '*',
        labelKey: 'actions.edit',
        icon: Pencil,
        keys: 'e',
        requires: 'handler',
        run: ({ row, handlers }) => handlers?.['row.edit']?.(row),
    },
    {
        id: 'row.duplicate',
        entity: '*',
        labelKey: 'actions.duplicate',
        icon: CopyPlus,
        requires: 'handler',
        run: ({ row, handlers }) => handlers?.['row.duplicate']?.(row),
    },
    {
        id: 'row.copy_id',
        entity: '*',
        labelKey: 'actions.copy_id',
        icon: Copy,
        group: 'copy',
        visible: (row) => row['id'] !== undefined,
        run: ({ row }) => void copyText(String(row['id'])),
    },
    {
        id: 'row.copy_text',
        entity: '*',
        labelKey: 'actions.copy_row',
        icon: Copy,
        group: 'copy',
        run: ({ row }) =>
            void copyText(
                Object.values(row)
                    .filter((v) => ['string', 'number'].includes(typeof v))
                    .join('\t'),
            ),
    },
    {
        id: 'row.export',
        entity: '*',
        labelKey: 'actions.export_selected',
        icon: Download,
        requires: 'handler',
        run: ({ row, selection, handlers }) =>
            handlers?.['row.export']?.(row, selection),
    },
    {
        id: 'row.delete',
        entity: '*',
        labelKey: 'actions.delete',
        icon: Trash2,
        keys: 'delete',
        group: 'danger',
        requires: 'handler',
        run: ({ row, selection, handlers }) =>
            handlers?.['row.delete']?.(row, selection),
    },
];

/** Global commands for the palette: the same registry, `entity: null`. */
const commands: AppAction[] = [
    {
        id: 'cmd.new_sale',
        entity: null,
        labelKey: 'commands.new_sale',
        icon: ShoppingCart,
        keys: 'f1',
        permission: 'pos.sell',
        href: '/app/pos',
    },
    {
        id: 'cmd.new_customer',
        entity: null,
        labelKey: 'commands.new_customer',
        icon: UserPlus,
        permission: 'customers.create',
        href: '/app/customers/create',
    },
    {
        id: 'cmd.new_product',
        entity: null,
        labelKey: 'commands.new_product',
        icon: Plus,
        permission: 'products.create',
        href: '/app/products/create',
    },
    {
        id: 'cmd.receive_purchase',
        entity: null,
        labelKey: 'commands.receive_purchase',
        icon: Truck,
        permission: 'purchases.create',
        href: '/app/purchases/create',
    },
    {
        id: 'cmd.new_adjustment',
        entity: null,
        labelKey: 'commands.new_adjustment',
        icon: Package,
        permission: 'stock.adjust',
        href: '/app/adjustments/create',
    },
    {
        id: 'cmd.new_transfer',
        entity: null,
        labelKey: 'commands.new_transfer',
        icon: ArrowLeftRight,
        permission: 'stock.transfer',
        href: '/app/transfers/create',
    },
    {
        id: 'cmd.shortcuts',
        entity: null,
        labelKey: 'commands.shortcuts',
        icon: Keyboard,
        keys: '?',
        run: () => useUiStore.getState().open('shortcuts'),
    },
    {
        id: 'cmd.language',
        entity: null,
        labelKey: 'commands.toggle_language',
        icon: Languages,
        keys: 'alt+l',
        run: () => {
            const { locale, set } = usePreferencesStore.getState();
            set({ locale: locale === 'bn' ? 'en' : 'bn' });
        },
    },
    {
        id: 'cmd.theme',
        entity: null,
        labelKey: 'commands.theme',
        icon: Palette,
        keys: 'alt+t',
        run: () => useUiStore.getState().open('theme'),
    },
    ...SCHEMES.map((scheme): AppAction => ({
        id: `cmd.scheme.${scheme}`,
        entity: null,
        labelKey: `scheme.${scheme}`,
        icon: Palette,
        run: () => usePreferencesStore.getState().set({ scheme }),
    })),
    {
        id: 'cmd.density',
        entity: null,
        labelKey: 'commands.toggle_density',
        icon: Palette,
        run: () => {
            const { density, set } = usePreferencesStore.getState();
            set({ density: density === 'compact' ? 'comfortable' : 'compact' });
        },
    },
];

let registered = false;

/** Idempotent: called once by the shell. */
export function registerDefaultActions(): void {
    if (registered) {
        return;
    }
    registered = true;
    actions.register(...rowActions);
    actions.register(...commands);
    registerCustomerActions();
    registerAdminActions();
    registerPlatformActions();
}

export { navigateTo };
