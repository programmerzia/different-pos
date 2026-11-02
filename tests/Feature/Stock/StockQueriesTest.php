<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Product;
use App\Support\Database\Transaction;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Stock\StockLedger;
use App\Support\Stock\StockQueries;
use App\Support\Tenancy\TenantContext;
use Tests\Fixtures\StockWorld;

beforeEach(function (): void {
    $this->world = StockWorld::make();
    $this->branch = $this->world->branch;
    $this->queries = app(StockQueries::class);

    $this->receive = fn (Product $product, string $qty, int $paisa, ?Branch $branch = null): mixed => Transaction::run(fn () => app(StockLedger::class)->receive(
        $product, $branch ?? $this->branch, Quantity::parse($qty), Money::ofPaisa($paisa), StockMovementType::Purchase, null, ['type' => 'test', 'id' => 1],
    ));
    $this->issue = fn (Product $product, string $qty): mixed => Transaction::run(fn () => app(StockLedger::class)->issue(
        $product, $this->branch, Quantity::parse($qty), StockMovementType::Sale, ['type' => 'test', 'id' => 2],
    ));
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('reports on-hand per product and branch', function (): void {
    $a = $this->world->product();
    $b = $this->world->product();
    ($this->receive)($a, '4', 100);
    ($this->issue)($a, '1.5');
    ($this->receive)($b, '2', 100);

    expect($this->queries->onHand($a, $this->branch)->toDecimalString())->toBe('2.500')
        ->and($this->queries->onHand($this->world->product(), $this->branch)->toDecimalString())->toBe('0.000')
        ->and(array_map(fn (Quantity $q): string => $q->toDecimalString(), $this->queries->onHandByProduct($this->branch)))
        ->toBe([$a->id => '2.500', $b->id => '2.000']);
});

it('values stock as Σ qty_remaining × unit_cost, rounded per batch', function (): void {
    $a = $this->world->product();
    $b = $this->world->product();
    ($this->receive)($a, '2.5', 10001);  // 25002.5 → 25003
    ($this->receive)($a, '3', 100);      // 300
    ($this->receive)($b, '1', 99999);    // 99999
    ($this->issue)($b, '1');             // fully consumed: 0

    expect($this->queries->valuation($this->branch)->paisa)->toBe(25303)
        ->and(array_map(fn (Money $m): int => $m->paisa, $this->queries->valuationByProduct($this->branch)))->toBe([$a->id => 25303]);
});

it('lists movements with a running balance', function (): void {
    $a = $this->world->product();
    ($this->receive)($a, '3', 100);
    ($this->receive)($a, '2', 200);
    ($this->issue)($a, '4');

    expect(array_map(fn (array $row): array => [$row['movement']->qty->toDecimalString(), $row['balance']->toDecimalString()], $this->queries->movements($a, $this->branch)))
        ->toBe([['3.000', '3.000'], ['2.000', '5.000'], ['-3.000', '2.000'], ['-1.000', '1.000']]);
});

it('flags stocked products at or below their reorder level', function (): void {
    $low = $this->world->product(['name' => 'A low', 'reorder_level' => Quantity::ofUnits(5)]);
    $edge = $this->world->product(['name' => 'B edge', 'reorder_level' => Quantity::ofUnits(5)]);
    $fine = $this->world->product(['name' => 'C fine', 'reorder_level' => Quantity::ofUnits(5)]);
    $never = $this->world->product(['name' => 'D never stocked', 'reorder_level' => Quantity::ofUnits(2)]);
    $this->world->product(['name' => 'E unmonitored']);
    $this->world->product(['name' => 'F service', 'reorder_level' => Quantity::ofUnits(5), 'track_stock' => false]);
    ($this->receive)($low, '3', 100);
    ($this->receive)($edge, '5', 100);
    ($this->receive)($fine, '6', 100);

    // Stock in another branch does not count here.
    $other = makeBranch($this->world->tenant);
    app(TenantContext::class)->set($this->world->tenant, $other);
    ($this->receive)($never, '10', 100, $other);
    app(TenantContext::class)->set($this->world->tenant, $this->branch);

    expect(array_map(fn (array $row): array => [$row['product']->id, $row['on_hand']->toDecimalString()], $this->queries->lowStock($this->branch)))
        ->toBe([[$low->id, '3.000'], [$edge->id, '5.000'], [$never->id, '0.000']]);
});

it('never reads another tenant\'s stock', function (): void {
    $mine = $this->world->product();
    ($this->receive)($mine, '1', 100);

    $context = app(TenantContext::class);
    $foreign = StockWorld::make();
    $theirs = $foreign->product();
    ($this->receive)($theirs, '7', 1000, $foreign->branch);

    $context->set($this->world->tenant, $this->branch);
    expect($this->queries->valuation($foreign->branch)->paisa)->toBe(0)
        ->and($this->queries->onHand($theirs, $foreign->branch)->toDecimalString())->toBe('0.000')
        ->and($this->queries->movements($theirs, $foreign->branch))->toBe([])
        ->and($this->queries->onHandByProduct($foreign->branch))->toBe([]);
});
