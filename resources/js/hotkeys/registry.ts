import {
    normalizeKeys,
    parseSequence,
    sequencesConflict,
    type Combo,
} from './matcher';

/** `global` is always live; any other scope is live only while it is the top of the scope stack. */
export type HotkeyScope =
    | 'global'
    | 'list'
    | 'form'
    | 'dialog'
    | 'pos'
    | (string & {});

export type HotkeyDef = {
    /** Unique, dotted: `nav.sales`, `list.new`. */
    id: string;
    /** `mod+k`, `g s`, `shift+f10`, `?`. */
    keys: string;
    scope: HotkeyScope;
    /** i18n key of the label shown in the `?` overlay and the palette. */
    labelKey: string;
    handler: (event: KeyboardEvent) => void;
    /** Also fire while an input is focused. Ctrl/Alt/Meta combos, F-keys and Esc always do. */
    allowInInput?: boolean;
    /** Fire even when a dialog is the top scope (e.g. the palette toggling itself). */
    inDialog?: boolean;
    /** Listed in the overlay but not bound (informational, e.g. Space = select). */
    display?: boolean;
};

export type RegisteredHotkey = HotkeyDef & {
    sequence: Combo[];
    normalized: string;
};

export class HotkeyConflictError extends Error {
    override name = 'HotkeyConflictError';
}

const isDev = (): boolean => {
    try {
        return import.meta.env.DEV === true || import.meta.env.MODE === 'test';
    } catch {
        return false;
    }
};

/** The central registry. One instance is shared by the whole app (`hotkeys`); tests build their own. */
export class HotkeyRegistry {
    private readonly entries = new Map<string, RegisteredHotkey>();
    private readonly listeners = new Set<() => void>();
    private scopeStack: HotkeyScope[] = [];
    private version = 0;

    constructor(private readonly strict: () => boolean = isDev) {}

    /** Registers a hotkey and returns its unregister function. A conflict throws in dev, warns in prod. */
    register(def: HotkeyDef): () => void {
        const sequence = parseSequence(def.keys);
        const normalized = normalizeKeys(def.keys);

        for (const existing of this.entries.values()) {
            if (existing.id === def.id) {
                continue; // Hot reload / strict-mode remount replaces itself.
            }
            if (!this.scopesOverlap(existing.scope, def.scope)) {
                continue;
            }
            if (sequencesConflict(existing.sequence, sequence)) {
                const message = `Hotkey conflict: "${def.id}" (${def.keys}, ${def.scope}) vs "${existing.id}" (${existing.keys}, ${existing.scope}).`;
                if (this.strict()) {
                    throw new HotkeyConflictError(message);
                }
                console.warn(message);

                return () => undefined;
            }
        }

        this.entries.set(def.id, { ...def, sequence, normalized });
        this.emit();

        return () => {
            const current = this.entries.get(def.id);
            if (current && current.handler === def.handler) {
                this.entries.delete(def.id);
                this.emit();
            }
        };
    }

    /** `global` overlaps every scope (it is always live); other scopes overlap only themselves. */
    private scopesOverlap(a: HotkeyScope, b: HotkeyScope): boolean {
        return a === b || a === 'global' || b === 'global';
    }

    pushScope(scope: HotkeyScope): () => void {
        this.scopeStack = [...this.scopeStack, scope];
        this.emit();

        return () => {
            const index = this.scopeStack.lastIndexOf(scope);
            if (index >= 0) {
                this.scopeStack = this.scopeStack.filter((_, i) => i !== index);
                this.emit();
            }
        };
    }

    topScope(): HotkeyScope | null {
        return this.scopeStack.at(-1) ?? null;
    }

    /** Hotkeys that can fire right now. */
    active(): RegisteredHotkey[] {
        const top = this.topScope();
        const inDialog = top === 'dialog';

        return [...this.entries.values()].filter((entry) => {
            if (entry.display === true) {
                return false;
            }
            if (entry.scope === 'global') {
                return !inDialog || entry.inDialog === true;
            }

            return entry.scope === top;
        });
    }

    /** Everything a user can press on this page, for the `?` overlay (grouped by the caller). */
    listVisible(): RegisteredHotkey[] {
        const top = this.topScope();

        return [...this.entries.values()].filter(
            (entry) => entry.scope === 'global' || entry.scope === top,
        );
    }

    get(id: string): RegisteredHotkey | undefined {
        return this.entries.get(id);
    }

    size(): number {
        return this.entries.size;
    }

    subscribe = (listener: () => void): (() => void) => {
        this.listeners.add(listener);

        return () => this.listeners.delete(listener);
    };

    getVersion = (): number => this.version;

    private emit(): void {
        this.version++;
        this.listeners.forEach((listener) => listener());
    }
}

export const hotkeys = new HotkeyRegistry();
