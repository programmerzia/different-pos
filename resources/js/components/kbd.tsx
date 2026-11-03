import { parseSequence, displayCombo } from '@/hotkeys/matcher';
import { cn } from '@/lib/utils';

/**
 * A key hint as one mockup keycap: `<Kbd keys="mod+k" />` → "Ctrl+K", chords `<Kbd keys="g s" />` → "G S".
 * `tone="on-primary"` is the keycap on a solid primary surface (active sidebar item). Hidden on touch-only devices.
 */
export function Kbd({
    keys,
    className,
    onButton = false,
    tone = 'default',
}: {
    keys: string;
    className?: string;
    /** Right-aligned at 70 % opacity inside a button. */
    onButton?: boolean;
    tone?: 'default' | 'on-primary';
}) {
    let parts: string[][];
    try {
        parts = parseSequence(keys).map(displayCombo);
    } catch {
        parts = [[keys]];
    }

    return (
        <kbd
            aria-hidden="true"
            className={cn(
                'keycap inline-flex shrink-0 items-center [@media(pointer:coarse)]:hidden',
                tone === 'on-primary' &&
                    'border-transparent bg-foreground/20 text-primary-foreground shadow-none',
                onButton && 'ml-auto opacity-70',
                className,
            )}
        >
            {parts.map((chord) => chord.join('+')).join(' ')}
        </kbd>
    );
}
