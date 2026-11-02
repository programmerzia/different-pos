import type { ReactNode } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useHotkeyScope } from '@/hotkeys/use-hotkeys';
import { cn } from '@/lib/utils';

/** The register's modal frame: makes `dialog` the live hotkey scope while open, Esc closes. */
export function PosDialog({
    open,
    onClose,
    title,
    description,
    className,
    children,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    description?: string | undefined;
    className?: string;
    children: ReactNode;
}) {
    useHotkeyScope('dialog', open);

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent
                className={cn('max-h-[90svh] overflow-y-auto', className)}
            >
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription
                    className={description ? undefined : 'sr-only'}
                >
                    {description ?? title}
                </DialogDescription>
                {children}
            </DialogContent>
        </Dialog>
    );
}
