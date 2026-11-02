import { Copy } from 'lucide-react';
import {
    Fragment,
    type KeyboardEvent,
    type ReactElement,
    useMemo,
} from 'react';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuSub,
    ContextMenuSubContent,
    ContextMenuSubTrigger,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import { Kbd } from '@/components/kbd';
import {
    actions,
    useActionVersion,
    type ActionContext,
    type AppAction,
    type Handlers,
} from '@/command/actions';
import { navigateTo } from '@/command/navigate';
import { useCan } from '@/hooks/use-can';
import { useT } from '@/i18n';

/** Opens the menu for a focused element: the same event a right-click would raise. */
export function openContextMenuAt(element: HTMLElement): void {
    const rect = element.getBoundingClientRect();
    element.dispatchEvent(
        new MouseEvent('contextmenu', {
            bubbles: true,
            cancelable: true,
            clientX: rect.left + Math.min(rect.width / 2, 120),
            clientY: rect.top + rect.height / 2,
        }),
    );
}

export function isContextMenuKey(event: {
    key: string;
    shiftKey: boolean;
    ctrlKey: boolean;
    altKey: boolean;
}): boolean {
    return (
        event.key === 'ContextMenu' ||
        (event.key === 'F10' &&
            event.shiftKey &&
            !event.ctrlKey &&
            !event.altKey)
    );
}

type Props<Row> = {
    entity: string;
    row: Row;
    /** Detail page of the row ("Open"). */
    href?: string | undefined;
    handlers?: Handlers<Row> | undefined;
    selection?: readonly Row[] | undefined;
    /** One focusable element: a <tr> or a card. Receives Shift+F10 / the Menu key / long-press. */
    children: ReactElement;
};

/**
 * Radix context menu fed by the shared action registry (the same actions as the palette and row
 * buttons). Hidden, never disabled, when the user lacks permission. Not used inside text inputs.
 */
export function RowContextMenu<Row>({
    entity,
    row,
    href,
    handlers,
    selection,
    children,
}: Props<Row>) {
    const { t } = useT();
    const can = useCan();
    const version = useActionVersion();

    const items = useMemo(
        () => actions.forEntity<Row>(entity, can, row, { href, handlers }),
        // `version` changes whenever actions are (un)registered.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [entity, can, row, href, handlers, version],
    );

    const context: ActionContext<Row> = {
        row,
        href,
        navigate: (url, options) => navigateTo(url, options ?? {}),
        ...(selection ? { selection } : {}),
        handlers,
    };

    const run = (action: AppAction<Row>) => action.run?.(context);

    const onKeyDown = (event: KeyboardEvent<HTMLElement>) => {
        if (isContextMenuKey(event)) {
            event.preventDefault();
            event.stopPropagation();
            openContextMenuAt(event.currentTarget);
        }
    };

    if (items.length === 0) {
        return children;
    }

    const primary = items.filter(
        (a) => (a.group ?? 'primary') === 'primary' || a.group === 'canvas',
    );
    const copy = items.filter((a) => a.group === 'copy');
    const danger = items.filter((a) => a.group === 'danger');

    const renderItem = (action: AppAction<Row>) => (
        <ContextMenuItem
            key={action.id}
            destructive={action.group === 'danger'}
            onSelect={() => run(action)}
        >
            {action.icon ? <action.icon aria-hidden="true" /> : null}
            <span>{t(action.labelKey)}</span>
            {action.keys ? (
                <Kbd keys={action.keys} className="ml-auto" />
            ) : null}
        </ContextMenuItem>
    );

    return (
        <ContextMenu>
            <ContextMenuTrigger asChild onKeyDown={onKeyDown}>
                {children}
            </ContextMenuTrigger>
            <ContextMenuContent aria-label={t('actions.menu')}>
                {primary.map(renderItem)}
                {copy.length > 0 && (
                    <Fragment>
                        {primary.length > 0 && <ContextMenuSeparator />}
                        <ContextMenuSub>
                            <ContextMenuSubTrigger>
                                <Copy aria-hidden="true" />
                                {t('actions.copy')}
                            </ContextMenuSubTrigger>
                            <ContextMenuSubContent>
                                {copy.map(renderItem)}
                            </ContextMenuSubContent>
                        </ContextMenuSub>
                    </Fragment>
                )}
                {danger.length > 0 && (
                    <Fragment>
                        <ContextMenuSeparator />
                        {danger.map(renderItem)}
                    </Fragment>
                )}
            </ContextMenuContent>
        </ContextMenu>
    );
}
