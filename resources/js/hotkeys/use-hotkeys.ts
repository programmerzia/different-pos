import { useEffect, useRef } from 'react';
import { hotkeys, type HotkeyDef, type HotkeyScope } from './registry';

export type HotkeySpec = Omit<HotkeyDef, 'scope'>;

/**
 * Registers a page's (or component's) hotkeys under `scope` and makes that scope the live one while
 * mounted. Handlers may close over fresh state: only the id/keys list decides re-registration.
 *
 *   useHotkeys('list', [{ id: 'list.new', keys: 'n', labelKey: 'hotkeys.new', handler: openNew }]);
 */
export function useHotkeys(
    scope: HotkeyScope,
    specs: readonly HotkeySpec[],
): void {
    const latest = useRef(specs);
    useEffect(() => {
        latest.current = specs;
    });

    const signature = specs
        .map(
            (s) =>
                `${s.id}|${s.keys}|${s.allowInInput ?? ''}|${s.inDialog ?? ''}|${s.display ?? ''}`,
        )
        .join(';');

    useEffect(() => {
        const popScope =
            scope === 'global' ? () => undefined : hotkeys.pushScope(scope);
        const offs = latest.current.map((spec, index) =>
            hotkeys.register({
                ...spec,
                scope,
                handler: (event) => latest.current[index]?.handler(event),
            }),
        );

        return () => {
            offs.forEach((off) => off());
            popScope();
        };
        // `signature` stands for the specs' identity; handlers are read through `latest`.
    }, [scope, signature]);
}

/** Marks a scope live (e.g. an open dialog) without registering anything. */
export function useHotkeyScope(scope: HotkeyScope, enabled = true): void {
    useEffect(() => {
        if (!enabled) {
            return;
        }

        return hotkeys.pushScope(scope);
    }, [scope, enabled]);
}
