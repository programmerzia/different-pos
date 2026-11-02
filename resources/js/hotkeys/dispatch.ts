import {
    resolveChord,
    type ChordResult,
    type Combo,
    type KeyLike,
} from './matcher';
import type { RegisteredHotkey } from './registry';

export const CHORD_TIMEOUT_MS = 1000;

export type TargetInfo = {
    /** The focused element is a text field (input, textarea, select, contenteditable). */
    typing: boolean;
};

const hasCommandModifier = (c: Combo): boolean =>
    c.mod || c.ctrl || c.meta || c.alt;
const isFunctionKey = (key: string): boolean => /^f([1-9]|1[0-2])$/.test(key);

/**
 * "Shortcuts never fire while typing in an input, except Esc, F-keys, Ctrl+… and Enter"
 * (02_AppFlow §1.3). Enter only when the hotkey opts in via allowInInput.
 */
export function mayFireWhileTyping(def: RegisteredHotkey): boolean {
    if (def.allowInInput === true) {
        return true;
    }
    const first = def.sequence[0];
    if (!first || def.sequence.length > 1) {
        return false;
    }

    return (
        hasCommandModifier(first) ||
        first.key === 'escape' ||
        isFunctionKey(first.key)
    );
}

export type Dispatch =
    | { type: 'fire'; def: RegisteredHotkey; buffer: KeyLike[] }
    | { type: 'pending'; buffer: KeyLike[] }
    | { type: 'none'; buffer: KeyLike[] };

/**
 * One key event → what to do. `buffer` is the chord progress so far (empty when idle); the caller
 * owns the timer that clears it after CHORD_TIMEOUT_MS.
 */
export function dispatchKey(
    active: readonly RegisteredHotkey[],
    buffer: readonly KeyLike[],
    event: KeyLike,
    target: TargetInfo,
): Dispatch {
    const usable = target.typing ? active.filter(mayFireWhileTyping) : active;

    const attempt = (events: KeyLike[]): ChordResult<RegisteredHotkey> =>
        resolveChord(usable, events);

    let events = [...buffer, event];
    let result = attempt(events);
    if (result.type === 'none' && buffer.length > 0) {
        // The chord broke: treat this key as the start of a new sequence.
        events = [event];
        result = attempt(events);
    }

    if (result.type === 'match') {
        return { type: 'fire', def: result.def, buffer: [] };
    }
    if (result.type === 'pending') {
        return { type: 'pending', buffer: events };
    }

    return { type: 'none', buffer: [] };
}
