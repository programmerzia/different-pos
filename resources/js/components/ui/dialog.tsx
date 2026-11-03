import * as DialogPrimitive from "@radix-ui/react-dialog"
import { type LucideIcon, XIcon } from "lucide-react"
import * as React from "react"

import { cn } from "@/lib/utils"

function Dialog({
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Root>) {
  return <DialogPrimitive.Root data-slot="dialog" {...props} />
}

function DialogTrigger({
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Trigger>) {
  return <DialogPrimitive.Trigger data-slot="dialog-trigger" {...props} />
}

function DialogPortal({
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Portal>) {
  return <DialogPrimitive.Portal data-slot="dialog-portal" {...props} />
}

function DialogClose({
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Close>) {
  return <DialogPrimitive.Close data-slot="dialog-close" {...props} />
}

function DialogOverlay({
  className,
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Overlay>) {
  return (
    <DialogPrimitive.Overlay
      data-slot="dialog-overlay"
      className={cn(
        "data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/60 backdrop-blur-xs",
        className
      )}
      {...props}
    />
  )
}

/** Mockup modal widths (icons_and_helpers.py `Modal` sizeMap). */
const DIALOG_SIZE = {
  sm: "sm:max-w-sm",
  md: "sm:max-w-md",
  lg: "sm:max-w-lg",
  xl: "sm:max-w-xl",
  "2xl": "sm:max-w-2xl",
  "3xl": "sm:max-w-3xl",
  "4xl": "sm:max-w-4xl",
} as const

export type DialogSize = keyof typeof DIALOG_SIZE

/**
 * `layout="panel"` is the mockup modal: no padding of its own, compose it from `<DialogBar>` (header with
 * title, subtitle, close), `<DialogBody>` (scrolls) and `<DialogActions>` (sticky footer with key hints).
 * The default layout keeps the older padded shadcn look so existing dialogs are unchanged.
 */
function DialogContent({
  className,
  children,
  size,
  layout = "default",
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Content> & {
  size?: DialogSize
  layout?: "default" | "panel"
}) {
  const panel = layout === "panel"

  return (
    <DialogPortal data-slot="dialog-portal">
      <DialogOverlay />
      <DialogPrimitive.Content
        data-slot="dialog-content"
        data-layout={layout}
        className={cn(
          "data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 fixed top-[50%] left-[50%] z-50 w-full max-w-[calc(100%-2rem)] translate-x-[-50%] translate-y-[-50%] border duration-100",
          panel
            ? "bg-surface-raised text-popover-foreground flex max-h-[calc(100svh-2rem)] flex-col overflow-hidden rounded-2xl text-xs shadow-2xl"
            : "bg-background grid gap-4 rounded-lg p-6 shadow-lg",
          size ? DIALOG_SIZE[size] : panel ? DIALOG_SIZE.md : "sm:max-w-lg",
          className
        )}
        {...props}
      >
        {children}
        {panel ? null : (
          <DialogPrimitive.Close className="ring-offset-background focus:ring-ring data-[state=open]:bg-accent data-[state=open]:text-muted-foreground absolute top-4 right-4 rounded-xs opacity-70 transition-opacity hover:opacity-100 focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:pointer-events-none [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4">
            <XIcon />
            <span className="sr-only">Close</span>
          </DialogPrimitive.Close>
        )}
      </DialogPrimitive.Content>
    </DialogPortal>
  )
}

/** Mockup modal header: sunken bar, optional icon tile, 14 px bold title + badge, 11 px subtitle, close (Esc). */
function DialogBar({
  title,
  subtitle,
  icon: Icon,
  badge,
  closeLabel = "Close",
  className,
}: {
  title: React.ReactNode
  subtitle?: React.ReactNode
  icon?: LucideIcon
  badge?: React.ReactNode
  /** Accessible name of the close button (pass the translated "Close"). */
  closeLabel?: string
  className?: string
}) {
  return (
    <div
      data-slot="dialog-bar"
      className={cn(
        "bg-surface-sunken flex shrink-0 items-center justify-between gap-3 border-b p-3.5 select-none",
        className
      )}
    >
      <div className="flex min-w-0 items-center gap-2">
        {Icon ? (
          <div className="bg-primary-soft text-primary flex size-6 shrink-0 items-center justify-center rounded-lg">
            <Icon className="size-3.5" aria-hidden="true" />
          </div>
        ) : null}
        <div className="min-w-0">
          <DialogPrimitive.Title className="text-foreground flex items-center gap-2 text-sm font-bold">
            <span className="truncate">{title}</span>
            {badge ? (
              <span className="bg-primary-soft text-primary rounded-sm px-1.5 font-mono text-[10px] font-bold">
                {badge}
              </span>
            ) : null}
          </DialogPrimitive.Title>
          {subtitle ? (
            <DialogPrimitive.Description className="text-muted-foreground mt-0.5 text-[11px]">
              {subtitle}
            </DialogPrimitive.Description>
          ) : null}
        </div>
      </div>
      <DialogPrimitive.Close
        className="text-muted-foreground hover:text-foreground hover:bg-surface flex size-7 shrink-0 items-center justify-center rounded-lg transition-colors [@media(pointer:coarse)]:size-11"
        aria-label={closeLabel}
        title={`${closeLabel} (Esc)`}
      >
        <XIcon className="size-4" aria-hidden="true" />
      </DialogPrimitive.Close>
    </div>
  )
}

/** Mockup modal body: 16 px padding, 12 px rhythm, scrolls between the bar and the actions. */
function DialogBody({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="dialog-body"
      className={cn("min-h-0 flex-1 space-y-3 overflow-y-auto p-4", className)}
      {...props}
    />
  )
}

/** Mockup modal footer: pinned, surface, right-aligned actions; `hints` (key caps) sit on the left. */
function DialogActions({
  hints,
  className,
  children,
  ...props
}: React.ComponentProps<"div"> & { hints?: React.ReactNode }) {
  return (
    <div
      data-slot="dialog-actions"
      className={cn(
        "bg-surface flex shrink-0 flex-wrap items-center justify-end gap-2 border-t p-3",
        className
      )}
      {...props}
    >
      {hints ? (
        <div className="text-muted-foreground mr-auto flex flex-wrap items-center gap-2 text-[11px]">
          {hints}
        </div>
      ) : null}
      {children}
    </div>
  )
}

function DialogHeader({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="dialog-header"
      className={cn("flex flex-col gap-2 text-center sm:text-left", className)}
      {...props}
    />
  )
}

function DialogFooter({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="dialog-footer"
      className={cn(
        "flex flex-col-reverse gap-2 sm:flex-row sm:justify-end",
        className
      )}
      {...props}
    />
  )
}

function DialogTitle({
  className,
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Title>) {
  return (
    <DialogPrimitive.Title
      data-slot="dialog-title"
      className={cn("text-lg leading-none font-semibold", className)}
      {...props}
    />
  )
}

function DialogDescription({
  className,
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Description>) {
  return (
    <DialogPrimitive.Description
      data-slot="dialog-description"
      className={cn("text-muted-foreground text-sm", className)}
      {...props}
    />
  )
}

export {
  Dialog,
  DialogActions,
  DialogBar,
  DialogBody,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogOverlay,
  DialogPortal,
  DialogTitle,
  DialogTrigger,
}
