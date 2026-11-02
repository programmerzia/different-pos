<?php

declare(strict_types=1);

namespace App\Support\Sales;

use App\Support\Money\Money;
use Carbon\CarbonInterface;

/**
 * Aging buckets for open dues by the age of the sale in whole business days (tenant timezone):
 * 0–30, 31–60, 61–90, 90+. Mirrors resources/js/lib/dues.ts.
 */
final class DueAging
{
    public const array BUCKETS = ['d0_30', 'd31_60', 'd61_90', 'd90_plus'];

    public static function bucket(int $days): string
    {
        return match (true) {
            $days <= 30 => 'd0_30',
            $days <= 60 => 'd31_60',
            $days <= 90 => 'd61_90',
            default => 'd90_plus',
        };
    }

    public static function age(CarbonInterface $soldAt, CarbonInterface $now, string $timezone): int
    {
        return max(0, BusinessDay::daysBetween($soldAt, $now, $timezone));
    }

    /**
     * Oldest-first allocation preview, the same walk CollectCustomerPayment does.
     *
     * @param  list<array{id: int, due: Money}>  $dues  oldest first
     * @return array{allocations: array<int, Money>, advance: Money}
     */
    public static function allocateOldestFirst(array $dues, Money $amount): array
    {
        $left = $amount;
        $allocations = [];
        foreach ($dues as $due) {
            if ($left->paisa <= 0) {
                break;
            }
            if ($due['due']->paisa <= 0) {
                continue;
            }
            $share = $due['due']->compareTo($left) < 0 ? $due['due'] : $left;
            $allocations[$due['id']] = $share;
            $left = $left->minus($share);
        }

        return ['allocations' => $allocations, 'advance' => $left->isNegative() ? Money::zero() : $left];
    }
}
