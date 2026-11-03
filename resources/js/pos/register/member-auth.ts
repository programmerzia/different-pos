import type { CustomerCard, MemberAuthState } from './types';

export type PinPolicy = 'off' | 'optional' | 'required';
export type PinStatus = 'set' | 'pending' | 'locked';

/** The register's PIN step for a just-attached customer (02_AppFlow §4.4, online part). */
export type MemberStep =
    /** Walk-in, policy off, or already verified: nothing to do. */
    | { kind: 'none' }
    /** The member has a PIN: show the customer pad. */
    | { kind: 'verify' }
    /** No PIN yet: offer to set one (optional: skipping allowed). */
    | { kind: 'offer_set'; skippable: boolean }
    /** Locked: only a manager override (or waiting) gets past. */
    | { kind: 'locked'; skippable: boolean };

export function memberStep(
    customer: CustomerCard | null,
    policy: PinPolicy,
    member: MemberAuthState | null,
): MemberStep {
    if (customer === null || policy === 'off') {
        return { kind: 'none' };
    }
    if (member !== null && member.customerId === customer.id) {
        return { kind: 'none' };
    }
    const skippable = policy === 'optional';
    switch (customer.pin_status) {
        case 'set':
            return { kind: 'verify' };
        case 'locked':
            return { kind: 'locked', skippable };
        default:
            return { kind: 'offer_set', skippable };
    }
}

/** What the sale will record as member_auth; the server decides for real (CompleteSale::memberAuth). */
export function memberAuthValue(
    customer: CustomerCard | null,
    policy: PinPolicy,
    member: MemberAuthState | null,
    now: number = Date.now(),
): 'none' | 'pin_online' | 'override' | 'no_pin_policy' {
    if (customer === null) {
        return 'none';
    }
    if (policy === 'off') {
        return 'no_pin_policy';
    }
    if (member === null || member.customerId !== customer.id) {
        return 'none';
    }
    if (member.kind === 'pin_online') {
        return member.expiresAt > now ? 'pin_online' : 'none';
    }
    if (member.kind === 'override') {
        return 'override';
    }

    return 'none';
}

/** Policy `required` blocks Pay until the member is verified or overridden. */
export function payBlockedByPin(
    customer: CustomerCard | null,
    policy: PinPolicy,
    member: MemberAuthState | null,
    now: number = Date.now(),
): boolean {
    return (
        policy === 'required' &&
        customer !== null &&
        memberAuthValue(customer, policy, member, now) === 'none'
    );
}

/** The member token to send with the sale, if one is still valid for this customer. */
export function memberTokenFor(
    customer: CustomerCard | null,
    member: MemberAuthState | null,
    now: number = Date.now(),
): string | null {
    if (
        customer === null ||
        member === null ||
        member.customerId !== customer.id ||
        member.kind !== 'pin_online' ||
        member.expiresAt <= now
    ) {
        return null;
    }

    return member.token;
}
