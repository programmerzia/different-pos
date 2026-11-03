import { describe, expect, it } from 'vite-plus/test';
import { addressStatus } from '@/lib/customer-address';

const blank = { address_line: null, area: null, post_code: null };

describe('addressStatus', () => {
    it('never shows a verified tick without an address', () => {
        expect(addressStatus({ ...blank, location_verified: true })).toBe(
            'none',
        );
        expect(
            addressStatus({ ...blank, area: '  ', location_verified: true }),
        ).toBe('none');
    });

    it('ticks only a verified address', () => {
        expect(
            addressStatus({
                ...blank,
                area: 'Gulshan',
                location_verified: true,
            }),
        ).toBe('verified');
        expect(
            addressStatus({
                ...blank,
                address_line: 'Road 5',
                location_verified: false,
            }),
        ).toBe('unverified');
    });
});
