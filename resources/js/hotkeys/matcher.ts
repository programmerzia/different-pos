/**
 * Pure key-combination parsing and matching (no DOM, no React), so it is unit-tested directly.
 *
 * Syntax: `mod+k` (Ctrl or Cmd), `ctrl+shift+p`, `alt+b`, `shift+f10`, `?`, `/`, `j`, `escape`,
 * and chords separated by a space: `g s` (press G, then S).
 */
export type Combo = {
    key: string;
    ctrl: boolean;
    meta: boolean;
    /** `mod` = Ctrl or Meta. */
    mod: boolean;
    alt: boolean;
    shift: boolean;
};

export type KeyLike = {
    key: string;
    code?: string;
    ctrlKey: boolean;
    metaKey: boolean;
    altKey: boolean;
    shiftKey: boolean;
};

const ALIASES: Record<string, string> = {
    esc: 'escape',
    del: 'delete',
    return: 'enter',
    up: 'arrowup',
    down: 'arrowdown',
    left: 'arrowleft',
    right: 'arrowright',
    space: ' ',
    spacebar: ' ',
    menu: 'contextmenu',
    comma: ',',
    plus: '+',
};

export function parseCombo(text: string): Combo {
    const t = text.trim().toLowerCase();
    let raw: string;
    let parts: string[];
    if (t === '+') {
        raw = '+';
        parts = [];
    } else if (t.endsWith('++')) {
        raw = '+';
        parts = t.slice(0, -2).split('+');
    } else {
        parts = t.split('+');
        raw = parts.pop() ?? '';
    }
    const combo: Combo = {
        key: ALIASES[raw] ?? raw,
        ctrl: false,
        meta: false,
        mod: false,
        alt: false,
        shift: false,
    };
    for (const part of parts) {
        if (part === 'mod') {
            combo.mod = true;
        } else if (part === 'ctrl' || part === 'control') {
            combo.ctrl = true;
        } else if (part === 'meta' || part === 'cmd') {
            combo.meta = true;
        } else if (part === 'alt' || part === 'option') {
            combo.alt = true;
        } else if (part === 'shift') {
            combo.shift = true;
        } else if (part !== '') {
            throw new Error(`Unknown key modifier "${part}" in "${text}".`);
        }
    }
    if (combo.key === '') {
        throw new Error(`Empty key in "${text}".`);
    }

    return combo;
}

export function parseSequence(keys: string): Combo[] {
    const sequence = keys
        .trim()
        .split(/\s+/)
        .filter((part) => part !== '')
        .map(parseCombo);
    if (sequence.length === 0) {
        throw new Error('A hotkey needs at least one key.');
    }

    return sequence;
}

/** Canonical text, used for conflict detection: "g s", "mod+k", "shift+f10". */
export function normalizeKeys(keys: string): string {
    return parseSequence(keys)
        .map((c) =>
            [
                c.mod ? 'mod' : '',
                c.ctrl ? 'ctrl' : '',
                c.meta ? 'meta' : '',
                c.alt ? 'alt' : '',
                c.shift ? 'shift' : '',
                c.key,
            ]
                .filter(Boolean)
                .join('+'),
        )
        .join(' ');
}

const isPrintableSymbol = (key: string): boolean =>
    key.length === 1 && key.toLowerCase() === key.toUpperCase();

export function comboMatches(combo: Combo, e: KeyLike): boolean {
    const key = e.key.toLowerCase();
    const keyOk =
        key === combo.key ||
        // Alt+letter yields another glyph on some layouts: fall back to the physical key.
        (combo.alt &&
            e.code !== undefined &&
            e.code.toLowerCase() === `key${combo.key}`);
    if (!keyOk) {
        return false;
    }

    if (combo.mod) {
        if (!(e.ctrlKey || e.metaKey)) {
            return false;
        }
    } else if (e.ctrlKey !== combo.ctrl || e.metaKey !== combo.meta) {
        return false;
    }
    if (e.altKey !== combo.alt) {
        return false;
    }
    // "?" needs Shift on most layouts: a symbol combo ignores Shift unless it names it.
    if (!combo.shift && isPrintableSymbol(combo.key)) {
        return true;
    }

    return e.shiftKey === combo.shift;
}

export type ChordDef = { sequence: Combo[] };

export type ChordResult<T extends ChordDef> =
    | { type: 'match'; def: T }
    | { type: 'pending' }
    | { type: 'none' };

/**
 * Feeds the events pressed so far (oldest first) against the registered sequences.
 * `match`: a sequence is complete. `pending`: some sequence still starts with the buffer.
 */
export function resolveChord<T extends ChordDef>(
    defs: readonly T[],
    buffer: readonly KeyLike[],
): ChordResult<T> {
    let pending = false;
    for (const def of defs) {
        if (def.sequence.length < buffer.length) {
            continue;
        }
        const prefixMatches = buffer.every((event, index) => {
            const combo = def.sequence[index];

            return combo !== undefined && comboMatches(combo, event);
        });
        if (!prefixMatches) {
            continue;
        }
        if (def.sequence.length === buffer.length) {
            return { type: 'match', def };
        }
        pending = true;
    }

    return pending ? { type: 'pending' } : { type: 'none' };
}

/** True when two sequences cannot both be live: equal, or one is a prefix of the other. */
export function sequencesConflict(
    a: readonly Combo[],
    b: readonly Combo[],
): boolean {
    const length = Math.min(a.length, b.length);
    for (let i = 0; i < length; i++) {
        const left = a[i];
        const right = b[i];
        if (!left || !right || normalizeCombo(left) !== normalizeCombo(right)) {
            return false;
        }
    }

    return true;
}

function normalizeCombo(c: Combo): string {
    return `${c.mod}|${c.ctrl}|${c.meta}|${c.alt}|${c.shift}|${c.key}`;
}

/** Display parts for <Kbd>: ["Ctrl", "K"], ["G", "S"] for chords handled by the caller. */
export function displayCombo(combo: Combo): string[] {
    const names: Record<string, string> = {
        arrowup: '↑',
        arrowdown: '↓',
        arrowleft: '←',
        arrowright: '→',
        escape: 'Esc',
        enter: 'Enter',
        delete: 'Del',
        ' ': 'Space',
        contextmenu: 'Menu',
    };
    const parts: string[] = [];
    if (combo.mod || combo.ctrl) {
        parts.push('Ctrl');
    }
    if (combo.meta) {
        parts.push('Cmd');
    }
    if (combo.alt) {
        parts.push('Alt');
    }
    if (combo.shift) {
        parts.push('Shift');
    }
    parts.push(names[combo.key] ?? combo.key.toUpperCase());

    return parts;
}
