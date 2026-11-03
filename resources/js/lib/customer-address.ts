import type { CustomerDetail } from '@/types/customers';

export type AddressStatus = 'verified' | 'unverified' | 'none';

/**
 * The profile's address badge. The tick needs both an address and location_verified (the column
 * defaults to true, so a customer without any address must not look verified); an address that was
 * auto-parsed from legacy text (location_verified false) is "not verified".
 */
export function addressStatus(
    c: Pick<
        CustomerDetail,
        'address_line' | 'area' | 'post_code' | 'location_verified'
    >,
): AddressStatus {
    const has = [c.address_line, c.area, c.post_code].some(
        (v) => (v ?? '').trim() !== '',
    );
    if (!has) {
        return 'none';
    }

    return c.location_verified === true ? 'verified' : 'unverified';
}
