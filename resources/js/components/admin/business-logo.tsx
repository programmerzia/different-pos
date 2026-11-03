import { router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { HubCard, HubSection } from '@/components/admin/hub-section';
import InputError from '@/components/input-error';
import { TenantLogo } from '@/components/tenant-logo';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Upload } from '@/components/upload';
import { useT } from '@/i18n';

const LOGO_URL = '/app/settings/business/logo';

/** Settings → Business → Logo: upload (PNG/JPG/WebP ≤ 2 MB, the server re-validates), preview, remove. */
export function BusinessLogo({
    name,
    logoUrl,
}: {
    name: string;
    logoUrl: string | null;
}) {
    const { t } = useT();
    const form = useForm<{ logo: File | null }>({ logo: null });
    const [confirming, setConfirming] = useState(false);
    const [removing, setRemoving] = useState(false);
    const [pickerKey, setPickerKey] = useState(0);

    // A blob URL for the chosen file, released when it changes or the section unmounts.
    const preview = useMemo(
        () => (form.data.logo ? URL.createObjectURL(form.data.logo) : null),
        [form.data.logo],
    );
    useEffect(
        () => () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        },
        [preview],
    );

    const reset = () => {
        form.reset();
        form.clearErrors();
        setPickerKey((key) => key + 1);
    };

    const save = () =>
        form.post(LOGO_URL, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: reset,
        });

    const remove = () => {
        setRemoving(true);
        router.delete(LOGO_URL, {
            preserveScroll: true,
            onFinish: () => {
                setRemoving(false);
                setConfirming(false);
            },
        });
    };

    return (
        <HubSection
            title={t('business.section_logo')}
            subtitle={t('business.logo_hint')}
        >
            <HubCard className="grid gap-4 space-y-0 sm:grid-cols-[auto_1fr]">
                <div className="flex flex-col items-start gap-3">
                    <TenantLogo
                        url={preview ?? logoUrl}
                        name={t('business.logo_preview')}
                        className="size-28 rounded-xl"
                        imageClassName="p-2"
                    />
                    <p className="text-xs text-muted-foreground">
                        {preview
                            ? t('business.logo_selected', {
                                  name: form.data.logo?.name ?? '',
                              })
                            : logoUrl
                              ? name
                              : t('business.logo_none')}
                    </p>
                </div>
                <div className="flex flex-col gap-3">
                    <Upload
                        key={pickerKey}
                        accept="image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp"
                        maxSizeMb={2}
                        label={t('business.logo')}
                        onFiles={(files) =>
                            form.setData('logo', files[0] ?? null)
                        }
                    />
                    <InputError message={form.errors.logo} />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            className="[@media(pointer:coarse)]:min-h-11"
                            disabled={!form.data.logo || form.processing}
                            onClick={save}
                        >
                            {form.processing ? (
                                <Spinner aria-hidden="true" />
                            ) : null}
                            {logoUrl
                                ? t('business.logo_replace')
                                : t('business.logo_upload')}
                        </Button>
                        {form.data.logo ? (
                            <Button
                                type="button"
                                variant="ghost"
                                className="[@media(pointer:coarse)]:min-h-11"
                                onClick={reset}
                            >
                                {t('business.logo_cancel')}
                            </Button>
                        ) : null}
                        {logoUrl ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="[@media(pointer:coarse)]:min-h-11"
                                disabled={removing}
                                onClick={() => setConfirming(true)}
                            >
                                {t('business.logo_remove')}
                            </Button>
                        ) : null}
                    </div>
                </div>
            </HubCard>
            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={t('business.logo_remove_title')}
                description={t('business.logo_remove_body')}
                confirmLabel={t('business.logo_remove')}
                destructive
                onConfirm={remove}
            />
        </HubSection>
    );
}
