import type { Locale } from '@/i18n';
import { bn } from './bn';
import { en } from './en';
import type { Chapter, Manual } from './types';

export type { Block, Chapter, Manual, Section } from './types';

export const HELP_PATH = '/app/help';

export function manualFor(locale: Locale): Manual {
    return locale === 'bn' ? bn : en;
}

/** `/app/help#pos`: the chapter that documents a sidebar nav group, else the manual's start. */
export function helpHref(navGroupId?: string | null): string {
    if (!navGroupId) {
        return HELP_PATH;
    }
    const chapter = en.chapters.find((c) => c.navIds?.includes(navGroupId));

    return chapter ? `${HELP_PATH}#${chapter.id}` : HELP_PATH;
}

/** Every string of a chapter joined, for the search box. */
export function chapterText(chapter: Chapter): string {
    const parts: string[] = [chapter.title, chapter.summary];
    for (const section of chapter.sections) {
        parts.push(section.title);
        for (const block of section.blocks) {
            switch (block.type) {
                case 'p':
                case 'note':
                    parts.push(block.text);
                    break;
                case 'steps':
                case 'bullets':
                    parts.push(...block.items);
                    break;
                case 'keys':
                    parts.push(...block.rows.flatMap((r) => [r.keys, r.label]));
                    break;
                case 'table':
                    parts.push(...block.head, ...block.rows.flat());
                    break;
            }
        }
    }

    return parts.join('\n');
}
