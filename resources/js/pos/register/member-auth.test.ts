import { describe, expect, it } from 'vite-plus/test';
import { saleBody } from './api';
import { cartReducer, emptyCart } from './cart';
import {
    memberAuthValue,
    memberStep,
    memberTokenFor,
    payBlockedByPin,
} from './member-auth';
import type { CustomerCard, MemberAuthState } from './types';

const member = (over: Partial<CustomerCard> = {}): CustomerCard => ({
    id: 9,
    uuid: 'c-9',
    number: '1000 0017',
    name: 'Rahim',
    name_bn: null,
    shop_name: null,
    phone: '+8801711223344',
    group: null,
    balance: 0,
    credit_limit: null,
    advance: 0,
    is_active: true,
    pin_status: 'set',
    ...over,
});

const verified: MemberAuthState = {
    kind: 'pin_online',
    customerId: 9,
    token: 'tok',
    expiresAt: 10_000,
};

describe('memberStep (02_AppFlow §4.4, online)', () => {
    it('does nothing for a walk-in, when the policy is off, or once this customer is verified', () => {
        expect(memberStep(null, 'required', null)).toEqual({ kind: 'none' });
        expect(memberStep(member(), 'off', null)).toEqual({ kind: 'none' });
        expect(memberStep(member(), 'required', verified)).toEqual({ kind: 'none' });
    });

    it('asks for the PIN when the member has one, offers to set one otherwise', () => {
        expect(memberStep(member(), 'optional', null)).toEqual({ kind: 'verify' });
        expect(memberStep(member({ pin_status: 'pending' }), 'optional', null)).toEqual({ kind: 'offer_set', skippable: true });
        expect(memberStep(member({ pin_status: 'pending' }), 'required', null)).toEqual({ kind: 'offer_set', skippable: false });
        expect(memberStep(member({ pin_status: 'locked' }), 'required', null)).toEqual({ kind: 'locked', skippable: false });
    });

    it('restarts for a different customer even with an old verification', () => {
        expect(memberStep(member({ id: 10 }), 'optional', verified)).toEqual({ kind: 'verify' });
    });
});

describe('memberAuthValue / payBlockedByPin / memberTokenFor', () => {
    it('mirrors the server: none, no_pin_policy, pin_online, override', () => {
        expect(memberAuthValue(null, 'required', null)).toBe('none');
        expect(memberAuthValue(member(), 'off', null)).toBe('no_pin_policy');
        expect(memberAuthValue(member(), 'optional', null)).toBe('none');
        expect(memberAuthValue(member(), 'optional', { kind: 'skipped', customerId: 9 })).toBe('none');
        expect(memberAuthValue(member(), 'required', verified, 5_000)).toBe('pin_online');
        expect(memberAuthValue(member(), 'required', verified, 20_000)).toBe('none');
        expect(memberAuthValue(member(), 'required', { kind: 'override', customerId: 9, managerName: 'Sumi' })).toBe('override');
    });

    it('blocks Pay only under the required policy without a verification or override', () => {
        expect(payBlockedByPin(member(), 'required', null)).toBe(true);
        expect(payBlockedByPin(member(), 'required', verified, 5_000)).toBe(false);
        expect(payBlockedByPin(member(), 'optional', null)).toBe(false);
        expect(payBlockedByPin(null, 'required', null)).toBe(false);
    });

    it('sends the member token only while valid and for the attached customer', () => {
        expect(memberTokenFor(member(), verified, 5_000)).toBe('tok');
        expect(memberTokenFor(member(), verified, 10_000)).toBeNull();
        expect(memberTokenFor(member({ id: 10 }), verified, 5_000)).toBeNull();
        expect(memberTokenFor(member(), { kind: 'skipped', customerId: 9 })).toBeNull();
    });
});

describe('cart member state', () => {
    it('keeps the verification for the same customer and drops it for another or the walk-in', () => {
        let state = cartReducer(emptyCart('u1'), { type: 'customer', customer: member() });
        state = cartReducer(state, { type: 'member', member: verified });
        expect(cartReducer(state, { type: 'customer', customer: member() }).member).toEqual(verified);
        expect(cartReducer(state, { type: 'customer', customer: member({ id: 10 }) }).member).toBeNull();
        expect(cartReducer(state, { type: 'customer', customer: null }).member).toBeNull();
        expect(cartReducer(state, { type: 'clear' }).member).toBeNull();
    });

    it('puts the member token on the sale body (never the PIN)', () => {
        let state = cartReducer(emptyCart('u1'), { type: 'customer', customer: member() });
        state = cartReducer(state, {
            type: 'member',
            member: { ...verified, expiresAt: Date.now() + 60_000 },
        });
        const body = saleBody(state, [], 0, null);
        expect(body.member_token).toBe('tok');
        expect(JSON.stringify(body)).not.toContain('pin');
        expect(saleBody(emptyCart('u2'), [], 0, null).member_token).toBeNull();
    });
});
