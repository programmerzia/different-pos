import type { LucideIcon } from 'lucide-react';
import { useSyncExternalStore } from 'react';
import type { Can } from '@/hooks/use-can';

/**
 * ONE action registry behind row buttons, the row context menu and the command palette.
 * `entity: null` = a global command (palette only); `'*'` = every table row; anything else
 * (`'product'`, `'customer'`, `'sale'`) = rows of that entity.
 * Actions the user may not perform are hidden, not disabled (02_AppFlow §1.2).
 */
export type ActionContext<Row = unknown> = {
    row: Row;
    /** Where "Open" goes, supplied by the surface (table row, palette item). */
    href?: string | undefined;
    navigate: (url: string, options?: { newTab?: boolean }) => void;
    /** Rows currently selected (bulk), when the surface has a selection. */
    selection?: readonly Row[];
    /** Surface-provided implementations for generic actions (edit, duplicate, delete ...). */
    handlers?: Handlers<Row> | undefined;
};

export type Handlers<Row = unknown> = Partial<
    Record<string, (row: Row, selection?: readonly Row[]) => void>
>;

/** What a surface (table row, palette item) can do, used to hide generic actions it cannot back. */
export type Surface<Row = unknown> = {
    href?: string | undefined;
    handlers?: Handlers<Row> | undefined;
};

export type AppAction<Row = unknown> = {
    id: string;
    entity: string | null;
    /** i18n key. */
    labelKey: string;
    icon?: LucideIcon;
    /** Key hint shown in menus/palette; a global command's keys are also bound by the shell. */
    keys?: string;
    /** Any one grants. Omitted = any signed-in user. */
    permission?: string | readonly string[];
    /** `copy` actions are grouped into a "Copy ▸" submenu; `danger` ones come last, in red. */
    group?: 'primary' | 'copy' | 'danger' | 'canvas';
    visible?: (row: Row) => boolean;
    /** Generic actions only appear when the surface can back them: with a detail `href`, or a handler under this id. */
    requires?: 'href' | 'handler';
    /** Navigation target for global commands. */
    href?: string;
    run?: (context: ActionContext<Row>) => void;
};

type Listener = () => void;

export class ActionRegistry {
    private actions = new Map<string, AppAction<never>>();
    private listeners = new Set<Listener>();
    private version = 0;

    register<Row>(...list: AppAction<Row>[]): () => void {
        for (const action of list) {
            this.actions.set(action.id, action as unknown as AppAction<never>);
        }
        this.emit();

        return () => {
            list.forEach((action) => this.actions.delete(action.id));
            this.emit();
        };
    }

    /** Row actions for an entity, permission-filtered and ordered: primary, copy, danger last. */
    forEntity<Row>(
        entity: string,
        can: Can,
        row?: Row,
        surface: Surface<Row> = {},
    ): AppAction<Row>[] {
        const order = { primary: 0, canvas: 0, copy: 1, danger: 2 } as const;

        return [...this.actions.values()]
            .filter((a) => a.entity === entity || a.entity === '*')
            .filter((a) => can(a.permission))
            .filter((a) => a.requires !== 'href' || surface.href !== undefined)
            .filter(
                (a) =>
                    a.requires !== 'handler' ||
                    surface.handlers?.[a.id] !== undefined,
            )
            .filter(
                (a) =>
                    row === undefined || !a.visible || a.visible(row as never),
            )
            .sort(
                (a, b) =>
                    order[a.group ?? 'primary'] - order[b.group ?? 'primary'],
            )
            .map((a) => a as unknown as AppAction<Row>);
    }

    /** Global commands for the palette. */
    commands(can: Can): AppAction[] {
        return [...this.actions.values()]
            .filter((a) => a.entity === null && can(a.permission))
            .map((a) => a as unknown as AppAction);
    }

    get(id: string): AppAction | undefined {
        return this.actions.get(id) as unknown as AppAction | undefined;
    }

    subscribe = (listener: Listener): (() => void) => {
        this.listeners.add(listener);

        return () => this.listeners.delete(listener);
    };

    getVersion = (): number => this.version;

    private emit(): void {
        this.version++;
        this.listeners.forEach((l) => l());
    }
}

export const actions = new ActionRegistry();

/** Re-renders when actions are (un)registered. */
export function useActionVersion(): number {
    return useSyncExternalStore(
        actions.subscribe,
        actions.getVersion,
        actions.getVersion,
    );
}
