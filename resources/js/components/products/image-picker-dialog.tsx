import { ImageIcon, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useHotkeyScope } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { apiRequest } from '@/lib/api';

/** A photo already uploaded to one of this tenant's products (GET /app/products/images). */
export type LibraryImage = {
    path: string;
    url: string;
    name: string;
    uses: number;
};

export const PRODUCT_IMAGES_URL = '/app/products/images';

/**
 * "Reuse an uploaded photo": the tenant's distinct product photos, searchable by product name.
 * Picking one hands back its path; the product then points at the same file (no copy).
 */
export function ImagePickerDialog({
    open,
    onOpenChange,
    onPick,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onPick: (image: LibraryImage) => void;
}) {
    const { t } = useT();
    useHotkeyScope('dialog', open);
    const [query, setQuery] = useState('');
    const [images, setImages] = useState<LibraryImage[] | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }
        const controller = new AbortController();
        const timer = setTimeout(
            () => {
                const q = query.trim();
                apiRequest<{ images: LibraryImage[] }>(
                    q === ''
                        ? PRODUCT_IMAGES_URL
                        : `${PRODUCT_IMAGES_URL}?q=${encodeURIComponent(q)}`,
                    { signal: controller.signal },
                )
                    .then((r) => {
                        setImages(r.images);
                        setFailed(false);
                    })
                    .catch(() => {
                        if (!controller.signal.aborted) {
                            setFailed(true);
                        }
                    });
            },
            query === '' ? 0 : 200,
        );

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [open, query]);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    setQuery('');
                }
                onOpenChange(next);
            }}
        >
            <DialogContent className="max-w-160">
                <DialogHeader>
                    <DialogTitle>
                        {t('products.photo_library_title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('products.photo_library_hint')}
                    </DialogDescription>
                </DialogHeader>
                <div className="relative">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <input
                        autoFocus
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={t('products.photo_library_search')}
                        aria-label={t('products.photo_library_search')}
                        className="touch-target h-9 w-full rounded-lg border bg-surface-sunken pr-3 pl-8 text-xs focus:border-primary focus:ring-2 focus:ring-ring/40 focus:outline-none"
                    />
                </div>
                <div className="max-h-[50vh] min-h-40 overflow-y-auto">
                    {failed ? (
                        <p
                            role="alert"
                            className="p-6 text-center text-xs text-destructive"
                        >
                            {t('products.photo_library_failed')}
                        </p>
                    ) : images === null ? (
                        <p className="p-6 text-center text-xs text-muted-foreground">
                            {t('products.photo_library_loading')}
                        </p>
                    ) : images.length === 0 ? (
                        <p className="flex flex-col items-center gap-2 p-6 text-center text-xs text-muted-foreground">
                            <ImageIcon className="size-6" aria-hidden="true" />
                            {query.trim() === ''
                                ? t('products.photo_library_empty')
                                : t('products.photo_library_none')}
                        </p>
                    ) : (
                        <ul
                            aria-label={t('products.photo_library_title')}
                            className="grid grid-cols-3 gap-2 sm:grid-cols-4"
                        >
                            {images.map((image) => (
                                <li key={image.path}>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            onPick(image);
                                            setQuery('');
                                            onOpenChange(false);
                                        }}
                                        className="group flex w-full flex-col overflow-hidden rounded-lg border bg-surface text-left hover:border-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <img
                                            src={image.url}
                                            alt=""
                                            loading="lazy"
                                            className="aspect-square w-full bg-surface-sunken object-cover"
                                        />
                                        <span className="truncate px-1.5 py-1 text-[11px] font-medium group-hover:text-primary">
                                            {image.name}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
