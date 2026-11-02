import { describe, expect, it } from 'vite-plus/test';
import { paisaFromText, sanitizeMoneyText, textFromPaisa } from './money-input';

describe('money input parsing', () => {
    it('keeps digits and one dot, at most two decimals', () => {
        expect(sanitizeMoneyText('12a3.456.7')).toBe('123.45');
        expect(sanitizeMoneyText('1,234')).toBe('1234');
        expect(sanitizeMoneyText('-5')).toBe('5');
        expect(sanitizeMoneyText('..')).toBe('.');
    });

    it('accepts Bangla digits', () => {
        expect(sanitizeMoneyText('১২৩.৫০')).toBe('123.50');
        expect(paisaFromText('১২৩.৫')).toBe(12350);
    });

    it('converts to integer paisa without floats', () => {
        expect(paisaFromText('0.1')).toBe(10);
        expect(paisaFromText('1234.50')).toBe(123450);
        expect(paisaFromText('1.')).toBe(100);
        expect(paisaFromText('90071992547409.91')).toBe(9007199254740991);
    });

    it('empty or lone dot is null, overflow is null (never a wrong number)', () => {
        expect(paisaFromText('')).toBeNull();
        expect(paisaFromText('.')).toBeNull();
        expect(paisaFromText('99999999999999999')).toBeNull();
    });

    it('editing text round-trips', () => {
        expect(textFromPaisa(null)).toBe('');
        expect(textFromPaisa(123450)).toBe('1234.50');
        expect(textFromPaisa(5)).toBe('0.05');
        expect(paisaFromText(textFromPaisa(987654))).toBe(987654);
    });
});
