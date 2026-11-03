import { ScanBarcode } from 'lucide-react';
import { forwardRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { useT } from '@/i18n';
import { apiRequest } from '@/lib/api';

export type ScanProduct = {
    id: number;
    name: string;
    variant_label: string | null;
    sku: string;
    barcode: string | null;
    track_stock: boolean;
    /** Paisa; 0 when the user may not see costs. */
    cost_price: number;
    /** Paisa. */
    sale_price: number;
    /** Milli-units on hand at the current branch. */
    on_hand: number;
};

type Result = { exact: boolean; products: ScanProduct[] };

/**
 * Scan-to-add: a barcode scanner types the code and presses Enter, which adds the exact match.
 * Anything else lists up to 8 name/SKU matches to pick from (Enter picks a single match).
 */
export const ScanField = forwardRef<HTMLInputElement, { lookupUrl: string; onPick: (product: ScanProduct) => void }>(
    function ScanField({ lookupUrl, onPick }, ref) {
        const { t } = useT();
        const [text, setText] = useState('');
        const [matches, setMatches] = useState<ScanProduct[]>([]);
        const [message, setMessage] = useState<string | null>(null);
        const [busy, setBusy] = useState(false);

        const pick = (product: ScanProduct) => {
            onPick(product);
            setText('');
            setMatches([]);
            setMessage(null);
        };

        const run = async () => {
            const code = text.trim();
            if (code === '' || busy) {
                return;
            }
            setBusy(true);
            try {
                const result = await apiRequest<Result>(`${lookupUrl}?code=${encodeURIComponent(code)}`);
                const first = result.products[0];
                if (first && (result.exact || result.products.length === 1)) {
                    pick(first);
                } else if (result.products.length === 0) {
                    setMatches([]);
                    setMessage(t('scan.not_found', { code }));
                } else {
                    setMessage(null);
                    setMatches(result.products);
                }
            } catch {
                setMessage(t('scan.failed'));
            } finally {
                setBusy(false);
            }
        };

        return (
            <div className="relative">
                <ScanBarcode className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                <Input
                    ref={ref}
                    id="scan"
                    className="num min-h-11 pl-9"
                    value={text}
                    autoComplete="off"
                    placeholder={t('scan.placeholder')}
                    aria-label={t('scan.label')}
                    onChange={(e) => setText(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            void run();
                        }
                        if (e.key === 'Escape') {
                            setMatches([]);
                            setMessage(null);
                        }
                    }}
                />
                {message ? <p role="alert" className="mt-1 text-sm text-destructive">{message}</p> : null}
                {matches.length > 0 ? (
                    <ul aria-label={t('scan.matches')} className="absolute z-10 mt-1 w-full overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md">
                        {matches.map((m) => (
                            <li key={m.id}>
                                <button type="button" className="flex min-h-11 w-full items-center justify-between gap-3 px-3 text-left text-sm hover:bg-muted focus-visible:bg-muted focus-visible:outline-none" onClick={() => pick(m)}>
                                    <span className="truncate">{m.name}{m.variant_label ? ` · ${m.variant_label}` : ''}</span>
                                    <span className="num text-xs text-muted-foreground">{m.sku}</span>
                                </button>
                            </li>
                        ))}
                    </ul>
                ) : null}
            </div>
        );
    },
);
