import { describe, expect, it } from 'vite-plus/test';
import { detectPreset, presetRange } from './date-range';
import { pinReducer } from './pin';
import { stepQty } from './qty-step';

describe('stepQty (milli-units)', () => {
    it('steps and clamps with integers', () => {
        expect(stepQty(1000, 1000)).toBe(2000);
        expect(stepQty(1000, -2000, { min: 0 })).toBe(0);
        expect(stepQty(9500, 1000, { max: 10000 })).toBe(10000);
        expect(stepQty(250, 250)).toBe(500);
    });
});

describe('date range presets', () => {
    const today = new Date(2026, 10, 3); // 3 Nov 2026
    it('computes the five presets', () => {
        expect(presetRange('today', today)).toEqual({
            from: '2026-11-03',
            to: '2026-11-03',
        });
        expect(presetRange('last7', today)).toEqual({
            from: '2026-10-28',
            to: '2026-11-03',
        });
        expect(presetRange('thisMonth', today)).toEqual({
            from: '2026-11-01',
            to: '2026-11-03',
        });
        expect(presetRange('lastMonth', today)).toEqual({
            from: '2026-10-01',
            to: '2026-10-31',
        });
    });
    it('handles year boundaries and detects presets', () => {
        expect(presetRange('lastMonth', new Date(2027, 0, 15))).toEqual({
            from: '2026-12-01',
            to: '2026-12-31',
        });
        expect(
            detectPreset({ from: '2026-11-03', to: '2026-11-03' }, today),
        ).toBe('today');
        expect(
            detectPreset({ from: '2026-11-02', to: '2026-11-03' }, today),
        ).toBe('custom');
    });
});

describe('pin reducer', () => {
    it('accepts digits up to the limit, backspace and clear', () => {
        let pin = '';
        for (const d of '1234567') {
            pin = pinReducer(pin, { type: 'digit', digit: d });
        }
        expect(pin).toBe('123456');
        expect(pinReducer(pin, { type: 'back' })).toBe('12345');
        expect(pinReducer(pin, { type: 'clear' })).toBe('');
        expect(pinReducer('1', { type: 'digit', digit: 'a' })).toBe('1');
    });
});
