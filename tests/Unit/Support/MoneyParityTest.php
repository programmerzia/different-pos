<?php

declare(strict_types=1);

use App\Exceptions\Domain\InvalidMoney;
use App\Exceptions\Domain\InvalidQuantity;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;

/*
 * Golden cases shared with resources/js/engine/money.test.ts.
 * If one of these fails, the PHP and TypeScript twins have diverged.
 */

/** @return array<string, list<array<string, mixed>>> */
function moneyCases(): array
{
    /** @var array<string, list<array<string, mixed>>> $cases */
    $cases = json_decode((string) file_get_contents(__DIR__.'/../../fixtures/money/cases.json'), true, 512, JSON_THROW_ON_ERROR);

    return $cases;
}

/** @return Generator<string, array{array<string, mixed>}> */
function casesFor(string $group): Generator
{
    foreach (moneyCases()[$group] as $i => $case) {
        yield $group.' #'.$i.' '.json_encode($case, JSON_UNESCAPED_UNICODE) => [$case];
    }
}

it('parses money', function (array $case): void {
    if (isset($case['error'])) {
        expect(fn () => Money::parse($case['in']))->toThrow(InvalidMoney::class);

        return;
    }
    expect(Money::parse($case['in'])->paisa)->toBe($case['out']);
})->with(fn () => casesFor('parse'));

it('parses money with rounding', function (array $case): void {
    expect(Money::parseRounded($case['in'])->paisa)->toBe($case['out']);
})->with(fn () => casesFor('parseRounded'));

it('multiplies by a quantity', function (array $case): void {
    $run = fn (): Money => Money::ofPaisa($case['paisa'])->times(Quantity::ofMilli($case['milli']));
    if (isset($case['error'])) {
        expect($run)->toThrow(InvalidMoney::class);

        return;
    }
    expect($run()->paisa)->toBe($case['out']);
})->with(fn () => casesFor('timesQty'));

it('takes a basis-point percentage', function (array $case): void {
    expect(Money::ofPaisa($case['paisa'])->percentOfBp($case['bp'])->paisa)->toBe($case['out']);
})->with(fn () => casesFor('percentOfBp'));

it('allocates by weights', function (array $case): void {
    $run = fn (): array => array_map(fn (Money $m): int => $m->paisa, Money::ofPaisa($case['paisa'])->allocate($case['weights']));
    if (isset($case['error'])) {
        expect($run)->toThrow(InvalidMoney::class);

        return;
    }
    expect($run())->toBe($case['out']);
})->with(fn () => casesFor('allocate'));

it('formats money', function (array $case): void {
    $symbol = $case['symbol'] ?? true;
    expect(Money::ofPaisa($case['paisa'])->format('en', $symbol))->toBe($case['en'])
        ->and(Money::ofPaisa($case['paisa'])->format('bn', $symbol))->toBe($case['bn']);
})->with(fn () => casesFor('format'));

it('renders a decimal string', function (array $case): void {
    expect(Money::ofPaisa($case['paisa'])->toDecimalString())->toBe($case['out']);
})->with(fn () => casesFor('toDecimalString'));

it('parses quantities', function (array $case): void {
    if (isset($case['error'])) {
        expect(fn () => Quantity::parse($case['in']))->toThrow(InvalidQuantity::class);

        return;
    }
    expect(Quantity::parse($case['in'])->milli)->toBe($case['out']);
})->with(fn () => casesFor('qtyParse'));

it('formats quantities', function (array $case): void {
    expect(Quantity::ofMilli($case['milli'])->format('en'))->toBe($case['en'])
        ->and(Quantity::ofMilli($case['milli'])->format('bn'))->toBe($case['bn']);
})->with(fn () => casesFor('qtyFormat'));

it('renders quantity decimal strings', function (array $case): void {
    expect(Quantity::ofMilli($case['milli'])->toDecimalString())->toBe($case['out']);
})->with(fn () => casesFor('qtyDecimal'));
