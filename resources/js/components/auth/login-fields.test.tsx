import {
    act,
    cleanup,
    fireEvent,
    render,
    screen,
} from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vite-plus/test';
import { i18n } from '@/i18n';
import { LoginFields, type RememberedBusiness } from '@/pages/auth/login';

vi.mock('@inertiajs/react', () => ({
    Link: ({
        children,
        href,
        ...props
    }: {
        children: React.ReactNode;
        href: unknown;
    }) => (
        <a href={typeof href === 'string' ? href : '#'} {...props}>
            {children}
        </a>
    ),
    Head: () => null,
    usePage: () => ({ props: { name: 'App', tenant: null } }),
    router: { visit: vi.fn() },
}));

const business: RememberedBusiness = {
    code: 'different-fashion',
    name: 'Different Fashion',
    logo_url: null,
    tagline: null,
};

function renderFields(props: Partial<Parameters<typeof LoginFields>[0]> = {}) {
    return render(
        <form>
            <LoginFields
                processing={false}
                errors={{}}
                business={null}
                canResetPassword
                {...props}
            />
        </form>,
    );
}

const slot = () => screen.getByTestId('business-slot');
const identifier = () => screen.getByLabelText(i18n.t('login.identifier'));

beforeAll(async () => {
    // jsdom has no ResizeObserver; the Radix checkbox measures itself with one.
    globalThis.ResizeObserver ??= class {
        observe() {}
        unobserve() {}
        disconnect() {}
    } as unknown as typeof ResizeObserver;
    await i18n.changeLanguage('en');
});

afterEach(() => {
    cleanup();
    vi.useRealTimers();
});

describe('login form', () => {
    it('opens the business field only for a username or phone', () => {
        renderFields();
        const field = document.getElementById('business') as HTMLInputElement;
        expect(slot().dataset.open).toBe('false');
        expect(field.disabled).toBe(true);

        fireEvent.change(identifier(), {
            target: { value: 'owner@demo.test' },
        });
        expect(slot().dataset.open).toBe('false');

        fireEvent.change(identifier(), { target: { value: 'cashier1' } });
        expect(slot().dataset.open).toBe('true');
        expect(field.disabled).toBe(false);
        expect(field.required).toBe(true);

        fireEvent.change(identifier(), { target: { value: '' } });
        expect(slot().dataset.open).toBe('false');
    });

    it('shows the remembered business with a Change button that reveals the code field', () => {
        renderFields({ business });
        fireEvent.change(identifier(), { target: { value: 'cashier1' } });
        expect(screen.getByText('Different Fashion')).toBeTruthy();
        const hidden = document.querySelector(
            'input[type="hidden"][name="business"]',
        ) as HTMLInputElement;
        expect(hidden.value).toBe('different-fashion');

        fireEvent.click(screen.getByRole('button', { name: /Change/ }));
        const field = document.getElementById('business') as HTMLInputElement;
        expect(field.defaultValue).toBe('different-fashion');
        expect(document.activeElement).toBe(field);
    });

    it('warns while Caps Lock is on in the password field', () => {
        renderFields();
        const password = document.getElementById(
            'password',
        ) as HTMLInputElement;
        const warning = screen.getByTestId('caps-lock');
        expect(warning.textContent).toBe('');

        fireEvent.keyDown(password, {
            key: 'A',
            modifierCapsLock: true,
        });
        expect(warning.textContent).toContain(i18n.t('login.caps_lock'));
        expect(password.getAttribute('aria-describedby')).toContain(
            'caps-lock',
        );

        fireEvent.keyUp(password, { key: 'a', modifierCapsLock: false });
        expect(warning.textContent).toBe('');
    });

    it('lists errors in the alert summary and inline', () => {
        renderFields({
            errors: { login: 'These credentials do not match our records.' },
        });
        const summary = screen.getByRole('alert');
        expect(summary.textContent).toContain(i18n.t('login.error_summary'));
        expect(summary.textContent).toContain(
            'These credentials do not match our records.',
        );
        expect(document.getElementById('login-error')?.textContent).toBe(
            'These credentials do not match our records.',
        );
        expect(identifier().getAttribute('aria-invalid')).toBe('true');
    });

    it('counts down a throttle and keeps the submit disabled until it ends', () => {
        vi.useFakeTimers();
        const now = Date.now();
        renderFields({
            errors: {
                login: 'Too many login attempts. Please try again in 3 seconds.',
            },
            lockedUntil: now + 3000,
        });
        const submit = screen.getByRole('button', { name: /Try again in 3 s/ });
        expect((submit as HTMLButtonElement).disabled).toBe(true);
        expect(document.getElementById('login-error')).toBeNull();
        expect(screen.getByRole('alert').textContent).toContain(
            i18n.t('login.throttled'),
        );

        act(() => {
            vi.advanceTimersByTime(1000);
        });
        expect(
            screen.getByRole('button', { name: /Try again in 2 s/ }),
        ).toBeTruthy();

        act(() => {
            vi.advanceTimersByTime(2500);
        });
        const ready = screen.getByRole('button', {
            name: i18n.t('login.submit'),
        }) as HTMLButtonElement;
        expect(ready.disabled).toBe(false);
        expect(screen.getByRole('alert').textContent).toContain(
            i18n.t('login.throttle_over'),
        );
    });

    it('disables the submit with a spinner while signing in', () => {
        renderFields({ processing: true });
        const submit = screen.getByRole('button', {
            name: new RegExp(i18n.t('login.signing_in')),
        }) as HTMLButtonElement;
        expect(submit.disabled).toBe(true);
    });
});
