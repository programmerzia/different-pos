<?php

declare(strict_types=1);

namespace App\Support\Sales;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder;

/** Business-day arithmetic in the tenant timezone (timestamps are stored in UTC). */
final class BusinessDay
{
    public const string DEFAULT_TIMEZONE = 'Asia/Dhaka';

    public static function timezone(Tenant $tenant): string
    {
        $timezone = $tenant->getAttribute('timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : self::DEFAULT_TIMEZONE;
    }

    /** Whole calendar days from the local date of $from to the local date of $to (negative if $to is earlier). */
    public static function daysBetween(CarbonInterface $from, CarbonInterface $to, string $timezone): int
    {
        $a = CarbonImmutable::instance($from)->setTimezone($timezone)->startOfDay();
        $b = CarbonImmutable::instance($to)->setTimezone($timezone)->startOfDay();

        return (int) $a->diffInDays($b, false);
    }

    public static function isSameDay(CarbonInterface $a, CarbonInterface $b, string $timezone): bool
    {
        return self::daysBetween($a, $b, $timezone) === 0;
    }

    /** Today's local date (Y-m-d) in the tenant timezone, e.g. a form's default date. */
    public static function today(string $timezone): string
    {
        return CarbonImmutable::now($timezone)->toDateString();
    }

    /** The local date (Y-m-d) of a UTC timestamp, or null. */
    public static function localDate(?CarbonInterface $at, string $timezone): ?string
    {
        return $at instanceof CarbonInterface ? CarbonImmutable::instance($at)->setTimezone($timezone)->toDateString() : null;
    }

    /** ISO 8601 in the tenant timezone, with its offset ("2026-11-03T18:26:27+06:00"), or null. */
    public static function localIso(?CarbonInterface $at, string $timezone): ?string
    {
        return $at instanceof CarbonInterface ? CarbonImmutable::instance($at)->setTimezone($timezone)->toIso8601String() : null;
    }

    /**
     * Applies an inclusive range of local dates (YYYY-MM-DD, either end optional; anything else is
     * ignored) to a UTC timestamp column: from the start of $from to before the start of the day after $to.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function whereLocalDates(Builder $query, string $column, ?string $from, ?string $to, string $timezone): Builder
    {
        foreach (['>=' => $from, '<' => $to] as $operator => $value) {
            if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                continue;
            }
            $day = CarbonImmutable::createFromFormat('Y-m-d', $value, $timezone);
            if (! $day instanceof CarbonImmutable) {
                continue;
            }
            $edge = $operator === '<' ? $day->startOfDay()->addDay() : $day->startOfDay();
            $query->where($column, $operator, $edge->utc());
        }

        return $query;
    }
}
