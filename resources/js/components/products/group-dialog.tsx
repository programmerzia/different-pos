import { router } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/form-field';
import { NativeSelect } from '@/components/native-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useHotkeyScope } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import type { VariantOptionDef } from './variant-builder';

export type Family = {
    id: number;
    uuid: string;
    code: string;
    name: string;
    option1: VariantOptionDef | null;
    option2: VariantOptionDef | null;
};

const idOrNull = (v: string): number | null => (v === '' ? null : Number(v));

/** "Group into a style": attach a loose product to an existing family as one of its variants. */
export function GroupDialog({
    open,
    onOpenChange,
    families,
    productId,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    families: Family[];
    productId: number;
}) {
    const { t } = useT();
    useHotkeyScope('dialog', open);
    const [familyId, setFamilyId] = useState<number | null>(
        families[0]?.id ?? null,
    );
    const [v1, setV1] = useState<number | null>(null);
    const [v2, setV2] = useState<number | null>(null);
    const family = families.find((x) => x.id === familyId) ?? null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-140">
                <DialogHeader>
                    <DialogTitle>{t('products.group')}</DialogTitle>
                </DialogHeader>
                <div className="grid gap-4">
                    <FormField label={t('products.family')}>
                        <NativeSelect
                            value={familyId ?? ''}
                            onChange={(e) => {
                                setFamilyId(idOrNull(e.target.value));
                                setV1(null);
                                setV2(null);
                            }}
                        >
                            {families.map((x) => (
                                <option key={x.id} value={x.id}>
                                    {x.code} · {x.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                    {family?.option1 ? (
                        <FormField label={family.option1.name}>
                            <NativeSelect
                                value={v1 ?? ''}
                                onChange={(e) =>
                                    setV1(idOrNull(e.target.value))
                                }
                            >
                                <option value="">{t('common.choose')}</option>
                                {family.option1.values.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.value}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                    ) : null}
                    {family?.option2 ? (
                        <FormField label={family.option2.name}>
                            <NativeSelect
                                value={v2 ?? ''}
                                onChange={(e) =>
                                    setV2(idOrNull(e.target.value))
                                }
                            >
                                <option value="">{t('common.choose')}</option>
                                {family.option2.values.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.value}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                    ) : null}
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common.cancel')}
                    </Button>
                    <Button
                        type="button"
                        className="min-h-11"
                        disabled={
                            family === null ||
                            v1 === null ||
                            (family.option2 !== null && v2 === null)
                        }
                        onClick={() =>
                            family &&
                            router.post(
                                `/app/products/families/${family.uuid}/members`,
                                {
                                    product_id: productId,
                                    option1_value_id: v1,
                                    option2_value_id: v2,
                                },
                            )
                        }
                    >
                        {t('products.group_confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
