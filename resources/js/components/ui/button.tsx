import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * Mockup button recipes (tools/mockup/baseline): 32 px, rounded-lg, 12 px; primary = solid + semibold,
 * outline = the header "Columns / Export / Print" chip (surface, border, primary border on hover).
 * Coarse pointers keep a 44 px target.
 */
const buttonVariants = cva(
  "inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-lg text-xs font-medium transition-[color,background-color,border-color,box-shadow] cursor-pointer disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-3.5 [&_svg]:shrink-0 outline-none focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive [@media(pointer:coarse)]:min-h-11",
  {
    variants: {
      variant: {
        default:
          "bg-primary text-primary-foreground font-semibold shadow-xs hover:bg-primary-hover",
        destructive:
          "bg-destructive text-destructive-foreground font-semibold shadow-xs hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40",
        outline:
          "border border-border bg-surface text-muted-foreground hover:border-primary hover:text-foreground",
        secondary:
          "border border-border bg-surface-sunken text-foreground hover:bg-surface hover:border-primary",
        ghost: "text-muted-foreground hover:bg-surface-sunken hover:text-foreground",
        link: "text-primary underline-offset-4 hover:underline",
      },
      size: {
        default: "h-8 px-3 has-[>svg]:px-2.5",
        sm: "h-7 px-2.5 has-[>svg]:px-2",
        lg: "h-10 px-4 text-sm has-[>svg]:px-3.5",
        icon: "size-8 [@media(pointer:coarse)]:min-w-11",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

function Button({
  className,
  variant,
  size,
  asChild = false,
  ...props
}: React.ComponentProps<"button"> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean
  }) {
  const Comp = asChild ? Slot : "button"

  return (
    <Comp
      data-slot="button"
      className={cn(buttonVariants({ variant, size, className }))}
      {...props}
    />
  )
}

export { Button, buttonVariants }
