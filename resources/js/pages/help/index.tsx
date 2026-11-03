import { Head } from '@inertiajs/react';
import { BookOpen, Lightbulb, Search, TriangleAlert } from 'lucide-react';
import {
    Fragment,
    type ReactNode,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { EmptyState } from '@/components/empty-state';
import { Kbd } from '@/components/kbd';
import { NativeSelect } from '@/components/native-select';
import { Page, PageHeader, PrintButton } from '@/components/page-header';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';
import {
    type Block,
    type Chapter,
    chapterText,
    manualFor,
    type Section,
} from '@/manual';

/** `**bold**` and `[[keys]]` → nodes. No HTML is ever parsed. */
function inline(text: string): ReactNode {
    const parts = text.split(/(\*\*[^*]+\*\*|\[\[[^\]]+\]\])/g);

    return parts.map((part, i) => {
        if (part.startsWith('**') && part.endsWith('**')) {
            return (
                <strong key={i} className="font-semibold text-foreground">
                    {part.slice(2, -2)}
                </strong>
            );
        }
        if (part.startsWith('[[') && part.endsWith(']]')) {
            return (
                <Kbd
                    key={i}
                    keys={part.slice(2, -2)}
                    className="mx-0.5 align-baseline text-[10px] [@media(pointer:coarse)]:inline-flex"
                />
            );
        }

        return <Fragment key={i}>{part}</Fragment>;
    });
}

function BlockView({ block }: { block: Block }) {
    switch (block.type) {
        case 'p':
            return (
                <p className="text-sm leading-6 text-foreground/90">
                    {inline(block.text)}
                </p>
            );
        case 'steps':
            return (
                <ol className="list-decimal space-y-1.5 pl-6 text-sm leading-6 marker:font-semibold marker:text-primary">
                    {block.items.map((item, i) => (
                        <li key={i}>{inline(item)}</li>
                    ))}
                </ol>
            );
        case 'bullets':
            return (
                <ul className="list-disc space-y-1.5 pl-6 text-sm leading-6 marker:text-muted-foreground">
                    {block.items.map((item, i) => (
                        <li key={i}>{inline(item)}</li>
                    ))}
                </ul>
            );
        case 'keys':
            return (
                <ul className="divide-y rounded-lg border bg-surface">
                    {block.rows.map((row) => (
                        <li
                            key={row.keys + row.label}
                            className="flex min-h-9 items-center justify-between gap-4 px-3 py-1.5 text-sm"
                        >
                            <span>{inline(row.label)}</span>
                            <Kbd
                                keys={row.keys}
                                className="[@media(pointer:coarse)]:inline-flex"
                            />
                        </li>
                    ))}
                </ul>
            );
        case 'note': {
            const warning = block.tone === 'warning';
            const Icon = warning ? TriangleAlert : Lightbulb;

            return (
                <aside
                    className={cn(
                        'flex gap-2.5 rounded-lg border px-3 py-2.5 text-sm leading-6',
                        warning
                            ? 'border-warning/40 bg-warning-soft text-foreground'
                            : 'border-primary/30 bg-primary-soft text-foreground',
                    )}
                >
                    <Icon
                        className={cn(
                            'mt-1 size-4 shrink-0',
                            warning ? 'text-warning' : 'text-primary',
                        )}
                        aria-hidden="true"
                    />
                    <p>{inline(block.text)}</p>
                </aside>
            );
        }
        case 'table':
            return (
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-surface-sunken text-left text-xs font-semibold text-muted-foreground">
                            <tr>
                                {block.head.map((h) => (
                                    <th key={h} className="px-3 py-2">
                                        {inline(h)}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {block.rows.map((row, i) => (
                                <tr key={i} className="align-top">
                                    {row.map((cell, j) => (
                                        <td key={j} className="px-3 py-2">
                                            {inline(cell)}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            );
    }
}

function SectionView({
    chapter,
    section,
}: {
    chapter: Chapter;
    section: Section;
}) {
    return (
        <section
            id={`${chapter.id}--${section.id}`}
            aria-labelledby={`${chapter.id}--${section.id}-h`}
            className="scroll-mt-20 space-y-3"
        >
            <h3
                id={`${chapter.id}--${section.id}-h`}
                className="text-sm font-semibold text-foreground"
            >
                {section.title}
            </h3>
            {section.blocks.map((block, i) => (
                <BlockView key={i} block={block} />
            ))}
        </section>
    );
}

const normalize = (s: string) => s.toLocaleLowerCase();

export default function HelpIndex() {
    const { t, locale } = useT();
    const can = useCan();
    const manual = manualFor(locale);
    const chapters = useMemo(
        () => manual.chapters.filter((c) => can(c.permission)),
        [manual, can],
    );
    const [query, setQuery] = useState('');
    const [current, setCurrent] = useState<string>(() => {
        const hash =
            typeof window === 'undefined'
                ? ''
                : window.location.hash.replace(/^#/, '');

        return hash.split('--')[0] || '';
    });
    const searchRef = useRef<HTMLInputElement>(null);

    useHotkeys('page', [
        {
            id: 'help.search',
            keys: '/',
            labelKey: 'hotkeys.search',
            handler: () => searchRef.current?.focus(),
        },
    ]);

    // The first permitted chapter is the default; a hash picks the chapter (and scrolls to its section).
    const chapter =
        chapters.find((c) => c.id === current) ?? chapters[0] ?? null;

    useEffect(() => {
        const hash = window.location.hash.replace(/^#/, '');
        if (!hash) {
            return;
        }
        const target = document.getElementById(hash);
        target?.scrollIntoView({ block: 'start' });
    }, [chapter?.id]);

    useEffect(() => {
        const onHash = () => {
            const hash = window.location.hash.replace(/^#/, '');
            setCurrent(hash.split('--')[0] ?? '');
        };
        window.addEventListener('hashchange', onHash);

        return () => window.removeEventListener('hashchange', onHash);
    }, []);

    const trimmed = normalize(query.trim());
    const hits = useMemo(() => {
        if (trimmed.length < 2) {
            return null;
        }

        return chapters.flatMap((c) =>
            c.sections
                .filter((s) =>
                    normalize(chapterText({ ...c, sections: [s] })).includes(
                        trimmed,
                    ),
                )
                .map((s) => ({ chapter: c, section: s })),
        );
    }, [chapters, trimmed]);

    const pick = (id: string) => {
        setCurrent(id);
        window.history.replaceState(null, '', `#${id}`);
        document
            .getElementById('help-content')
            ?.scrollTo({ top: 0, behavior: 'instant' });
    };

    return (
        <>
            <Head title={t('help.title')} />
            <Page fill className="gap-3">
                <PageHeader
                    className="border-b pb-3 print:hidden"
                    title={t('help.title')}
                    subtitle={t('help.subtitle', {
                        describes: manual.describes,
                    })}
                    actions={
                        <>
                            <div className="relative">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    ref={searchRef}
                                    id="page-search"
                                    type="search"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder={t('help.search')}
                                    aria-label={t('help.search')}
                                    className="h-8 w-56 pl-8 text-xs"
                                />
                                <Kbd
                                    keys="/"
                                    className="absolute top-1/2 right-2 -translate-y-1/2 text-[10px]"
                                />
                            </div>
                            <PrintButton />
                        </>
                    }
                />

                <div className="flex min-h-0 flex-1 flex-col gap-3 md:flex-row">
                    {/* Table of contents: a select on small screens, a sticky list from md. */}
                    <nav
                        aria-label={t('help.contents')}
                        className="shrink-0 md:w-60 print:hidden"
                    >
                        <NativeSelect
                            aria-label={t('help.contents')}
                            className="h-9 w-full text-sm md:hidden"
                            value={chapter?.id ?? ''}
                            onChange={(e) => pick(e.target.value)}
                        >
                            {chapters.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.title}
                                </option>
                            ))}
                        </NativeSelect>
                        <ol className="hidden max-h-full gap-0.5 overflow-y-auto rounded-xl border bg-surface p-2 md:flex md:flex-col">
                            {chapters.map((c, i) => {
                                const active = c.id === chapter?.id && !hits;

                                return (
                                    <li key={c.id}>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setQuery('');
                                                pick(c.id);
                                            }}
                                            aria-current={
                                                active ? 'true' : undefined
                                            }
                                            className={cn(
                                                'flex w-full items-start gap-2 rounded-lg px-2.5 py-1.5 text-left text-xs',
                                                active
                                                    ? 'bg-primary font-semibold text-primary-foreground'
                                                    : 'text-foreground hover:bg-surface-sunken',
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    'num mt-px w-4 shrink-0 font-mono text-[10px]',
                                                    active
                                                        ? 'text-primary-foreground/80'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {i + 1}
                                            </span>
                                            <span className="min-w-0">
                                                <span className="block truncate">
                                                    {c.title}
                                                </span>
                                                <span
                                                    className={cn(
                                                        'block truncate text-[11px] font-normal',
                                                        active
                                                            ? 'text-primary-foreground/80'
                                                            : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {c.summary}
                                                </span>
                                            </span>
                                        </button>
                                        {active && c.sections.length > 1 ? (
                                            <ul className="my-1 ml-6 space-y-0.5 border-l pl-2">
                                                {c.sections.map((s) => (
                                                    <li key={s.id}>
                                                        <a
                                                            href={`#${c.id}--${s.id}`}
                                                            className="block truncate rounded px-1.5 py-0.5 text-[11px] text-muted-foreground hover:bg-surface-sunken hover:text-foreground"
                                                        >
                                                            {s.title}
                                                        </a>
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : null}
                                    </li>
                                );
                            })}
                        </ol>
                    </nav>

                    <div
                        id="help-content"
                        className="print-area min-h-0 flex-1 overflow-y-auto rounded-xl border bg-surface p-4 md:p-6"
                    >
                        {hits ? (
                            hits.length === 0 ? (
                                <EmptyState
                                    icon={Search}
                                    title={t('help.no_results')}
                                    description={t('help.no_results_hint')}
                                />
                            ) : (
                                <div className="space-y-6">
                                    <p className="text-xs text-muted-foreground">
                                        {t('help.results', {
                                            count: hits.length,
                                        })}
                                    </p>
                                    {hits.map(({ chapter: c, section }) => (
                                        <article
                                            key={`${c.id}--${section.id}`}
                                            className="space-y-2"
                                        >
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setQuery('');
                                                    pick(c.id);
                                                    window.history.replaceState(
                                                        null,
                                                        '',
                                                        `#${c.id}--${section.id}`,
                                                    );
                                                }}
                                                className="text-[11px] font-semibold tracking-wider text-primary uppercase hover:underline"
                                            >
                                                {c.title}
                                            </button>
                                            <SectionView
                                                chapter={c}
                                                section={section}
                                            />
                                        </article>
                                    ))}
                                </div>
                            )
                        ) : chapter ? (
                            <article className="mx-auto max-w-3xl space-y-8">
                                <header className="space-y-1 border-b pb-4">
                                    <p className="flex items-center gap-1.5 text-[11px] font-semibold tracking-wider text-primary uppercase">
                                        <BookOpen
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                        {t('help.title')}
                                    </p>
                                    <h2 className="text-xl font-bold text-foreground">
                                        {chapter.title}
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {chapter.summary}
                                    </p>
                                </header>
                                {chapter.sections.map((section) => (
                                    <SectionView
                                        key={section.id}
                                        chapter={chapter}
                                        section={section}
                                    />
                                ))}
                            </article>
                        ) : (
                            <EmptyState title={t('help.no_results')} />
                        )}
                    </div>
                </div>
            </Page>
        </>
    );
}
