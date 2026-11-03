/**
 * The user manual's content model. Text is plain strings with two inline marks:
 * `**bold**` and `[[keys]]` (rendered as a keycap, e.g. `[[F9]]`, `[[Ctrl+K]]`, `[[G S]]`).
 * No HTML anywhere: the renderer splits the string and emits React nodes.
 */
export type Block =
    | { type: 'p'; text: string }
    | { type: 'steps'; items: string[] }
    | { type: 'bullets'; items: string[] }
    | { type: 'keys'; rows: { keys: string; label: string }[] }
    | { type: 'note'; tone: 'tip' | 'warning'; text: string }
    | { type: 'table'; head: string[]; rows: string[][] };

export type Section = {
    id: string;
    title: string;
    blocks: Block[];
};

export type Chapter = {
    id: string;
    title: string;
    /** One line under the title and in the table of contents. */
    summary: string;
    /** Shown only when the signed-in user has one of these permissions (none = everyone). */
    permission?: string | readonly string[];
    /** Sidebar nav group ids (command/nav.ts) that open this chapter from the footer link. */
    navIds?: readonly string[];
    sections: Section[];
};

export type Manual = {
    /** Which build of the app the text describes; shown in the page header. */
    describes: string;
    chapters: Chapter[];
};
