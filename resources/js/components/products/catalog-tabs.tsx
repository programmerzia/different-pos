import { Link } from '@inertiajs/react';
import { Barcode, Layers, Tag } from 'lucide-react';
import { toast } from 'sonner';
import { useCan } from '@/hooks/use-can';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

export type CatalogTab = 'products' | 'categories' | 'barcodes';

const tab =
    'flex h-7 items-center gap-1.5 rounded-lg px-3 transition-colors [@media(pointer:coarse)]:min-h-11';
const on = 'bg-surface font-bold text-primary shadow-xs';
const off = 'text-muted-foreground hover:text-foreground';

/**
 * Mockup catalog hub sub-tabs (render_catalog_stock_purchases.py): Products & SKUs · Category Management ·
 * Bulk Barcodes Generator. Label printing is a later step, so the barcode tab only explains that.
 */
export function CatalogTabs({ active }: { active: CatalogTab }) {
    const { t } = useT();
    const can = useCan();

    return (
        <nav
            aria-label={t('products.hub_tabs')}
            className="flex w-fit shrink-0 items-center gap-1 rounded-xl border bg-surface-sunken p-1 text-xs font-semibold"
        >
            <Link
                href="/app/products"
                aria-current={active === 'products' ? 'page' : undefined}
                className={cn(tab, active === 'products' ? on : off)}
            >
                <Tag className="size-3.25" aria-hidden="true" />
                <span>{t('products.tab_products')}</span>
            </Link>
            {can('products.update') ? (
                <Link
                    href="/app/products/categories"
                    aria-current={active === 'categories' ? 'page' : undefined}
                    className={cn(tab, active === 'categories' ? on : off)}
                >
                    <Layers className="size-3.25" aria-hidden="true" />
                    <span>{t('products.tab_categories')}</span>
                </Link>
            ) : null}
            <button
                type="button"
                aria-disabled="true"
                title={t('products.label_soon')}
                onClick={() => toast.info(t('products.label_soon'))}
                className={cn(tab, off, 'cursor-not-allowed opacity-60')}
            >
                <Barcode className="size-3.25" aria-hidden="true" />
                <span>{t('products.tab_barcodes')}</span>
            </button>
        </nav>
    );
}
