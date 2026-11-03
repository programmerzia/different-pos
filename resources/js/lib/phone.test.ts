import { describe, expect, it } from 'vitest';
import { displayPhone } from './phone';

describe('displayPhone', () => {
    it('shows a stored BD mobile number in the local 01XXX-XXXXXX form', () => {
        expect(displayPhone('+8801711234567')).toBe('01711-234567');
        expect(displayPhone('8801819838641')).toBe('01819-838641');
        expect(displayPhone('01711234567')).toBe('01711-234567');
    });

    it('leaves anything else unchanged and blanks a missing number', () => {
        expect(displayPhone('+44 20 7946 0958')).toBe('+44 20 7946 0958');
        expect(displayPhone('+8801211234567')).toBe('+8801211234567');
        expect(displayPhone(null)).toBe('');
        expect(displayPhone('')).toBe('');
    });
});
