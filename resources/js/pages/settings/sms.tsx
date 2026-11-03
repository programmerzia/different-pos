import { Head, useForm } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';
import { useCallback } from 'react';
import { HubCard, HubSection, HubToggle } from '@/components/admin/hub-section';
import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { MoneyInput } from '@/components/money-input';
import { NativeSelect } from '@/components/native-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useT } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout, { useSettingsSave } from '@/layouts/settings/layout';
import type { SmsSettings } from '@/types/messaging';

type Props = { drivers: string[]; sms: SmsSettings };

type TemplateData = Record<string, { enabled: boolean; body: string }>;

/**
 * Settings hub → Messaging Providers (mockup "SMS & WhatsApp Messaging Gateways"): provider, sender,
 * credentials (the token is never sent back), the per-part price the composer's estimate uses, the
 * automatic templates and a test SMS. Saved by the hub's "Save Changes (Ctrl+S)".
 */
export default function SmsSettingsPage({ drivers, sms }: Props) {
    const { t } = useT();
    const form = useForm({
        driver: sms.driver,
        sender_id: sms.sender_id,
        sid: sms.sid,
        base_url: sms.base_url,
        api_token: '',
        clear_token: false,
        cost_per_segment: sms.cost_per_segment,
        templates: Object.fromEntries(
            sms.templates.map((tpl) => [
                tpl.key,
                { enabled: tpl.enabled, body: tpl.body },
            ]),
        ) as TemplateData,
    });
    const test = useForm({ phone: '' });
    const real = form.data.driver === 'ssl_wireless';
    const errors = form.errors as Record<string, string | undefined>;

    const { put, reset, clearErrors } = form;
    const save = useCallback(
        () => put('/app/settings/sms', { preserveScroll: true }),
        [put],
    );
    const discard = useCallback(() => {
        reset();
        clearErrors();
    }, [reset, clearErrors]);
    useSettingsSave({
        save,
        dirty: form.isDirty,
        processing: form.processing,
        discard,
    });

    const sendTest = () =>
        test.post('/app/settings/sms/test', { preserveScroll: true });

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
                <form
                    className="space-y-8"
                    onSubmit={(event) => {
                        event.preventDefault();
                        save();
                    }}
                >
                    <HubSection
                        title={t('sms.settings.hub_title')}
                        subtitle={t('sms.settings.subtitle')}
                    >
                        <HubCard>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <FormField
                                    label={t('sms.provider.label')}
                                    error={errors['driver']}
                                >
                                    <NativeSelect
                                        value={form.data.driver}
                                        onChange={(e) =>
                                            form.setData(
                                                'driver',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        {drivers.map((d) => (
                                            <option key={d} value={d}>
                                                {t(`sms.driver.${d}`)}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                                <FormField
                                    label={t('sms.provider.sender')}
                                    error={errors['sender_id']}
                                >
                                    <Input
                                        className="font-mono font-bold"
                                        value={form.data.sender_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'sender_id',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </div>

                            {real ? (
                                <>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <FormField
                                            label={t('sms.provider.sid')}
                                            error={errors['sid']}
                                        >
                                            <Input
                                                className="font-mono"
                                                value={form.data.sid}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'sid',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            label={t('sms.provider.base_url')}
                                            error={errors['base_url']}
                                        >
                                            <Input
                                                inputMode="url"
                                                value={form.data.base_url}
                                                placeholder="https://smsplus.sslwireless.com"
                                                onChange={(e) =>
                                                    form.setData(
                                                        'base_url',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </FormField>
                                    </div>
                                    <FormField
                                        label={t('sms.provider.token')}
                                        hint={t('sms.provider.hint')}
                                        error={errors['api_token']}
                                    >
                                        <Input
                                            type="password"
                                            autoComplete="off"
                                            className="font-mono"
                                            value={form.data.api_token}
                                            placeholder={
                                                sms.has_token
                                                    ? t(
                                                          'sms.provider.token_kept',
                                                      )
                                                    : ''
                                            }
                                            onChange={(e) =>
                                                form.setData(
                                                    'api_token',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    {sms.has_token ? (
                                        <label className="flex min-h-8 items-center gap-2 text-xs [@media(pointer:coarse)]:min-h-11">
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
                                </>
                            ) : null}

                            <div className="grid gap-3 border-t pt-2 sm:grid-cols-2">
                                <FormField
                                    label={t('sms.provider.cost')}
                                    hint={t('sms.provider.cost_hint')}
                                    error={errors['cost_per_segment']}
                                >
                                    <MoneyInput
                                        value={form.data.cost_per_segment}
                                        onChange={(v) =>
                                            form.setData(
                                                'cost_per_segment',
                                                v ?? 0,
                                            )
                                        }
                                    />
                                </FormField>
                            </div>
                        </HubCard>
                    </HubSection>

                    <HubSection
                        title={t('sms.templates.title')}
                        subtitle={t('sms.templates.hint')}
                    >
                        <div className="space-y-3">
                            {sms.templates.map((tpl) => {
                                const data = form.data.templates[tpl.key]!;

                                return (
                                    <HubCard
                                        key={tpl.key}
                                        className="space-y-2"
                                    >
                                        <HubToggle
                                            label={t(`sms.template.${tpl.key}`)}
                                            description={t(
                                                'sms.templates.enabled',
                                            )}
                                            checked={data.enabled}
                                            onChange={(v) =>
                                                setTemplate(tpl.key, {
                                                    enabled: v,
                                                })
                                            }
                                        />
                                        <textarea
                                            aria-label={t(
                                                `sms.template.${tpl.key}`,
                                            )}
                                            rows={3}
                                            maxLength={480}
                                            value={data.body}
                                            onChange={(e) =>
                                                setTemplate(tpl.key, {
                                                    body: e.target.value,
                                                })
                                            }
                                            className="w-full rounded-lg border border-border bg-surface p-2 font-mono text-xs leading-relaxed text-foreground outline-none focus-visible:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `templates.${tpl.key}.body`
                                                ]
                                            }
                                        />
                                        <div className="flex flex-wrap items-center gap-1.5 text-[11px] text-muted-foreground">
                                            <span>
                                                {t(
                                                    'sms.templates.placeholders',
                                                )}
                                            </span>
                                            {tpl.placeholders.map((p) => (
                                                <code
                                                    key={p}
                                                    className="rounded border bg-surface px-1.5 py-0.5 font-mono text-[10px] text-foreground"
                                                >{`{${p}}`}</code>
                                            ))}
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="ml-auto h-7 text-[11px] [@media(pointer:coarse)]:min-h-11"
                                                onClick={() =>
                                                    setTemplate(tpl.key, {
                                                        body: tpl.default,
                                                    })
                                                }
                                            >
                                                {t('sms.templates.reset')}
                                            </Button>
                                        </div>
                                    </HubCard>
                                );
                            })}
                        </div>
                    </HubSection>
                    <button type="submit" className="sr-only" tabIndex={-1}>
                        {t('sms.save')}
                    </button>
                </form>

                <HubSection
                    title={t('sms.test.title')}
                    subtitle={t('sms.test.hint')}
                >
                    <HubCard>
                        <div className="flex flex-wrap items-end gap-3">
                            <FormField
                                label={t('sms.test.phone')}
                                error={test.errors.phone}
                                className="w-60"
                            >
                                <Input
                                    type="tel"
                                    inputMode="tel"
                                    autoComplete="off"
                                    className="font-mono"
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
                            </FormField>
                            <Button
                                type="button"
                                variant="outline"
                                className="bg-surface"
                                disabled={
                                    test.processing || test.data.phone === ''
                                }
                                onClick={sendTest}
                            >
                                <MessageSquare aria-hidden="true" />
                                {t('sms.test.send')}
                            </Button>
                        </div>
                    </HubCard>
                </HubSection>
            </div>
        </>
    );
}

SmsSettingsPage.layout = [AppLayout, SettingsLayout];
