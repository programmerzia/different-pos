/** Pure helpers behind the sign-in form (unit-tested in login.test.ts). */

/** A username or phone needs the business code; an email is unique across businesses. */
export function needsBusinessCode(identifier: string): boolean {
    const value = identifier.trim();

    return value !== '' && !value.includes('@');
}

/**
 * The wait in seconds from Fortify's throttle message ("Too many login attempts. Please try again in 42 seconds."),
 * in any language as long as it carries one number; null when there is none.
 */
export function parseThrottleSeconds(
    message: string | undefined | null,
): number | null {
    if (!message) {
        return null;
    }
    const match =
        /(\d+)\s*(?:seconds?|secs?|s\b|সেকেন্ড)/iu.exec(message) ??
        /(\d+)/u.exec(message);
    if (!match) {
        return null;
    }
    const seconds = Number.parseInt(match[1] as string, 10);

    return Number.isFinite(seconds) && seconds > 0 && seconds <= 3600
        ? seconds
        : null;
}

/** Whether a throttle message is what an error is (so the form shows a countdown instead of the raw text). */
export function isThrottleMessage(message: string | undefined | null): boolean {
    return !!message && /too many|অনেকবার|throttl/iu.test(message);
}

/** When a throttled sign-in may be retried (ms timestamp), or null when the error is no throttle or has no wait. */
export function lockUntil(
    message: string | undefined | null,
    now: number,
): number | null {
    if (!isThrottleMessage(message)) {
        return null;
    }
    const seconds = parseThrottleSeconds(message);

    return seconds === null ? null : now + seconds * 1000;
}

/** Caps Lock state from a keyboard or pointer event; false when the browser cannot tell. */
export function capsLockOn(event: {
    getModifierState?: (key: 'CapsLock') => boolean;
}): boolean {
    return (
        typeof event.getModifierState === 'function' &&
        event.getModifierState('CapsLock')
    );
}
