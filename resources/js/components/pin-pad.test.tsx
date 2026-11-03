import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { PinPad, pinKeyDigit } from './pin-pad';

afterEach(cleanup);

const filled = () =>
    document.querySelectorAll('[data-filled="true"]').length;

describe('PinPad', () => {
    it('never renders the digits: dots count them, the status tells the count', () => {
        const onSubmit = vi.fn();
        render(<PinPad onSubmit={onSubmit} />);
        fireEvent.click(screen.getByRole('button', { name: '4' }));
        fireEvent.click(screen.getByRole('button', { name: '8' }));
        fireEvent.click(screen.getByRole('button', { name: '2' }));
        expect(filled()).toBe(3);
        expect(screen.getByRole('status').textContent).toBe('3 digits entered');
        expect(document.body.textContent).not.toContain('482');
        // Fewer than four digits cannot be confirmed.
        expect((screen.getByRole('button', { name: /Confirm/ }) as HTMLButtonElement).disabled).toBe(true);
        fireEvent.click(screen.getByRole('button', { name: /Confirm/ }));
        expect(onSubmit).not.toHaveBeenCalled();
    });

    it('takes the keyboard (digits, Backspace, Enter), caps at six and submits once', () => {
        const onSubmit = vi.fn();
        render(<PinPad onSubmit={onSubmit} />);
        const pad = screen.getByLabelText('PIN pad', { selector: 'input' });
        for (const key of ['1', '2', '3', '4', '5', '6', '7']) {
            fireEvent.keyDown(pad, { key });
        }
        expect(filled()).toBe(6);
        fireEvent.keyDown(pad, { key: 'Backspace' });
        expect(filled()).toBe(5);
        fireEvent.keyDown(pad, { key: 'Enter' });
        expect(onSubmit).toHaveBeenCalledWith('12345');
        // Cleared after submit: nothing lingers on screen.
        expect(filled()).toBe(0);
    });

    it('clears with C / Delete, shows the error and success texts, and accepts Bangla digits', () => {
        const onSubmit = vi.fn();
        const { rerender } = render(<PinPad onSubmit={onSubmit} error="Wrong PIN! Attempts left: 4" />);
        expect(screen.getByRole('status').textContent).toBe('Wrong PIN! Attempts left: 4');
        const pad = screen.getByLabelText('PIN pad', { selector: 'input' });
        fireEvent.keyDown(pad, { key: '৭' });
        fireEvent.keyDown(pad, { key: '৭' });
        expect(filled()).toBe(2);
        fireEvent.click(screen.getByRole('button', { name: 'Clear' }));
        expect(filled()).toBe(0);
        rerender(<PinPad onSubmit={onSubmit} success="PIN Verified Successfully!" />);
        expect(screen.getByRole('status').textContent).toBe('PIN Verified Successfully!');
        expect(pinKeyDigit('৩')).toBe('3');
        expect(pinKeyDigit('a')).toBeNull();
    });

    it('is inert while busy', () => {
        const onSubmit = vi.fn();
        render(<PinPad onSubmit={onSubmit} busy />);
        fireEvent.click(screen.getByRole('button', { name: '4' }));
        expect(filled()).toBe(0);
    });
});
