import { Link, router } from '@inertiajs/react';
import { AlertTriangle, Search, Send, Smartphone, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Kbd } from '@/components/kbd';
import { NativeSelect } from '@/components/native-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useDigits } from '@/hooks/use-digits';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';
import { PhonePreview } from './phone-preview';
import {
    insertVariable,
    renderPreview,
    smsCost,
    smsSegments,
} from './sms-text';

export type ComposerConfig = {
    configured: boolean;
    driver: string;
    cost_per_segment: number;
    shop: string;
    max_body: number;
    variables: string[];
    templates: { key: string; body: string; enabled: boolean }[];
    counts: { all: number; due: number };
    groups: { id: number; name: string; count: number }[];
};

type Picked = {
    uuid: string;
    name: string;
    phone: string | null;
    customer_no: string | number | null;
};

/** The audience select's value: `due`, `all`, `customer` or `group:<id>`. */
export type Target = string;

/** Recipient count for the chosen audience (the server counts again when it sends). */
export function audienceCount(
    target: Target,
    config: ComposerConfig,
    picked: Picked | null,
): number {
    if (target === 'customer') {
        return picked?.phone ? 1 : 0;
    }
    if (target.startsWith('group:')) {
        const id = Number(target.slice(6));

        return config.groups.find((g) => g.id === id)?.count ?? 0;
    }

    return target === 'all' ? config.counts.all : config.counts.due;
}

/**
 * Mockup "Dispatch Campaign Composer" (screen 10): channel (SMS; WhatsApp is a later phase), recipient
 * target, a template from the tenant's SMS templates, variable chips, the text with characters / SMS parts,
 * the cost estimate and Send (Ctrl+Enter) behind a confirm with the recipient count and total cost.
 */
export function Composer({
    config,
    active = true,
    draft,
}: {
    config: ComposerConfig;
    /** False while another hub tab is shown (the composer stays mounted; its keys pause). */
    active?: boolean;
    /** A template handed over from the Templates tab; a new `seq` replaces the text. */
    draft?: { body: string; seq: number };
}) {
    const { t } = useT();
    const digits = useDigits();
    const [target, setTarget] = useState<Target>(
        config.counts.due > 0 ? 'due' : 'all',
    );
    const [picked, setPicked] = useState<Picked | null>(null);
    const [template, setTemplate] = useState('');
    const [body, setBody] = useState(draft?.body ?? '');
    const [confirming, setConfirming] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const area = useRef<HTMLTextAreaElement>(null);

    const seq = draft?.seq ?? 0;
    const [seenSeq, setSeenSeq] = useState(seq);
    if (seenSeq !== seq) {
        setSeenSeq(seq);
        setBody(draft?.body ?? '');
        setTemplate('');
    }
    useEffect(() => {
        if (seq > 0) {
            area.current?.focus();
        }
    }, [seq]);

    const recipients = audienceCount(target, config, picked);
    const info = smsSegments(body);
    const perMessage = smsCost(
        Math.max(1, info.segments),
        1,
        config.cost_per_segment,
    );
    const total = smsCost(info.segments, recipients, config.cost_per_segment);
    const canSend = config.configured && body.trim() !== '' && recipients > 0;

    const sample = useMemo(
        () => ({
            name: picked?.name ?? t('sms.hub.sample_name'),
            customer: picked?.name ?? t('sms.hub.sample_name'),
            shop: config.shop,
            due: t('sms.hub.sample_due'),
            balance: t('sms.hub.sample_due'),
            number: picked?.customer_no
                ? String(picked.customer_no)
                : t('sms.hub.sample_number'),
        }),
        [picked, config.shop, t],
    );

    const chooseTemplate = (key: string) => {
        setTemplate(key);
        const tpl = config.templates.find((x) => x.key === key);
        if (tpl) {
            setBody(tpl.body);
        }
    };

    const addVariable = (variable: string) => {
        const el = area.current;
        const next = insertVariable(
            body,
            variable,
            el?.selectionStart ?? body.length,
            el?.selectionEnd ?? body.length,
        );
        setBody(next.text);
        requestAnimationFrame(() => {
            el?.focus();
            el?.setSelectionRange(next.caret, next.caret);
        });
    };

    const send = () =>
        new Promise<void>((resolve) => {
            setError(null);
            const group = target.startsWith('group:')
                ? Number(target.slice(6))
                : null;
            router.post(
                '/app/messages/send',
                {
                    target: group !== null ? 'group' : target,
                    customer:
                        target === 'customer' ? (picked?.uuid ?? null) : null,
                    group_id: group,
                    body,
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setBody('');
                        setTemplate('');
                    },
                    onError: (errors) =>
                        setError(Object.values(errors)[0] ?? null),
                    onFinish: () => resolve(),
                },
            );
        });

    useHotkeys('form', [
        {
            id: 'messages.send',
            keys: 'mod+enter',
            labelKey: 'sms.hub.send',
            allowInInput: true,
            handler: (event) => {
                event.preventDefault();
                if (active && canSend) {
                    setConfirming(true);
                }
            },
        },
    ]);

    const sendLabel =
        target === 'customer'
            ? t('sms.hub.send_one')
            : t('sms.hub.send_many', { n: digits.n(recipients) });

    return (
        <div className="flex flex-1 flex-col items-start gap-4 lg:flex-row">
            <div className="w-full flex-1 space-y-4 rounded-xl border bg-surface p-4 shadow-xs">
                <h2 className="text-xs font-bold tracking-wider text-foreground uppercase">
                    {t('sms.hub.composer_title')}
                </h2>

                {!config.configured ? (
                    <p
                        role="alert"
                        className="flex items-center gap-2 rounded-lg border border-warning bg-warning-soft p-2.5 text-xs text-foreground"
                    >
                        <AlertTriangle
                            className="size-4 shrink-0 text-warning"
                            aria-hidden="true"
                        />
                        <span className="flex-1">
                            {t('sms.hub.not_configured')}
                        </span>
                        <Link
                            href="/app/settings/sms"
                            className="font-bold text-primary underline"
                        >
                            {t('sms.hub.open_settings')}
                        </Link>
                    </p>
                ) : null}

                <div className="space-y-3">
                    <fieldset>
                        <legend className="text-[11px] font-semibold text-muted-foreground">
                            {t('sms.hub.channel')}
                        </legend>
                        <div className="mt-1 grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                aria-disabled="true"
                                title={t('sms.hub.next_phase')}
                                className="flex h-8 cursor-not-allowed items-center justify-center gap-1.5 rounded-lg border bg-surface-sunken text-xs font-semibold text-muted-foreground opacity-70 [@media(pointer:coarse)]:min-h-11"
                            >
                                <Smartphone
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                                <span>{t('sms.hub.whatsapp')}</span>
                                <span className="rounded bg-surface px-1 text-[9px] font-bold uppercase">
                                    {t('sms.hub.next_phase')}
                                </span>
                            </button>
                            <button
                                type="button"
                                aria-pressed="true"
                                className="flex h-8 items-center justify-center gap-1.5 rounded-lg border border-primary bg-primary-soft text-xs font-bold text-primary [@media(pointer:coarse)]:min-h-11"
                            >
                                <Send className="size-3.5" aria-hidden="true" />
                                <span>{t('sms.hub.sms')}</span>
                            </button>
                        </div>
                    </fieldset>

                    <div>
                        <Label
                            htmlFor="sms-target"
                            className="text-muted-foreground"
                        >
                            {t('sms.hub.target')}
                        </Label>
                        <NativeSelect
                            id="sms-target"
                            className="mt-1 font-semibold"
                            value={target}
                            onChange={(e) => setTarget(e.target.value)}
                        >
                            <option value="due">
                                {t('sms.hub.target_due', {
                                    n: digits.n(config.counts.due),
                                })}
                            </option>
                            <option value="customer">
                                {t('sms.hub.target_one', {
                                    name: picked
                                        ? picked.name
                                        : t('sms.hub.pick_customer'),
                                })}
                            </option>
                            {config.groups.map((g) => (
                                <option key={g.id} value={`group:${g.id}`}>
                                    {t('sms.hub.target_group', {
                                        name: g.name,
                                        n: digits.n(g.count),
                                    })}
                                </option>
                            ))}
                            <option value="all">
                                {t('sms.hub.target_all', {
                                    n: digits.n(config.counts.all),
                                })}
                            </option>
                        </NativeSelect>
                        {target === 'customer' ? (
                            <CustomerPicker
                                picked={picked}
                                onPick={setPicked}
                            />
                        ) : null}
                    </div>

                    <div>
                        <Label
                            htmlFor="sms-template"
                            className="text-muted-foreground"
                        >
                            {t('sms.hub.template')}
                        </Label>
                        <NativeSelect
                            id="sms-template"
                            className="mt-1"
                            value={template}
                            onChange={(e) => chooseTemplate(e.target.value)}
                        >
                            <option value="">
                                {t('sms.hub.template_custom')}
                            </option>
                            {config.templates.map((tpl) => (
                                <option key={tpl.key} value={tpl.key}>
                                    {t(`sms.template.${tpl.key}`)}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>

                    <div>
                        <div className="mb-1 text-[10px] text-muted-foreground">
                            {t('sms.hub.variables')}
                        </div>
                        <div className="flex flex-wrap gap-1.5">
                            {config.variables.map((v) => (
                                <button
                                    key={v}
                                    type="button"
                                    onClick={() => addVariable(v)}
                                    className="rounded border bg-surface-sunken px-2 py-0.5 font-mono text-[10px] text-foreground hover:border-primary [@media(pointer:coarse)]:min-h-11"
                                    aria-label={t('sms.hub.insert_variable', {
                                        name: `{${v}}`,
                                    })}
                                >
                                    + {`{${v}}`}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div>
                        <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                            <Label htmlFor="sms-body">
                                {t('sms.hub.body')}
                            </Label>
                            <span className="num font-mono" aria-live="polite">
                                {t('sms.hub.counter', {
                                    chars: digits.n(body.length),
                                    parts: digits.n(info.segments),
                                    encoding: t(
                                        `sms.hub.encoding_${info.encoding}`,
                                    ),
                                })}
                            </span>
                        </div>
                        <textarea
                            id="sms-body"
                            ref={area}
                            rows={4}
                            maxLength={config.max_body}
                            value={body}
                            placeholder={t('sms.hub.body_ph')}
                            aria-invalid={error ? true : undefined}
                            aria-describedby={error ? 'sms-error' : undefined}
                            onChange={(e) => setBody(e.target.value)}
                            className="mt-1 w-full rounded-lg border bg-surface-sunken p-2.5 font-mono text-xs leading-relaxed text-foreground outline-none focus-visible:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive"
                        />
                        {error ? (
                            <p
                                id="sms-error"
                                role="alert"
                                className="text-[11px] text-destructive"
                            >
                                {error}
                            </p>
                        ) : null}
                    </div>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2 border-t pt-3">
                    <span className="text-xs text-muted-foreground">
                        {t('sms.hub.cost_estimate')}{' '}
                        <strong className="num text-foreground">
                            {t('sms.hub.cost_per_msg', {
                                amount: digits.money(perMessage),
                            })}
                        </strong>
                        {recipients > 1 ? (
                            <>
                                {' · '}
                                <span className="num">
                                    {t('sms.hub.cost_total', {
                                        amount: digits.money(total),
                                    })}
                                </span>
                            </>
                        ) : null}
                    </span>
                    <Button
                        type="button"
                        className="h-9 bg-success px-5 font-bold text-primary-foreground hover:bg-success/90"
                        disabled={!canSend}
                        onClick={() => setConfirming(true)}
                    >
                        <Send aria-hidden="true" />
                        {sendLabel}
                        <Kbd keys="mod+enter" onButton />
                    </Button>
                </div>
            </div>

            <PhonePreview
                shop={config.shop}
                text={renderPreview(body, sample)}
            />

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={t('sms.hub.confirm_title')}
                description={t('sms.hub.confirm_body', {
                    n: digits.n(recipients),
                    parts: digits.n(info.segments),
                    amount: digits.money(total),
                })}
                confirmLabel={sendLabel}
                onConfirm={send}
            />
        </div>
    );
}

/** Single-customer search (the customer search API); shows the pick with a clear button. */
function CustomerPicker({
    picked,
    onPick,
}: {
    picked: Picked | null;
    onPick: (c: Picked | null) => void;
}) {
    const { t } = useT();
    const digits = useDigits();
    const [q, setQ] = useState('');
    const [results, setResults] = useState<Picked[]>([]);

    useEffect(() => {
        const term = q.trim();
        if (term.length < 2) {
            return;
        }
        const ctrl = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(`/app/customers/search?q=${encodeURIComponent(term)}`, {
                headers: { Accept: 'application/json' },
                signal: ctrl.signal,
            })
                .then((r) => (r.ok ? r.json() : { customers: [] }))
                .then((data: { customers: Picked[] }) =>
                    setResults(data.customers.slice(0, 8)),
                )
                .catch(() => undefined);
        }, 200);

        return () => {
            ctrl.abort();
            window.clearTimeout(timer);
        };
    }, [q]);

    if (picked) {
        return (
            <div className="mt-2 flex items-center justify-between gap-2 rounded-lg border bg-surface-sunken px-2.5 py-1.5 text-xs">
                <span>
                    <span className="font-semibold">{picked.name}</span>{' '}
                    <span className="num font-mono text-muted-foreground">
                        {picked.phone
                            ? digits.n(picked.phone)
                            : t('sms.hub.no_phone')}
                    </span>
                </span>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-6"
                    aria-label={t('sms.hub.clear_customer')}
                    onClick={() => onPick(null)}
                >
                    <X aria-hidden="true" />
                </Button>
            </div>
        );
    }

    const shown = q.trim().length >= 2 ? results : [];

    return (
        <div className="relative mt-2">
            <Search
                className="pointer-events-none absolute top-2 left-2.5 size-4 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                type="search"
                className="pl-8"
                value={q}
                autoFocus
                placeholder={t('sms.hub.search_customer')}
                aria-label={t('sms.hub.search_customer')}
                onChange={(e) => setQ(e.target.value)}
            />
            {shown.length > 0 ? (
                <ul className="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border bg-surface-raised p-1 shadow-lg">
                    {shown.map((c) => (
                        <li key={c.uuid}>
                            <button
                                type="button"
                                className={cn(
                                    'flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left text-xs hover:bg-surface-sunken focus-visible:bg-surface-sunken focus-visible:outline-none',
                                    !c.phone && 'opacity-60',
                                )}
                                onClick={() => {
                                    onPick(c);
                                    setQ('');
                                }}
                            >
                                <span className="font-semibold">{c.name}</span>
                                <span className="num font-mono text-muted-foreground">
                                    {c.phone
                                        ? digits.n(c.phone)
                                        : t('sms.hub.no_phone')}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
