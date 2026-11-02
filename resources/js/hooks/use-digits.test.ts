import { describe, expect, it } from 'vite-plus/test';
import { makeDigits } from './use-digits';

describe('digits', () => {
    it('Latin by default', () => {
        const d = makeDigits(false);
        expect(d.n(1234)).toBe('1234');
        expect(d.money(12345650)).toBe('৳1,23,456.50');
        expect(d.qty(1500)).toBe('1.5');
    });

    it('Bangla digits when on', () => {
        const d = makeDigits(true);
        expect(d.n('2026-11-03')).toBe('২০২৬-১১-০৩');
        expect(d.money(12345650)).toBe('৳১,২৩,৪৫৬.৫০');
        expect(d.qty(2000)).toBe('২');
        expect(d.latin('১২৩')).toBe('123');
    });
});
