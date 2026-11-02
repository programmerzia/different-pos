import { CalendarRange } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import {
    detectPreset,
    presetRange,
    type DateRange,
    type RangePreset,
} from '@/lib/date-range';

const PRESETS = ['today', 'last7', 'thisMonth', 'lastMonth', 'custom'] as const;

/** Range with presets (today, last 7 days, this month, last month, custom). Dates are YYYY-MM-DD. */
export function DateRangePicker({
    value,
    onChange,
    today: todayProp,
}: {
    value: DateRange;
    onChange: (range: DateRange) => void;
    /** Injectable for tests; defaults to now. */
    today?: Date;
}) {
    const { t } = useT();
    const [clock] = useState(() => new Date());
    const today = todayProp ?? clock;
    const digits = useDigits();
    const [open, setOpen] = useState(false);
    const active = detectPreset(value, today);
    const [custom, setCustom] = useState<boolean>(active === 'custom');

    const pick = (preset: RangePreset) => {
        if (preset === 'custom') {
            setCustom(true);

            return;
        }
        setCustom(false);
        onChange(presetRange(preset, today));
        setOpen(false);
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-11 md:min-h-9"
                >
                    <CalendarRange aria-hidden="true" />
                    <span className="num">
                        {digits.n(value.from)} → {digits.n(value.to)}
                    </span>
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-72" align="start">
                <ToggleGroup
                    type="single"
                    orientation="vertical"
                    className="flex w-full flex-col items-stretch gap-1"
                    value={custom ? 'custom' : active}
                    onValueChange={(v) => v && pick(v as RangePreset)}
                    aria-label={t('range.label')}
                >
                    {PRESETS.map((preset) => (
                        <ToggleGroupItem
                            key={preset}
                            value={preset}
                            className="min-h-11 justify-start md:min-h-9"
                        >
                            {t(`range.${preset}`)}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
                {custom ? (
                    <div className="mt-3 grid grid-cols-2 gap-2">
                        <label className="text-xs text-muted-foreground">
                            {t('range.from')}
                            <Input
                                type="date"
                                value={value.from}
                                max={value.to}
                                onChange={(e) =>
                                    e.target.value &&
                                    onChange({ ...value, from: e.target.value })
                                }
                            />
                        </label>
                        <label className="text-xs text-muted-foreground">
                            {t('range.to')}
                            <Input
                                type="date"
                                value={value.to}
                                min={value.from}
                                onChange={(e) =>
                                    e.target.value &&
                                    onChange({ ...value, to: e.target.value })
                                }
                            />
                        </label>
                    </div>
                ) : null}
            </PopoverContent>
        </Popover>
    );
}
