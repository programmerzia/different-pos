import { Head, Link } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { Check, Minus, Plus, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { FormField } from '@/components/form-field';
import { NativeSelect } from '@/components/native-select';
import { QtyStepper } from '@/components/qty-stepper';
import { ScanField, type ScanProduct } from '@/components/scan-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFormSubmit } from '@/hooks/use-form-submit';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

type Line = {
    product_id: number;
    name: string;
    sku: string;
    direction: 1 | -1;
    qty: number;
};

export default function AdjustmentCreate({ reasons }: { reasons: string[] }) {
    const { t } = useT();
    const { submit, processing, errors } = useFormSubmit();
    const scan = useRef<HTMLInputElement>(null);
    const [reason, setReason] = useState(
        reasons.includes('correction') ? 'correction' : (reasons[0] ?? ''),
    );
    const [note, setNote] = useState('');
    const [lines, setLines] = useState<Line[]>([]);

    const onPick = (p: ScanProduct) =>
        setLines((prev) => {
            const at = prev.findIndex((l) => l.product_id === p.id);
            if (at >= 0) {
                return prev.map((l, i) =>
                    i === at ? { ...l, qty: l.qty + 1000 } : l,
                );
            }

            return [
                ...prev,
                {
                    product_id: p.id,
                    name: p.variant_label
                        ? `${p.name} · ${p.variant_label}`
                        : p.name,
                    sku: p.sku,
                    direction: 1,
                    qty: 1000,
                },
            ];
        });
    const setLine = (index: number, patch: Partial<Line>) =>
        setLines((prev) =>
            prev.map((l, i) => (i === index ? { ...l, ...patch } : l)),
        );

    const save = () =>
        submit('post', '/app/adjustments', {
            reason,
            note: note || null,
            items: lines.map((l) => ({
                product_id: l.product_id,
                qty: l.direction * l.qty,
            })),
        });

    useHotkeys('form', [
        {
            id: 'adjustment.save',
            keys: 'mod+enter',
            labelKey: 'adjustments.apply',
            allowInInput: true,
            handler: (e) => {
                e.preventDefault();
                if (!processing && lines.length > 0) save();
            },
        },
        {
            id: 'adjustment.scan',
            keys: 'f2',
            labelKey: 'scan.label',
            allowInInput: true,
            handler: (e) => {
                e.preventDefault();
                scan.current?.focus();
            },
        },
    ]);

    const lineError = Object.entries(errors).find(([k]) =>
        k.startsWith('items'),
    )?.[1];

    return (
        <>
            <Head title={t('adjustments.new')} />
            <form
                className="flex max-w-4xl flex-col gap-6 p-page"
                noValidate
                onSubmit={(e) => {
                    e.preventDefault();
                    save();
                }}
            >
                <PageHeader title={t('adjustments.new')} />
                <div className="grid gap-4 lg:grid-cols-2">
                    <FormField
                        label={t('adjustments.reason')}
                        error={errors['reason']}
                    >
                        <NativeSelect
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                        >
                            {reasons.map((r) => (
                                <option key={r} value={r}>
                                    {t(`adjustments.reasons.${r}`)}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    <FormField
                        label={t('purchases.note')}
                        error={errors['note']}
                    >
                        <Input
                            className="min-h-11"
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                        />
                    </FormField>
                </div>

                <section aria-labelledby="al-title" className="grid gap-3">
                    <h2 id="al-title" className="text-xl font-semibold">
                        {t('purchases.items')}
                    </h2>
                    <ScanField
                        ref={scan}
                        lookupUrl="/app/adjustments/lookup"
                        onPick={onPick}
                    />
                    {lineError ? (
                        <p role="alert" className="text-sm text-destructive">
                            {lineError}
                        </p>
                    ) : null}
                    {lines.length === 0 ? (
                        <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                            {t('adjustments.scan_hint')}
                        </p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {lines.map((l, i) => (
                                <li
                                    key={l.product_id}
                                    className="flex flex-wrap items-center gap-3 px-3 py-2"
                                >
                                    <div className="min-w-48 flex-1">
                                        <p className="font-medium">{l.name}</p>
                                        <p className="num text-xs text-muted-foreground">
                                            {l.sku}
                                        </p>
                                    </div>
                                    <fieldset className="m-0 inline-flex min-w-0 overflow-hidden rounded-md border p-0">
                                        <legend className="sr-only">
                                            {t('adjustments.direction')}
                                        </legend>
                                        <button
                                            type="button"
                                            aria-pressed={l.direction === 1}
                                            onClick={() =>
                                                setLine(i, { direction: 1 })
                                            }
                                            className={cn(
                                                'inline-flex min-h-11 items-center gap-1 px-3 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                                l.direction === 1
                                                    ? 'bg-success-soft font-medium text-success'
                                                    : 'hover:bg-muted',
                                            )}
                                        >
                                            <Plus
                                                className="size-4"
                                                aria-hidden="true"
                                            />{' '}
                                            {t('adjustments.add_stock')}
                                        </button>
                                        <button
                                            type="button"
                                            aria-pressed={l.direction === -1}
                                            onClick={() =>
                                                setLine(i, { direction: -1 })
                                            }
                                            className={cn(
                                                'inline-flex min-h-11 items-center gap-1 border-l px-3 text-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                                l.direction === -1
                                                    ? 'bg-danger-soft font-medium text-destructive'
                                                    : 'hover:bg-muted',
                                            )}
                                        >
                                            <Minus
                                                className="size-4"
                                                aria-hidden="true"
                                            />{' '}
                                            {t('adjustments.remove_stock')}
                                        </button>
                                    </fieldset>
                                    <QtyStepper
                                        value={l.qty}
                                        min={1}
                                        onChange={(v) => setLine(i, { qty: v })}
                                        aria-label={`${t('purchases.qty')} ${l.name}`}
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-11"
                                        aria-label={`${t('purchases.remove_line')} ${l.name}`}
                                        onClick={() =>
                                            setLines((prev) =>
                                                prev.filter(
                                                    (_, idx) => idx !== i,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 aria-hidden="true" />
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <div className="flex gap-2">
                    <Button
                        type="submit"
                        className="min-h-11"
                        disabled={processing || lines.length === 0}
                    >
                        <Check aria-hidden="true" /> {t('adjustments.apply')}
                    </Button>
                    <Button asChild variant="outline" className="min-h-11">
                        <Link href="/app/adjustments">
                            {t('common.cancel')}
                        </Link>
                    </Button>
                </div>
            </form>
        </>
    );
}
