<?php

declare(strict_types=1);

use App\Support\Dates\Period;
use Carbon\CarbonImmutable;

/* One definition of the named periods for every list and report: the week starts on Saturday. */

$tuesday = CarbonImmutable::parse('2026-11-03 09:00', 'Asia/Dhaka');

it('starts "this week" on the Saturday on or before today, never a rolling 7 days', function () use ($tuesday): void {
    expect(Period::range('week', 'Asia/Dhaka', $tuesday))->toBe(['from' => '2026-10-31', 'to' => '2026-11-03'])
        ->and(Period::range('this_week', 'Asia/Dhaka', $tuesday))->toBe(['from' => '2026-10-31', 'to' => '2026-11-03'])
        ->and(Period::range('week', 'Asia/Dhaka', CarbonImmutable::parse('2026-10-31 23:00', 'Asia/Dhaka')))->toBe(['from' => '2026-10-31', 'to' => '2026-10-31'])
        ->and(Period::range('week', 'Asia/Dhaka', CarbonImmutable::parse('2026-11-06 23:00', 'Asia/Dhaka')))->toBe(['from' => '2026-10-31', 'to' => '2026-11-06']);
});

it('resolves today in the tenant timezone, not UTC', function (): void {
    // 20:00 UTC on 2 Nov is already 3 Nov in Dhaka.
    expect(Period::range('today', 'Asia/Dhaka', CarbonImmutable::parse('2026-11-02 20:00', 'UTC')))->toBe(['from' => '2026-11-03', 'to' => '2026-11-03']);
});

it('resolves the other named periods', function () use ($tuesday): void {
    expect(Period::range('yesterday', 'Asia/Dhaka', $tuesday))->toBe(['from' => '2026-11-02', 'to' => '2026-11-02'])
        ->and(Period::range('month', 'Asia/Dhaka', $tuesday))->toBe(['from' => '2026-11-01', 'to' => '2026-11-03'])
        ->and(Period::range('last_month', 'Asia/Dhaka', $tuesday))->toBe(['from' => '2026-10-01', 'to' => '2026-10-31'])
        ->and(Period::range('last_quarter', 'Asia/Dhaka', $tuesday))->toBe(['from' => '2026-07-01', 'to' => '2026-09-30'])
        ->and(Period::range('fiscal_year', 'Asia/Dhaka', $tuesday))->toBe(['from' => '2026-07-01', 'to' => '2026-11-03'])
        ->and(Period::range('fiscal_year', 'Asia/Dhaka', CarbonImmutable::parse('2027-03-31', 'Asia/Dhaka')))->toBe(['from' => '2026-07-01', 'to' => '2027-03-31']);
});

it('ignores unknown keys', function () use ($tuesday): void {
    expect(Period::range('7d', 'Asia/Dhaka', $tuesday))->toBeNull()
        ->and(Period::range(null, 'Asia/Dhaka', $tuesday))->toBeNull()
        ->and(Period::range('', 'Asia/Dhaka', $tuesday))->toBeNull();
});
