import { describe, expect, it } from 'vitest';
import { NAV } from '@/command/nav';
import { bn } from './bn';
import { en } from './en';
import { chapterText, helpHref } from './index';
import type { Chapter } from './types';

const shape = (chapters: Chapter[]) =>
    chapters.map((c) => ({
        id: c.id,
        permission: c.permission,
        navIds: c.navIds,
        sections: c.sections.map((s) => ({
            id: s.id,
            blocks: s.blocks.map((b) => b.type),
        })),
    }));

describe('user manual', () => {
    it('EN and BN have the same chapters, sections and block structure', () => {
        expect(shape(bn.chapters)).toEqual(shape(en.chapters));
    });

    it('has unique chapter and section ids', () => {
        const ids = en.chapters.map((c) => c.id);
        expect(new Set(ids).size).toBe(ids.length);
        for (const chapter of en.chapters) {
            const sectionIds = chapter.sections.map((s) => s.id);
            expect(new Set(sectionIds).size).toBe(sectionIds.length);
        }
    });

    it('has no empty text in either language', () => {
        for (const manual of [en, bn]) {
            for (const chapter of manual.chapters) {
                expect(chapterText(chapter)).not.toMatch(/\n\n|^\s*$/);
            }
        }
    });

    it('points every navIds entry at a real sidebar group', () => {
        const groups = new Set(NAV.map((g) => g.id));
        for (const chapter of en.chapters) {
            for (const id of chapter.navIds ?? []) {
                expect(groups.has(id)).toBe(true);
            }
        }
    });

    it('resolves the footer link to the chapter for the current page', () => {
        expect(helpHref('pos')).toBe('/app/help#pos');
        expect(helpHref(null)).toBe('/app/help');
        expect(helpHref('nope')).toBe('/app/help');
    });

    it('uses only balanced inline marks', () => {
        for (const manual of [en, bn]) {
            for (const chapter of manual.chapters) {
                const text = chapterText(chapter);
                expect((text.match(/\*\*/g) ?? []).length % 2).toBe(0);
                expect((text.match(/\[\[/g) ?? []).length).toBe(
                    (text.match(/\]\]/g) ?? []).length,
                );
            }
        }
    });
});
