import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

export type Chip = { id: number; label: string };

/** Toggle chips (size / colour values): `aria-pressed`, 44 px, selection shown by tick + fill, not colour alone. */
export function ChipGroup({
    chips,
    selected,
    onToggle,
    label,
}: {
    chips: Chip[];
    selected: number[];
    onToggle: (id: number) => void;
    label: string;
}) {
    return (
        <div role="group" aria-label={label} className="flex flex-wrap gap-2">
            {chips.map((chip) => {
                const on = selected.includes(chip.id);

                return (
                    <button
                        key={chip.id}
                        type="button"
                        aria-pressed={on}
                        onClick={() => onToggle(chip.id)}
                        className={cn(
                            'inline-flex min-h-11 items-center gap-1 rounded-md border px-3 text-sm font-medium outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 md:min-h-9',
                            on
                                ? 'border-primary bg-primary-soft text-primary'
                                : 'border-input bg-background text-foreground hover:bg-muted',
                        )}
                    >
                        {on ? <Check className="size-3.5" aria-hidden="true" /> : null}
                        {chip.label}
                    </button>
                );
            })}
        </div>
    );
}
