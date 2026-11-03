import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import { i18n } from '@/i18n';
import { usePreferencesStore } from '@/lib/preferences';
import type { CardBrand, CardMember } from './card-face';
import { code128, MembershipCard } from './membership-card';

const brand: CardBrand = { name: 'Different Fashion', logo_url: null, phone: '01711-223344', branch: 'Uttara', bin: null };
const member: CardMember = {
    uuid: 'u1',
    name: 'Rahim Uddin',
    name_bn: 'রহিম উদ্দিন',
    shop_name: 'Rahim Store',
    phone: null,
    customer_no: '1000 0017',
    member_since: '2026-01-05',
    group: 'VIP',
    card: { id: 4, code: 'MC1000001701', status: 'active', issue_no: 1, print_count: 0, printed_at: null },
};

afterEach(async () => {
    cleanup();
    await i18n.changeLanguage('en');
});

describe('MembershipCard', () => {
    it('shows the number grouped 4 + 4, the holder, the tier and the business name on the front', () => {
        render(<MembershipCard member={member} brand={brand} />);
        expect(screen.getByTestId('card-number').textContent).toBe('1000 0017');
        expect(screen.getByText('Rahim Uddin')).toBeTruthy();
        expect(screen.getByText('রহিম উদ্দিন')).toBeTruthy();
        expect(screen.getByText('Different Fashion')).toBeTruthy();
        expect(screen.getByText('Platinum VIP')).toBeTruthy();
        expect(screen.getByText('SINCE: 01/26')).toBeTruthy();
        expect(screen.queryByRole('img')).toBeNull();
    });

    it('adds the tenant logo when there is one', () => {
        const { container } = render(<MembershipCard member={member} brand={{ ...brand, logo_url: '/storage/logo.png' }} />);
        expect(container.querySelector('img.mc-logo')?.getAttribute('src')).toBe('/storage/logo.png');
    });

    it('shows the barcode, its code, the CID and the helpline on the back', () => {
        const { container } = render(<MembershipCard member={member} brand={brand} side="back" />);
        expect(screen.getByText('MC1000001701')).toBeTruthy();
        expect(screen.getByText('017')).toBeTruthy();
        expect(container.querySelectorAll('.mc-barcode rect').length).toBeGreaterThan(10);
        expect(container.querySelector('.mc-terms')?.textContent).toContain('Helpline: 01711-223344');
    });

    it('uses Bangla digits when the language and the preference say so', async () => {
        await i18n.changeLanguage('bn');
        usePreferencesStore.setState({ bn_digits: true });
        render(<MembershipCard member={member} brand={brand} />);
        expect(screen.getByTestId('card-number').textContent).toBe('১০০০ ০০১৭');
    });

    it('encodes Code128 bars', () => {
        const bits = code128('MC1000001701');
        expect(bits).toMatch(/^11010010000/);
        expect(bits.endsWith('1100011101011')).toBe(true);
    });
});
