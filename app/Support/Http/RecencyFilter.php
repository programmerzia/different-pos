<?php

declare(strict_types=1);

namespace App\Support\Http;

use Carbon\CarbonImmutable;

/**
 * The list toolbars' "Date:" recency presets (stock: last movement, vendors: last order): `today`,
 * `7d`, `30d`, `90d` (activity since the start of that business day) and `idle_90d` (no activity in
 * the last 90 days). Anything else means no filter.
 */
final class RecencyFilter
{
    public const array KEYS = ['today', '7d', '30d', '90d', 'idle_90d'];

    /**
     * @return array{0: CarbonImmutable, 1: bool}|null the UTC cut-off and whether activity must be on/after it
     *                                                 (true) or absent since it (false); null = no filter
     */
    public static function resolve(?string $key, string $timezone): ?array
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $cut = match ($key) {
            'today' => [$today, true],
            '7d' => [$today->subDays(7), true],
            '30d' => [$today->subDays(30), true],
            '90d' => [$today->subDays(90), true],
            'idle_90d' => [$today->subDays(90), false],
            default => null,
        };

        return $cut === null ? null : [$cut[0]->utc(), $cut[1]];
    }
}
