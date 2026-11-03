import { PageHeader } from '@/components/page-header';
import { Head, useForm } from '@inertiajs/react';
import { FormSection } from '@/components/form-section';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import type { SmsSettings } from '@/types/messaging';

type Props = { drivers: string[]; sms: SmsSettings };

type TemplateData = Record<string, { enabled: boolean; body: string }>;

export default function SmsSettingsPage({ drivers, sms }: Props) {
    const { t } = useT();
    const form = useForm({
        driver: sms.driver,
        sender_id: sms.sender_id,
        sid: sms.sid,
        base_url: sms.base_url,
        api_token: '',
        clear_token: false,
        templates: Object.fromEntries(
            sms.templates.map((tpl) => [
                tpl.key,
                { enabled: tpl.enabled, body: tpl.body },
            ]),
        ) as TemplateData,
    });
    const test = useForm({ phone: '' });
    const real = form.data.driver === 'ssl_wireless';

    const save = () => form.put('/app/settings/sms', { preserveScroll: true });
    const sendTest = () =>
        test.post('/app/settings/sms/test', { preserveScroll: true });

    useHotkeys('form', [
        {
            id: 'form.save',
            keys: 'mod+s',
            labelKey: 'sms.save',
            allowInInput: true,
            handler: (event) => {
                event.preventDefault();
                save();
            },
        },
    ]);

    const setTemplate = (
        key: string,
        patch: Partial<{ enabled: boolean; body: string }>,
    ) =>
        form.setData('templates', {
            ...form.data.templates,
            [key]: { ...form.data.templates[key]!, ...patch },
        });

    return (
        <>
            <Head title={t('sms.settings.title')} />
            <div className="space-y-8">
                <PageHeader
                    title={t('sms.settings.title')}
                    subtitle={t('sms.settings.subtitle')}
                />

                <form
                    className="space-y-8"
                    onSubmit={(event) => {
                        event.preventDefault();
                        save();
                    }}
                >
                    <FormSection
                        title={t('sms.provider.title')}
                        description={t('sms.provider.hint')}
                    >
                        <div className="grid gap-1.5">
                            <Label htmlFor="driver">
                                {t('sms.provider.label')}
                            </Label>
                            <Select
                                value={form.data.driver}
                                onValueChange={(v) => form.setData('driver', v)}
                            >
                                <SelectTrigger
                                    id="driver"
                                    className="min-h-11 md:min-h-9"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {drivers.map((d) => (
                                        <SelectItem key={d} value={d}>
                                            {t(`sms.driver.${d}`)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.driver} />
                        </div>

                        {real ? (
                            <>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="sid">
                                        {t('sms.provider.sid')}
                                    </Label>
                                    <Input
                                        id="sid"
                                        className="min-h-11 md:min-h-9"
                                        value={form.data.sid}
                                        onChange={(e) =>
                                            form.setData('sid', e.target.value)
                                        }
                                    />
                                    <InputError message={form.errors.sid} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="api_token">
                                        {t('sms.provider.token')}
                                    </Label>
                                    <Input
                                        id="api_token"
                                        type="password"
                                        autoComplete="off"
                                        className="min-h-11 md:min-h-9"
                                        value={form.data.api_token}
                                        placeholder={
                                            sms.has_token
                                                ? t('sms.provider.token_kept')
                                                : ''
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'api_token',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.api_token}
                                    />
                                    {sms.has_token ? (
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={form.data.clear_token}
                                                onCheckedChange={(v) =>
                                                    form.setData(
                                                        'clear_token',
                                                        v === true,
                                                    )
                                                }
                                            />
                                            {t('sms.provider.token_clear')}
                                        </label>
                                    ) : null}
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="base_url">
                                        {t('sms.provider.base_url')}
                                    </Label>
                                    <Input
                                        id="base_url"
                                        inputMode="url"
                                        className="min-h-11 md:min-h-9"
                                        value={form.data.base_url}
                                        placeholder="https://smsplus.sslwireless.com"
                                        onChange={(e) =>
                                            form.setData(
                                                'base_url',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.base_url}
                                    />
                                </div>
                            </>
                        ) : null}

                        <div className="grid gap-1.5">
                            <Label htmlFor="sender_id">
                                {t('sms.provider.sender')}
                            </Label>
                            <Input
                                id="sender_id"
                                className="min-h-11 md:min-h-9"
                                value={form.data.sender_id}
                                onChange={(e) =>
                                    form.setData('sender_id', e.target.value)
                                }
                            />
                            <InputError message={form.errors.sender_id} />
                        </div>
                    </FormSection>

                    <section className="max-w-180 space-y-4">
                        <div>
                            <h2 className="text-xl font-semibold">
                                {t('sms.templates.title')}
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {t('sms.templates.hint')}
                            </p>
                        </div>
                        {sms.templates.map((tpl) => {
                            const data = form.data.templates[tpl.key]!;

                            return (
                                <div
                                    key={tpl.key}
                                    className="space-y-2 rounded-lg border bg-card p-4"
                                >
                                    <div className="flex flex-wrap items-center gap-3">
                                        <Label
                                            htmlFor={`tpl-${tpl.key}`}
                                            className="mr-auto text-base font-medium"
                                        >
                                            {t(`sms.template.${tpl.key}`)}
                                        </Label>
                                        <label className="flex min-h-11 items-center gap-2 text-sm md:min-h-8">
                                            <Checkbox
                                                checked={data.enabled}
                                                onCheckedChange={(v) =>
                                                    setTemplate(tpl.key, {
                                                        enabled: v === true,
                                                    })
                                                }
                                            />
                                            {t('sms.templates.enabled')}
                                        </label>
                                    </div>
                                    <textarea
                                        id={`tpl-${tpl.key}`}
                                        rows={3}
                                        maxLength={480}
                                        value={data.body}
                                        onChange={(e) =>
                                            setTemplate(tpl.key, {
                                                body: e.target.value,
                                            })
                                        }
                                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    />
                                    <InputError
                                        message={
                                            form.errors[
                                                `templates.${tpl.key}.body` as keyof typeof form.errors
                                            ]
                                        }
                                    />
                                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                        <span>
                                            {t('sms.templates.placeholders')}
                                        </span>
                                        {tpl.placeholders.map((p) => (
                                            <code
                                                key={p}
                                                className="rounded-sm bg-muted px-1.5 py-0.5"
                                            >{`{${p}}`}</code>
                                        ))}
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="ml-auto min-h-11 md:min-h-8"
                                            onClick={() =>
                                                setTemplate(tpl.key, {
                                                    body: tpl.default,
                                                })
                                            }
                                        >
                                            {t('sms.templates.reset')}
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}
                    </section>

                    <Button
                        type="submit"
                        disabled={form.processing}
                        className="min-h-11 md:min-h-9"
                    >
                        {t('sms.save')}
                    </Button>
                </form>

                <FormSection
                    title={t('sms.test.title')}
                    description={t('sms.test.hint')}
                >
                    <div className="grid gap-1.5">
                        <Label htmlFor="test-phone">
                            {t('sms.test.phone')}
                        </Label>
                        <Input
                            id="test-phone"
                            type="tel"
                            inputMode="tel"
                            autoComplete="off"
                            className="min-h-11 md:min-h-9"
                            value={test.data.phone}
                            placeholder="01XXXXXXXXX"
                            onChange={(e) =>
                                test.setData('phone', e.target.value)
                            }
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    sendTest();
                                }
                            }}
                        />
                        <InputError message={test.errors.phone} />
                    </div>
                    <div className="flex items-end">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={test.processing || test.data.phone === ''}
                            className="min-h-11 md:min-h-9"
                            onClick={sendTest}
                        >
                            {t('sms.test.send')}
                        </Button>
                    </div>
                </FormSection>
            </div>
        </>
    );
}
