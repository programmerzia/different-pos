<?php

declare(strict_types=1);

namespace App\Support\Sales;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

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
}
