import { describe, expect, it, vi } from 'vite-plus/test';
import {
    creditUse,
    presetOf,
    presetRange,
    rowOpenHandlers,
} from '@/components/sales/list-tools';

const now = new Date(2026, 10, 3, 15, 0); // 3 Nov 2026, local

describe('date presets', () => {
    it('maps each preset to an inclusive local range', () => {
        expect(presetRange('today', now)).toEqual({
            from: '2026-11-03',
            to: '2026-11-03',
        });
        expect(presetRange('yesterday', now)).toEqual({
            from: '2026-11-02',
            to: '2026-11-02',
        });
        // Tuesday 3 Nov: the week started on Saturday 31 Oct (not a rolling 7 days).
        expect(presetRange('week', now)).toEqual({
            from: '2026-10-31',
            to: '2026-11-03',
        });
        expect(presetRange('week', new Date(2026, 9, 31, 9, 0))).toEqual({
            from: '2026-10-31',
            to: '2026-10-31',
        });
        expect(presetRange('week', new Date(2026, 10, 6, 9, 0)).from).toBe(
            '2026-10-31',
        );
        expect(presetRange('month', now)).toEqual({
            from: '2026-11-01',
            to: '2026-11-03',
        });
        expect(presetRange('', now)).toEqual({ from: '', to: '' });
    });

    it('recognises a range as its preset, anything else as custom', () => {
        expect(presetOf('', '', now)).toBe('');
        expect(presetOf('2026-11-03', '2026-11-03', now)).toBe('today');
        expect(presetOf('2026-11-01', '2026-11-03', now)).toBe('month');
        expect(presetOf('2026-01-01', '2026-02-01', now)).toBe('custom');
        expect(presetOf('2026-01-01', '', now)).toBe('custom');
    });
});

describe('creditUse', () => {
    it('is the rounded share of the limit, clamped to 0–100', () => {
        expect(creditUse(450_000, 500_000)).toBe(90);
        expect(creditUse(900_000, 500_000)).toBe(100);
        expect(creditUse(-10, 500_000)).toBe(0);
    });

    it('is null without a limit; a zero limit is full once anything is owed', () => {
        expect(creditUse(100, null)).toBeNull();
        expect(creditUse(100, 0)).toBe(100);
        expect(creditUse(0, 0)).toBe(0);
    });
});

describe('rowOpenHandlers', () => {
    const table = () => {
        document.body.innerHTML =
            '<table><tbody><tr id="a"><td><span id="cell">A</span><button id="btn">x</button></td></tr><tr id="b"><td>B</td></tr></tbody><tfoot><tr id="f"><td>T</td></tr></tfoot></table>';

        return (id: string) => document.getElementById(id) as HTMLElement;
    };

    it('opens the clicked row but not on controls or the totals row', () => {
        const el = table();
        const open = vi.fn();
        const { onClick } = rowOpenHandlers(['row-a', 'row-b'], open);
        const click = (target: HTMLElement) =>
            onClick({ target } as unknown as Parameters<typeof onClick>[0]);

        click(el('cell'));
        click(el('b'));
        click(el('btn'));
        click(el('f'));
        expect(open.mock.calls).toEqual([['row-a'], ['row-b']]);
    });

    it('takes plain Enter on a focused row and stops the table navigating', () => {
        const el = table();
        const open = vi.fn();
        const { onKeyDownCapture } = rowOpenHandlers(['row-a', 'row-b'], open);
        const stop = vi.fn();
        const press = (key: string, ctrlKey = false) =>
            onKeyDownCapture({
                key,
                ctrlKey,
                metaKey: false,
                shiftKey: false,
                altKey: false,
                target: el('b'),
                preventDefault: vi.fn(),
                stopPropagation: stop,
            } as unknown as Parameters<typeof onKeyDownCapture>[0]);

        press('Enter');
        press('Enter', true);
        press('ArrowDown');
        expect(open.mock.calls).toEqual([['row-b']]);
        expect(stop).toHaveBeenCalledTimes(1);
    });
});
