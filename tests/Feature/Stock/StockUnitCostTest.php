<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Support\Database\Transaction;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use App\Support\Stock\ListUnitCost;
use App\Support\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\CatalogWorld;

afterEach(fn () => app(TenantContext::class)->forget());

it('keeps the product cost price when it has one', function (): void {
    expect(ListUnitCost::of(12_000, 65_000, 5_000))->toBe(['paisa' => 12_000, 'average' => false]);
});

it('falls back to the weighted average of the stock on hand when the cost price is 0', function (): void {
    expect(ListUnitCost::of(0, 65_000, 5_000))->toBe(['paisa' => 13_000, 'average' => true])
        // 100.00 / 3 units = 33.333.. -> 33.33 (half up)
        ->and(ListUnitCost::of(0, 10_000, 3_000))->toBe(['paisa' => 3_333, 'average' => true])
        ->and(ListUnitCost::of(0, 5, 2_000))->toBe(['paisa' => 3, 'average' => true]);
});

it('shows 0 without stock or value', function (): void {
    expect(ListUnitCost::of(0, 0, 5_000))->toBe(['paisa' => 0, 'average' => false])
        ->and(ListUnitCost::of(0, 5_000, 0))->toBe(['paisa' => 0, 'average' => false])
        ->and(ListUnitCost::of(0, 5_000, -1_000))->toBe(['paisa' => 0, 'average' => false]);
});

it('serves the average on the stock list for an imported product without a cost price', function (): void {
    $world = CatalogWorld::make();
    $polo = $world->product(['name' => 'China Polo', 'sku' => 'CP-1', 'cost_price' => Money::zero()]);
    app(TenantContext::class)->set($world->tenant, $world->branch);
    $ledger = app(StockLedger::class);
    foreach ([[2, 10_000], [3, 15_000]] as [$units, $cost]) {
        Transaction::run(fn () => $ledger->receive($polo, $world->branch, Quantity::ofUnits($units), Money::ofPaisa($cost), StockMovementType::Opening, null, ['type' => 'test', 'id' => 1]));
    }
    app(TenantContext::class)->forget();

    $this->actingAs($world->user)->withSession(['branch_id' => $world->branch->id])->get('/app/stock?q=CP-1')
        ->assertInertia(fn (Assert $page) => $page->where('rows.0.value', 65_000)
            ->where('rows.0.cost_price', 13_000)->where('rows.0.cost_is_average', true));
});
