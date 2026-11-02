import { useEffect, useRef } from 'react';
import { CHORD_TIMEOUT_MS, dispatchKey } from './dispatch';
import type { KeyLike } from './matcher';
import { hotkeys } from './registry';

function isTypingTarget(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }
    if (target.isContentEditable) {
        return true;
    }

    return ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);
}

/** Mount once in the app shell: the single keydown listener behind the registry. */
export function HotkeyHost() {
    const buffer = useRef<KeyLike[]>([]);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        const reset = () => {
            buffer.current = [];
            if (timer.current) {
                clearTimeout(timer.current);
                timer.current = null;
            }
        };

        const onKeyDown = (event: KeyboardEvent) => {
            // Bare modifier presses and IME composition never trigger anything.
            if (
                event.isComposing ||
                ['Shift', 'Control', 'Alt', 'Meta'].includes(event.key)
            ) {
                return;
            }

            const result = dispatchKey(
                hotkeys.active(),
                buffer.current,
                event,
                {
                    typing: isTypingTarget(event.target),
                },
            );

            if (timer.current) {
                clearTimeout(timer.current);
                timer.current = null;
            }
            buffer.current = result.buffer;

            if (result.type === 'pending') {
                timer.current = setTimeout(reset, CHORD_TIMEOUT_MS);

                return;
            }
            if (result.type === 'fire') {
                event.preventDefault();
                result.def.handler(event);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => {
            window.removeEventListener('keydown', onKeyDown);
            reset();
        };
    }, []);

    return null;
}
