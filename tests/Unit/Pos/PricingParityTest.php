<?php

declare(strict_types=1);

use App\Pos\Pricing\PriceCalculator;

/*
 * Golden cases shared with resources/js/engine/pricing.test.ts.
 * If one of these fails, the PHP and TypeScript twins have diverged.
 */

/** @return Generator<string, array{array<string, mixed>}> */
function pricingCases(): Generator
{
    /** @var array{cases: list<array<string, mixed>>} $file */
    $file = json_decode((string) file_get_contents(__DIR__.'/../../fixtures/pricing/cases.json'), true, 512, JSON_THROW_ON_ERROR);

    foreach ($file['cases'] as $case) {
        yield (string) $case['name'] => [$case];
    }
}

it('matches the golden pricing fixtures', function (array $case): void {
    expect(PriceCalculator::calculateCart($case['input']))->toBe($case['expected']);
})->with(fn () => pricingCases());

it('has at least 40 golden cases', function (): void {
    expect(iterator_count(pricingCases()))->toBeGreaterThanOrEqual(40);
});

it('keeps the totals consistent for 2,000 random carts', function (): void {
    mt_srand(20261103);
    $none = ['type' => 'none', 'value' => 0];

    for ($n = 0; $n < 2000; $n++) {
        $lines = [];
        for ($i = 0, $count = mt_rand(1, 12); $i < $count; $i++) {
            $kind = mt_rand(0, 2);
            $lines[] = [
                'unit_price' => mt_rand(0, 2_000_000),
                'qty_milli' => mt_rand(0, 50_000),
                'line_discount' => match ($kind) {
                    0 => $none,
                    1 => ['type' => 'percent_bp', 'value' => mt_rand(0, 12_000)],
                    default => ['type' => 'amount', 'value' => mt_rand(0, 500_000)],
                },
                'tax_rate_bp' => [0, 500, 750, 1500][mt_rand(0, 3)],
                'tax_inclusive' => (bool) mt_rand(0, 1),
            ];
        }
        $orderDiscount = match (mt_rand(0, 2)) {
            0 => $none,
            1 => ['type' => 'percent_bp', 'value' => mt_rand(0, 10_000)],
            default => ['type' => 'amount', 'value' => mt_rand(0, 3_000_000)],
        };
        $input = [
            'lines' => $lines,
            'order_discount' => $orderDiscount,
            'group_discount_bp' => mt_rand(0, 1) === 0 ? 0 : mt_rand(0, 3000),
            'rounding_step' => [0, 50, 100][mt_rand(0, 2)],
            'tenders' => [],
        ];

        $result = PriceCalculator::calculateCart($input);
        $sumLines = array_sum(array_column($result['lines'], 'total'));

        expect($sumLines + $result['order']['rounding'])->toBe($result['order']['grand_total']);
        expect(array_sum(array_column($result['lines'], 'order_discount')))->toBe($result['order']['order_discount']);
        expect(array_sum(array_column($result['lines'], 'discount')))->toBe($result['order']['discount_total']);
        expect($result['order']['grand_total'])->toBeGreaterThanOrEqual(0);

        if ($input['rounding_step'] > 0) {
            expect($result['order']['grand_total'] % $input['rounding_step'])->toBe(0);
        }
        foreach ($result['lines'] as $line) {
            expect($line['net'] + $line['tax'])->toBe($line['total']);
            expect($line['net'])->toBeGreaterThanOrEqual(0);
        }
    }
});

it('rejects invalid input', function (array $patch): void {
    $input = array_replace([
        'lines' => [['unit_price' => 100, 'qty_milli' => 1000, 'line_discount' => ['type' => 'none', 'value' => 0], 'tax_rate_bp' => 0, 'tax_inclusive' => false]],
        'order_discount' => ['type' => 'none', 'value' => 0],
        'group_discount_bp' => 0,
        'rounding_step' => 0,
        'tenders' => [],
    ], $patch);

    expect(fn () => PriceCalculator::calculateCart($input))->toThrow(InvalidArgumentException::class);
})->with([
    'unknown discount type' => [['order_discount' => ['type' => 'bogus', 'value' => 1]]],
    'negative discount' => [['order_discount' => ['type' => 'amount', 'value' => -1]]],
    'group over 100%' => [['group_discount_bp' => 10_001]],
    'negative step' => [['rounding_step' => -1]],
]);
