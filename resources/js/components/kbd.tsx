import { parseSequence, displayCombo } from '@/hotkeys/matcher';
import { cn } from '@/lib/utils';

/** A key hint: `<Kbd keys="mod+k" />`, chords as `<Kbd keys="g s" />`. Hidden on touch-only devices. */
export function Kbd({
    keys,
    className,
    onButton = false,
}: {
    keys: string;
    className?: string;
    /** Right-aligned at 70 % opacity inside a button. */
    onButton?: boolean;
}) {
    let parts: string[][];
    try {
        parts = parseSequence(keys).map(displayCombo);
    } catch {
        parts = [[keys]];
    }

    return (
        <span
            aria-hidden="true"
            className={cn(
                'inline-flex items-center gap-1 [@media(pointer:coarse)]:hidden',
                onButton && 'ml-auto opacity-70',
                className,
            )}
        >
            {parts.map((chord, index) => (
                <kbd
                    key={index}
                    className="rounded-sm border border-border bg-muted px-1.5 py-0.5 font-mono text-xs leading-none text-muted-foreground"
                >
                    {chord.join('+')}
                </kbd>
            ))}
        </span>
    );
}
