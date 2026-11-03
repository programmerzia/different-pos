import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

export type Chip = { id: number; label: string };

/** Toggle chips (size / colour values): `aria-pressed`, 44 px, selection shown by tick + fill, not colour alone. */
export function ChipGroup({
    chips,
    selected,
    onToggle,
    label,
    size = 'default',
}: {
    chips: Chip[];
    selected: number[];
    onToggle: (id: number) => void;
    label: string;
    /** `sm`: the mockup's compact size/colour chips (28 px; 44 px on touch screens). */
    size?: 'default' | 'sm';
}) {
    return (
        <fieldset
            className={cn(
                'm-0 flex min-w-0 flex-wrap border-0 p-0',
                size === 'sm' ? 'gap-1.5' : 'gap-2',
            )}
        >
            <legend className="sr-only">{label}</legend>
            {chips.map((chip) => {
                const on = selected.includes(chip.id);

                return (
                    <button
                        key={chip.id}
                        type="button"
                        aria-pressed={on}
                        onClick={() => onToggle(chip.id)}
                        className={cn(
                            'inline-flex items-center gap-1 rounded-md border font-medium outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                            size === 'sm'
                                ? 'h-7 px-2.5 text-xs font-semibold [@media(pointer:coarse)]:min-h-11'
                                : 'min-h-11 px-3 text-sm md:min-h-9',
                            on
                                ? 'border-primary bg-primary-soft text-primary'
                                : 'border-input bg-background text-foreground hover:bg-muted',
                        )}
                    >
                        {on ? (
                            <Check className="size-3.5" aria-hidden="true" />
                        ) : null}
                        {chip.label}
                    </button>
                );
            })}
        </fieldset>
    );
}
