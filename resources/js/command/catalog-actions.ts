import {
    Boxes,
    Copy,
    Power,
    Printer,
    SlidersHorizontal,
    Truck,
} from 'lucide-react';
import { toast } from 'sonner';
import { i18n } from '@/i18n';
import { actions, type AppAction } from './actions';

type ProductRow = {
    id?: number;
    uuid?: string;
    sku?: string;
    barcode?: string | null;
    is_active?: boolean;
    track_stock?: boolean;
};

/** Row actions of the `product` entity (context menu, palette). Registered by the products page. */
const productActions: AppAction<ProductRow>[] = [
    {
        id: 'product.view_stock',
        entity: 'product',
        labelKey: 'products.view_stock',
        icon: Boxes,
        permission: 'stock.view',
        run: ({ row, navigate }) =>
            navigate(`/app/stock/${row.uuid}/movements`),
    },
    {
        id: 'product.adjust_stock',
        entity: 'product',
        labelKey: 'stock.adjust_row',
        icon: SlidersHorizontal,
        permission: 'stock.adjust',
        visible: (row) => row.track_stock !== false,
        run: ({ row, navigate }) =>
            navigate(`/app/adjustments/create?product=${row.uuid ?? ''}`),
    },
    {
        id: 'product.toggle_active',
        entity: 'product',
        labelKey: 'products.toggle_active',
        icon: Power,
        permission: 'products.update',
        requires: 'handler',
        run: ({ row, handlers }) => handlers?.['product.toggle_active']?.(row),
    },
    {
        id: 'product.print_label',
        entity: 'product',
        labelKey: 'products.print_label',
        icon: Printer,
        keys: 'ctrl+shift+p',
        permission: 'products.view',
        run: () => toast.info(i18n.t('products.label_soon')),
    },
    {
        id: 'product.copy_sku',
        entity: 'product',
        labelKey: 'products.copy_sku',
        icon: Copy,
        group: 'copy',
        visible: (row) => Boolean(row.sku),
        run: ({ row }) => void navigator.clipboard.writeText(row.sku ?? ''),
    },
    {
        id: 'product.copy_barcode',
        entity: 'product',
        labelKey: 'products.copy_barcode',
        icon: Copy,
        group: 'copy',
        visible: (row) => Boolean(row.barcode),
        run: ({ row }) => void navigator.clipboard.writeText(row.barcode ?? ''),
    },
];

export function registerProductActions(): () => void {
    return actions.register(...productActions);
}

/** Row actions of the `stock` entity (the stock list): adjust (prefilled), transfer (a later phase). */
const stockActions: AppAction<ProductRow>[] = [
    {
        id: 'stock.adjust',
        entity: 'stock',
        labelKey: 'stock.adjust_row',
        icon: SlidersHorizontal,
        permission: 'stock.adjust',
        run: ({ row, navigate }) =>
            navigate(`/app/adjustments/create?product=${row.uuid ?? ''}`),
    },
    {
        id: 'stock.transfer',
        entity: 'stock',
        labelKey: 'stock.transfer',
        icon: Truck,
        permission: 'stock.transfer',
        run: () => toast.info(i18n.t('stock.next_phase')),
    },
    {
        id: 'stock.copy_sku',
        entity: 'stock',
        labelKey: 'products.copy_sku',
        icon: Copy,
        group: 'copy',
        visible: (row) => Boolean(row.sku),
        run: ({ row }) => void navigator.clipboard.writeText(row.sku ?? ''),
    },
];

export function registerStockActions(): () => void {
    return actions.register(...stockActions);
}
