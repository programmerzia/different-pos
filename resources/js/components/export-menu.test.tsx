import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { ExportMenu } from '@/components/page-header';
import '@/i18n';
import { exportItems } from '@/lib/export-items';

afterEach(cleanup);

describe('ExportMenu', () => {
    it('renders nothing without items', () => {
        const { container } = render(<ExportMenu items={[]} />);
        expect(container.innerHTML).toBe('');
    });

    it('opens with Excel, PDF and CSV entries and reports the chosen format', async () => {
        const onSelect = vi.fn();
        render(<ExportMenu items={exportItems((k) => k, onSelect)} />);

        const trigger = screen.getByRole('button', {
            name: /table\.export|Export/,
        });
        expect(trigger.className).toContain('touch-target');
        trigger.focus();
        fireEvent.keyDown(trigger, { key: 'Enter' });

        const labels = (await screen.findAllByRole('menuitem')).map((item) =>
            item.textContent?.trim(),
        );
        expect(labels).toEqual([
            'table.export_xlsx',
            'table.export_pdf',
            'table.export_csv',
        ]);

        fireEvent.click(
            screen.getByRole('menuitem', { name: 'table.export_pdf' }),
        );
        expect(onSelect).toHaveBeenCalledWith('pdf');
    });
});
