import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Dialog,
    DialogBar,
    DialogBody,
    DialogContent,
} from '@/components/ui/dialog';
import { useHotkeyScope } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

/**
 * The register's modal frame, the mockup `Modal`: sunken header bar (icon tile, title, subtitle,
 * close), a scrolling body. Makes `dialog` the live hotkey scope while open; Esc closes.
 */
export function PosDialog({
    open,
    onClose,
    title,
    description,
    icon,
    className,
    bodyClassName,
    children,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    description?: string | undefined;
    icon?: LucideIcon;
    className?: string;
    bodyClassName?: string;
    children: ReactNode;
}) {
    const { t } = useT();
    useHotkeyScope('dialog', open);

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent
                layout="panel"
                className={className}
                onOpenAutoFocus={(e) => {
                    // Mockup Modal: the first field takes the focus, not the header's close button.
                    const panel = e.currentTarget as HTMLElement;
                    if (panel.contains(document.activeElement)) {
                        e.preventDefault();

                        return;
                    }
                    const field = panel.querySelector<HTMLElement>(
                        '[data-slot="dialog-body"] :is(input, select, textarea):not([disabled])',
                    );
                    if (field) {
                        e.preventDefault();
                        field.focus();
                    }
                }}
                {...(description ? {} : { 'aria-describedby': undefined })}
            >
                <DialogBar
                    title={title}
                    subtitle={description}
                    {...(icon ? { icon } : {})}
                    closeLabel={t('pos.close')}
                />
                <DialogBody className={cn('text-sm', bodyClassName)}>
                    {children}
                </DialogBody>
            </DialogContent>
        </Dialog>
    );
}
