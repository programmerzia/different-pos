<?php

declare(strict_types=1);

namespace App\Support\Dates;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The named date periods of list toolbars and reports, as inclusive ranges of tenant-local calendar
 * days (YYYY-MM-DD). One definition for every screen: "This week" is the Bangladesh week, Saturday
 * to today (never a rolling 7 days); "This month" starts on the 1st; the fiscal year on 1 July.
 * Mirrored by resources/js/lib/date-range.ts (startOfWeek / periodRange).
 */
final class Period
{
    /** Carbon day-of-week constant the week starts on (Bangladesh: Saturday). */
    public const int WEEK_STARTS_ON = CarbonInterface::SATURDAY;

    /** @var array<string, string> accepted aliases => canonical key */
    private const array ALIASES = [
        'today' => 'today',
        'yesterday' => 'yesterday',
        'week' => 'week',
        'this_week' => 'week',
        'month' => 'month',
        'this_month' => 'month',
        'last_month' => 'last_month',
        'last_quarter' => 'last_quarter',
        'fiscal_year' => 'fiscal_year',
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::ALIASES);
    }

    /**
     * @return array{from: string, to: string}|null null for an unknown key (= no period filter)
     */
    public static function range(?string $key, string $timezone, ?CarbonInterface $now = null): ?array
    {
        $canonical = self::ALIASES[$key ?? ''] ?? null;
        if ($canonical === null) {
            return null;
        }

        $today = ($now instanceof CarbonInterface ? CarbonImmutable::instance($now) : CarbonImmutable::now())
            ->setTimezone($timezone)->startOfDay();

        [$from, $to] = match ($canonical) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'week' => [self::startOfWeek($today), $today],
            'month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'last_quarter' => [$today->startOfQuarter()->subQuarterNoOverflow(), $today->startOfQuarter()->subDay()],
            'fiscal_year' => [self::fiscalYearStart($today), $today],
        };

        return ['from' => $from->toDateString(), 'to' => $to->toDateString()];
    }

    /** The Saturday on or before $day (same timezone, start of day). */
    public static function startOfWeek(CarbonInterface $day): CarbonImmutable
    {
        $day = CarbonImmutable::instance($day)->startOfDay();
        $back = ($day->dayOfWeek - self::WEEK_STARTS_ON + 7) % 7;

        return $day->subDays($back);
    }

    private static function fiscalYearStart(CarbonImmutable $today): CarbonImmutable
    {
        $year = $today->month >= 7 ? $today->year : $today->year - 1;

        return $today->setDate($year, 7, 1);
    }
}
