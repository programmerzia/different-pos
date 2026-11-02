<?php

declare(strict_types=1);

namespace App\Legacy\Transform;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Legacy dates are local Asia/Dhaka wall-clock values (12-hour, no AM/PM: the date part is right,
 * the time is kept as written, ADR 0002 M3). Zero and nonsense dates become null.
 */
final class LegacyDate
{
    public const string ZONE = 'Asia/Dhaka';

    public static function local(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '' || str_starts_with($value, '0000')) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value, self::ZONE);
        } catch (Throwable) {
            return null;
        }

        return $date->year < 1970 || $date->year > 2100 ? null : $date;
    }

    /** "Y-m-d H:i:s" in UTC (the app timezone) for a timestamp column. */
    public static function utc(mixed $value): ?string
    {
        return self::local($value)?->utc()->format('Y-m-d H:i:s');
    }

    /** "Y-m-d" for a DATE column (DOB, anniversary): no timezone shift. */
    public static function date(mixed $value): ?string
    {
        return self::local($value)?->format('Y-m-d');
    }
}
