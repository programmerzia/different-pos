import * as ContextMenuPrimitive from '@radix-ui/react-context-menu';
import { CheckIcon, ChevronRightIcon } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

const ContextMenu = ContextMenuPrimitive.Root;
const ContextMenuTrigger = ContextMenuPrimitive.Trigger;
const ContextMenuSub = ContextMenuPrimitive.Sub;

const itemBase =
    'relative flex min-h-(--menu-item-h) cursor-default items-center gap-2 rounded-sm px-2 py-1 text-sm outline-none select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[highlighted]:bg-primary-soft data-[highlighted]:text-foreground [&_svg]:size-4 [&_svg]:shrink-0 [&_svg]:text-muted-foreground [@media(pointer:coarse)]:min-h-11';

function ContextMenuContent({
    className,
    ...props
}: React.ComponentProps<typeof ContextMenuPrimitive.Content>) {
    return (
        <ContextMenuPrimitive.Portal>
            <ContextMenuPrimitive.Content
                className={cn(
                    'z-50 min-w-55 overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
                    className,
                )}
                {...props}
            />
        </ContextMenuPrimitive.Portal>
    );
}

function ContextMenuItem({
    className,
    destructive = false,
    ...props
}: React.ComponentProps<typeof ContextMenuPrimitive.Item> & {
    destructive?: boolean;
}) {
    return (
        <ContextMenuPrimitive.Item
            className={cn(
                itemBase,
                destructive &&
                    'text-destructive data-[highlighted]:bg-danger-soft data-[highlighted]:text-destructive [&_svg]:text-destructive',
                className,
            )}
            {...props}
        />
    );
}

function ContextMenuCheckboxItem({
    className,
    children,
    ...props
}: React.ComponentProps<typeof ContextMenuPrimitive.CheckboxItem>) {
    return (
        <ContextMenuPrimitive.CheckboxItem
            className={cn(itemBase, 'pl-8', className)}
            {...props}
        >
            <span className="absolute left-2 flex size-4 items-center justify-center">
                <ContextMenuPrimitive.ItemIndicator>
                    <CheckIcon />
                </ContextMenuPrimitive.ItemIndicator>
            </span>
            {children}
        </ContextMenuPrimitive.CheckboxItem>
    );
}

function ContextMenuSubTrigger({
    className,
    children,
    ...props
}: React.ComponentProps<typeof ContextMenuPrimitive.SubTrigger>) {
    return (
        <ContextMenuPrimitive.SubTrigger
            className={cn(itemBase, 'data-[state=open]:bg-primary-soft', className)}
            {...props}
        >
            {children}
            <ChevronRightIcon className="ml-auto" />
        </ContextMenuPrimitive.SubTrigger>
    );
}

function ContextMenuSubContent({
    className,
    ...props
}: React.ComponentProps<typeof ContextMenuPrimitive.SubContent>) {
    return (
        <ContextMenuPrimitive.SubContent
            className={cn(
                'z-50 min-w-45 overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=open]:fade-in-0',
                className,
            )}
            {...props}
        />
    );
}

function ContextMenuSeparator({
    className,
    ...props
}: React.ComponentProps<typeof ContextMenuPrimitive.Separator>) {
    return (
        <ContextMenuPrimitive.Separator
            className={cn('-mx-1 my-1 h-px bg-border', className)}
            {...props}
        />
    );
}

function ContextMenuLabel({
    className,
    ...props
}: React.ComponentProps<typeof ContextMenuPrimitive.Label>) {
    return (
        <ContextMenuPrimitive.Label
            className={cn('px-2 py-1 text-xs font-medium text-muted-foreground', className)}
            {...props}
        />
    );
}

export {
    ContextMenu,
    ContextMenuCheckboxItem,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuLabel,
    ContextMenuSeparator,
    ContextMenuSub,
    ContextMenuSubContent,
    ContextMenuSubTrigger,
    ContextMenuTrigger,
};
