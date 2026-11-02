const KEY = 'palette.recents';
const MAX = 6;

export type Recent = { id: string; label: string; href: string };

export function readRecents(
    storage: Pick<Storage, 'getItem'> = localStorage,
): Recent[] {
    try {
        const parsed: unknown = JSON.parse(storage.getItem(KEY) ?? '[]');

        return Array.isArray(parsed)
            ? parsed.filter(
                  (r): r is Recent =>
                      typeof r === 'object' &&
                      r !== null &&
                      typeof (r as Recent).id === 'string' &&
                      typeof (r as Recent).href === 'string',
              )
            : [];
    } catch {
        return [];
    }
}

/** Most recent first, de-duplicated, capped. */
export function pushRecent(
    recent: Recent,
    storage: Pick<Storage, 'getItem' | 'setItem'> = localStorage,
): Recent[] {
    const next = [
        recent,
        ...readRecents(storage).filter((r) => r.id !== recent.id),
    ].slice(0, MAX);
    try {
        storage.setItem(KEY, JSON.stringify(next));
    } catch {
        // Storage full or blocked: recents are a nicety.
    }

    return next;
}
