import { describe, expect, it } from 'vite-plus/test';
import {
    capsLockOn,
    isThrottleMessage,
    lockUntil,
    needsBusinessCode,
    parseThrottleSeconds,
} from './login';

describe('needsBusinessCode', () => {
    it('asks for the business only with a username or phone', () => {
        expect(needsBusinessCode('')).toBe(false);
        expect(needsBusinessCode('   ')).toBe(false);
        expect(needsBusinessCode('owner@demo.test')).toBe(false);
        expect(needsBusinessCode('cashier1')).toBe(true);
        expect(needsBusinessCode(' 01712-345678 ')).toBe(true);
    });
});

describe('parseThrottleSeconds', () => {
    it('reads the wait from the English and Bangla throttle messages', () => {
        expect(
            parseThrottleSeconds(
                'Too many login attempts. Please try again in 42 seconds.',
            ),
        ).toBe(42);
        expect(
            parseThrottleSeconds(
                'অনেকবার চেষ্টা করা হয়েছে। 17 সেকেন্ড পরে আবার চেষ্টা করুন।',
            ),
        ).toBe(17);
        expect(parseThrottleSeconds('Try again in 1 second')).toBe(1);
    });

    it('returns null without a usable number', () => {
        expect(parseThrottleSeconds(undefined)).toBeNull();
        expect(parseThrottleSeconds('')).toBeNull();
        expect(parseThrottleSeconds('Too many login attempts.')).toBeNull();
        expect(parseThrottleSeconds('Try again in 0 seconds')).toBeNull();
        expect(parseThrottleSeconds('Try again in 99999 seconds')).toBeNull();
    });
});

describe('throttle detection and lock', () => {
    it('recognises throttle messages only', () => {
        expect(
            isThrottleMessage(
                'Too many login attempts. Please try again in 5 seconds.',
            ),
        ).toBe(true);
        expect(isThrottleMessage('অনেকবার চেষ্টা করা হয়েছে।')).toBe(true);
        expect(
            isThrottleMessage('These credentials do not match our records.'),
        ).toBe(false);
        expect(isThrottleMessage(undefined)).toBe(false);
    });

    it('locks until now + the wait, and not at all for other errors', () => {
        const now = 1_000_000;
        expect(
            lockUntil(
                'Too many login attempts. Please try again in 30 seconds.',
                now,
            ),
        ).toBe(now + 30_000);
        expect(lockUntil('Too many login attempts.', now)).toBeNull();
        expect(
            lockUntil('These credentials do not match our records.', now),
        ).toBeNull();
    });
});

describe('capsLockOn', () => {
    it('reads the CapsLock modifier and tolerates events without it', () => {
        expect(
            capsLockOn({ getModifierState: (key) => key === 'CapsLock' }),
        ).toBe(true);
        expect(capsLockOn({ getModifierState: () => false })).toBe(false);
        expect(capsLockOn({})).toBe(false);
    });
});
