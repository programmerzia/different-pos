import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import { Page, PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { useInSettingsHub } from '@/layouts/settings/layout';

/**
 * Page chrome for the settings screens: title, an optional "New" button (also the `n` key) and the content.
 * Inside the Settings hub it is the hub's content-pane section (mockup: 16 px bold title, 12 px subtitle,
 * primary action on the right, max-w-4xl); elsewhere a full page header.
 */
export function AdminPage({
    title,
    subtitle,
    onNew,
    newLabel,
    actions,
    children,
}: {
    title: string;
    subtitle?: ReactNode;
    onNew?: () => void;
    newLabel?: string;
    actions?: ReactNode;
    children: ReactNode;
}) {
    const inHub = useInSettingsHub();
    const { t } = useT();
    useHotkeys('list', [
        {
            id: 'list.new',
            keys: 'n',
            labelKey: 'hotkeys.new',
            handler: () => onNew?.(),
        },
    ]);

    const buttons = (
        <>
            {actions}
            {onNew ? (
                <Button type="button" onClick={onNew}>
                    <Plus aria-hidden="true" />
                    {newLabel ?? t('admin.add')}
                </Button>
            ) : null}
        </>
    );

    if (inHub) {
        return (
            <>
                <Head title={title} />
                <section
                    className="max-w-4xl space-y-4"
                    aria-labelledby="settings-section-title"
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="min-w-0">
                            <h2
                                id="settings-section-title"
                                className="text-base font-bold text-foreground"
                            >
                                {title}
                            </h2>
                            {subtitle ? (
                                <p className="text-xs text-muted-foreground">
                                    {subtitle}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {buttons}
                        </div>
                    </div>
                    {children}
                </section>
            </>
        );
    }

    return (
        <>
            <Head title={title} />
            <Page>
                <PageHeader
                    title={title}
                    subtitle={subtitle}
                    actions={buttons}
                />
                {children}
            </Page>
        </>
    );
}
