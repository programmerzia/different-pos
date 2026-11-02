import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';

/** Page chrome for the settings screens: title, an optional "New" button (also the `n` key) and the content. */
export function AdminPage({
    title,
    onNew,
    newLabel,
    actions,
    children,
}: {
    title: string;
    onNew?: () => void;
    newLabel?: string;
    actions?: ReactNode;
    children: ReactNode;
}) {
    const { t } = useT();
    useHotkeys('list', [
        {
            id: 'list.new',
            keys: 'n',
            labelKey: 'hotkeys.new',
            handler: () => onNew?.(),
        },
    ]);

    return (
        <>
            <Head title={title} />
            <div className="flex flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="mr-auto text-2xl font-bold">{title}</h1>
                    {actions}
                    {onNew ? (
                        <Button
                            type="button"
                            className="min-h-11 md:min-h-9"
                            onClick={onNew}
                        >
                            <Plus aria-hidden="true" />
                            {newLabel ?? t('admin.add')}
                        </Button>
                    ) : null}
                </div>
                {children}
            </div>
        </>
    );
}
