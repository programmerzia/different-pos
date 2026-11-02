import { describe, expect, it } from 'vite-plus/test';
import {
    comboMatches,
    normalizeKeys,
    parseCombo,
    parseSequence,
    resolveChord,
    type KeyLike,
} from './matcher';

const key = (k: string, mods: Partial<KeyLike> = {}): KeyLike => ({
    key: k,
    ctrlKey: false,
    metaKey: false,
    altKey: false,
    shiftKey: false,
    ...mods,
});

describe('combo matching', () => {
    it('mod matches Ctrl or Cmd', () => {
        const combo = parseCombo('mod+k');
        expect(comboMatches(combo, key('k', { ctrlKey: true }))).toBe(true);
        expect(comboMatches(combo, key('K', { metaKey: true }))).toBe(true);
        expect(comboMatches(combo, key('k'))).toBe(false);
    });

    it('a bare letter does not match when a modifier is held', () => {
        expect(comboMatches(parseCombo('n'), key('n'))).toBe(true);
        expect(comboMatches(parseCombo('n'), key('n', { ctrlKey: true }))).toBe(
            false,
        );
        expect(
            comboMatches(parseCombo('n'), key('N', { shiftKey: true })),
        ).toBe(false);
    });

    it('"?" ignores the Shift needed to type it; letters do not', () => {
        expect(
            comboMatches(parseCombo('?'), key('?', { shiftKey: true })),
        ).toBe(true);
        expect(comboMatches(parseCombo('?'), key('?'))).toBe(true);
    });

    it('shift+f10, alt+b and arrows', () => {
        expect(
            comboMatches(
                parseCombo('shift+f10'),
                key('F10', { shiftKey: true }),
            ),
        ).toBe(true);
        expect(comboMatches(parseCombo('shift+f10'), key('F10'))).toBe(false);
        expect(
            comboMatches(parseCombo('alt+b'), key('b', { altKey: true })),
        ).toBe(true);
        expect(
            comboMatches(
                parseCombo('alt+arrowleft'),
                key('ArrowLeft', { altKey: true }),
            ),
        ).toBe(true);
    });

    it('parses aliases, "," and a literal plus', () => {
        expect(parseCombo('esc').key).toBe('escape');
        expect(parseCombo(',').key).toBe(',');
        expect(parseCombo('ctrl++').key).toBe('+');
        expect(() => parseCombo('hyper+k')).toThrow();
    });

    it('normalises equivalent spellings', () => {
        expect(normalizeKeys('Shift+F10')).toBe(normalizeKeys('shift+f10'));
        expect(normalizeKeys('g  s')).toBe('g s');
    });
});

describe('chords', () => {
    const defs = [
        { id: 'sales', sequence: parseSequence('g s') },
        { id: 'customers', sequence: parseSequence('g c') },
        { id: 'new', sequence: parseSequence('n') },
    ];

    it('G then S matches; G alone is pending', () => {
        expect(resolveChord(defs, [key('g')])).toEqual({ type: 'pending' });
        const done = resolveChord(defs, [key('g'), key('s')]);
        expect(done.type === 'match' && done.def.id).toBe('sales');
    });

    it('single keys match immediately; unknown keys match nothing', () => {
        const hit = resolveChord(defs, [key('n')]);
        expect(hit.type === 'match' && hit.def.id).toBe('new');
        expect(resolveChord(defs, [key('z')])).toEqual({ type: 'none' });
        expect(resolveChord(defs, [key('g'), key('z')])).toEqual({
            type: 'none',
        });
    });
});
