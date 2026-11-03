<?php

declare(strict_types=1);

namespace App\Reports\Dashboard;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * The dashboard's period (tenant-local calendar days, both ends inclusive) and the window it is
 * compared with: the day before, the preceding 7/30 days, the same days of last month, or the
 * same number of days right before a custom range.
 */
final readonly class DashboardPeriod
{
    public const string TODAY = 'today';

    public const string YESTERDAY = 'yesterday';

    public const string LAST_7_DAYS = '7d';

    public const string LAST_30_DAYS = '30d';

    public const string THIS_MONTH = 'this_month';

    public const string CUSTOM = 'custom';

    /** @var list<string> */
    public const array KEYS = [self::TODAY, self::YESTERDAY, self::LAST_7_DAYS, self::LAST_30_DAYS, self::THIS_MONTH, self::CUSTOM];

    public const int MAX_DAYS = 366;

    public function __construct(
        public string $key,
        public string $from,
        public string $to,
        public string $previousFrom,
        public string $previousTo,
    ) {}

    public static function resolve(string $key, CarbonImmutable $today, ?string $from = null, ?string $to = null): self
    {
        $today = $today->startOfDay();

        return match ($key) {
            self::YESTERDAY => self::span($key, $today->subDay(), $today->subDay()),
            self::LAST_7_DAYS => self::span($key, $today->subDays(6), $today),
            self::LAST_30_DAYS => self::span($key, $today->subDays(29), $today),
            self::THIS_MONTH => self::thisMonth($today),
            self::CUSTOM => self::custom($today, $from, $to),
            default => self::span(self::TODAY, $today, $today),
        };
    }

    /** Number of days in the period. */
    public function days(): int
    {
        return (int) CarbonImmutable::parse($this->from)->diffInDays(CarbonImmutable::parse($this->to)) + 1;
    }

    public function isSingleDay(): bool
    {
        return $this->from === $this->to;
    }

    /** @return array{key: string, from: string, to: string, previous_from: string, previous_to: string} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'from' => $this->from,
            'to' => $this->to,
            'previous_from' => $this->previousFrom,
            'previous_to' => $this->previousTo,
        ];
    }

    private static function span(string $key, CarbonImmutable $from, CarbonImmutable $to): self
    {
        $days = (int) $from->diffInDays($to) + 1;
        $previousTo = $from->subDay();

        return new self(
            $key,
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            $previousTo->subDays($days - 1)->format('Y-m-d'),
            $previousTo->format('Y-m-d'),
        );
    }

    /** Month to date vs the same days of last month (capped at its last day). */
    private static function thisMonth(CarbonImmutable $today): self
    {
        $start = $today->startOfMonth();
        $lastStart = $start->subMonthNoOverflow();
        $lastTo = $lastStart->addDays($today->day - 1);
        if ($lastTo->greaterThanOrEqualTo($start)) {
            $lastTo = $start->subDay();
        }

        return new self(
            self::THIS_MONTH,
            $start->format('Y-m-d'),
            $today->format('Y-m-d'),
            $lastStart->format('Y-m-d'),
            $lastTo->format('Y-m-d'),
        );
    }

    private static function custom(CarbonImmutable $today, ?string $from, ?string $to): self
    {
        $start = self::date($from);
        $end = self::date($to);
        if (! $start instanceof CarbonImmutable || ! $end instanceof CarbonImmutable) {
            return self::span(self::TODAY, $today, $today);
        }
        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }
        if ($start->diffInDays($end) >= self::MAX_DAYS) {
            $start = $end->subDays(self::MAX_DAYS - 1);
        }

        return self::span(self::CUSTOM, $start, $end);
    }

    private static function date(?string $value): ?CarbonImmutable
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }

        return $date instanceof CarbonImmutable && $date->format('Y-m-d') === $value ? $date : null;
    }
}
