import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import { Page, PageHeader } from '@/components/page-header';
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
            <Page>
                <PageHeader
                    title={title}
                    actions={
                        <>
                            {actions}
                            {onNew ? (
                                <Button type="button" onClick={onNew}>
                                    <Plus aria-hidden="true" />
                                    {newLabel ?? t('admin.add')}
                                </Button>
                            ) : null}
                        </>
                    }
                />
                {children}
            </Page>
        </>
    );
}
