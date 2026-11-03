import { ChevronDown, X } from 'lucide-react';
import { useId, useMemo, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

export type VendorOption = { id: number; name: string };

/**
 * Type-ahead vendor combobox (mockup "Vendor Supplier" field of the GRN form): typing filters the
 * active vendors by name, ↓/↑ move, Enter picks, Esc closes, Backspace on a picked vendor clears it.
 * `id`, `aria-invalid` and `aria-describedby` come from `<FormField>`.
 */
export function VendorPicker({
    vendors,
    value,
    onChange,
    id,
    disabled = false,
    'aria-invalid': invalid,
    'aria-describedby': describedBy,
}: {
    vendors: VendorOption[];
    /** Selected vendor id as a string ('' = none). */
    value: string;
    onChange: (id: string) => void;
    id?: string;
    disabled?: boolean;
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
}) {
    const { t } = useT();
    const listId = useId();
    const input = useRef<HTMLInputElement>(null);
    const [text, setText] = useState<string | null>(null); // null = showing the pick
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(0);

    const selected = vendors.find((v) => String(v.id) === value) ?? null;
    const query = (text ?? '').trim().toLowerCase();
    const matches = useMemo(
        () =>
            (query === ''
                ? vendors
                : vendors.filter((v) => v.name.toLowerCase().includes(query))
            ).slice(0, 8),
        [vendors, query],
    );

    const pick = (vendor: VendorOption | null) => {
        onChange(vendor ? String(vendor.id) : '');
        setText(null);
        setOpen(false);
        setActive(0);
    };

    return (
        <div className="relative">
            <Input
                ref={input}
                id={id}
                // oxlint-disable-next-line jsx-a11y/prefer-tag-over-role -- WAI-ARIA type-ahead combobox pattern
                role="combobox"
                aria-expanded={open}
                aria-controls={listId}
                aria-autocomplete="list"
                aria-activedescendant={
                    open && matches[active]
                        ? `${listId}-${matches[active].id}`
                        : undefined
                }
                aria-invalid={invalid}
                aria-describedby={describedBy}
                autoComplete="off"
                disabled={disabled}
                className={cn(
                    'min-h-11 pr-14 font-semibold md:min-h-8',
                    selected && text === null && 'text-foreground',
                )}
                placeholder={t('purchases.vendor_search')}
                value={text ?? selected?.name ?? ''}
                onFocus={() => setOpen(true)}
                onBlur={() => {
                    // Leaving the field keeps the pick and drops a half-typed query.
                    setTimeout(() => {
                        setOpen(false);
                        setText(null);
                    }, 120);
                }}
                onChange={(e) => {
                    setText(e.target.value);
                    setOpen(true);
                    setActive(0);
                    if (selected && e.target.value === '') {
                        onChange('');
                    }
                }}
                onKeyDown={(e) => {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        setOpen(true);
                        setActive((a) => Math.min(a + 1, matches.length - 1));
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        setActive((a) => Math.max(a - 1, 0));
                    } else if (e.key === 'Enter' && open) {
                        const hit = matches[active];
                        if (hit) {
                            e.preventDefault();
                            pick(hit);
                        }
                    } else if (e.key === 'Escape' && open) {
                        e.preventDefault();
                        e.stopPropagation();
                        setOpen(false);
                        setText(null);
                    } else if (
                        e.key === 'Backspace' &&
                        selected &&
                        text === null
                    ) {
                        e.preventDefault();
                        onChange('');
                        setText('');
                    }
                }}
            />
            <div className="absolute inset-y-0 right-1 flex items-center gap-0.5">
                {selected && !disabled ? (
                    <button
                        type="button"
                        className="flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-surface hover:text-foreground [@media(pointer:coarse)]:size-11"
                        aria-label={t('purchases.vendor_clear')}
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={() => {
                            pick(null);
                            input.current?.focus();
                        }}
                    >
                        <X className="size-3.5" aria-hidden="true" />
                    </button>
                ) : null}
                <ChevronDown
                    className="pointer-events-none mr-1 size-3.5 text-muted-foreground"
                    aria-hidden="true"
                />
            </div>
            {open && !disabled ? (
                <ul
                    id={listId}
                    // oxlint-disable-next-line jsx-a11y/prefer-tag-over-role, jsx-a11y/no-noninteractive-element-to-interactive-role -- ARIA listbox popup of the combobox
                    role="listbox"
                    aria-label={t('purchases.vendor')}
                    className="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border bg-popover p-1 text-xs text-popover-foreground shadow-md"
                >
                    {matches.length === 0 ? (
                        <li className="px-2.5 py-2 text-muted-foreground">
                            {t('purchases.vendor_none')}
                        </li>
                    ) : (
                        matches.map((v, i) => (
                            // oxlint-disable-next-line jsx-a11y/click-events-have-key-events -- keyboard handled by the combobox input (aria-activedescendant)
                            <li
                                key={v.id}
                                id={`${listId}-${v.id}`}
                                // oxlint-disable-next-line jsx-a11y/prefer-tag-over-role, jsx-a11y/no-noninteractive-element-to-interactive-role -- ARIA option; focus stays on the input
                                role="option"
                                aria-selected={String(v.id) === value}
                                className={cn(
                                    'flex min-h-9 cursor-pointer items-center rounded-lg px-2.5 font-medium [@media(pointer:coarse)]:min-h-11',
                                    i === active
                                        ? 'bg-primary-soft text-primary'
                                        : 'hover:bg-surface-sunken',
                                )}
                                onMouseDown={(e) => e.preventDefault()}
                                onMouseEnter={() => setActive(i)}
                                onClick={() => pick(v)}
                            >
                                {v.name}
                            </li>
                        ))
                    )}
                </ul>
            ) : null}
        </div>
    );
}
