<?php

declare(strict_types=1);

use App\Actions\Purchasing\ReceivePurchase;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Support\Money\Money;
use App\Support\Permissions\SystemRoles;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\CatalogWorld;

afterEach(fn () => app(TenantContext::class)->forget());

/** Receives `$units` at ৳100.00 each into `$branch` through the real purchase action (ledger + batches). */
function receiveInto(CatalogWorld $world, Branch $branch, Product $product, int $units): void
{
    app(TenantContext::class)->set($world->tenant, $branch);
    app(ReceivePurchase::class)->handle($world->user, [
        'vendor_id' => $world->vendor()->id,
        'items' => [['product_id' => $product->id, 'qty' => Quantity::ofUnits($units), 'unit_cost' => Money::ofPaisa(10000)]],
    ]);
    app(TenantContext::class)->forget();
}

function stockBranch(CatalogWorld $world): Branch
{
    $branch = app(TenantContext::class)->withoutTenancy(fn () => Branch::factory()->for($world->tenant)->create(['name' => 'Branch 2', 'is_active' => true]));
    $world->user->branches()->attach($branch);

    return $branch;
}

it('shows the owner every branch, combined totals, unit cost and FIFO value across branches', function (): void {
    $world = CatalogWorld::make();
    $second = stockBranch($world);
    $panjabi = $world->product(['name' => 'Panjabi', 'sku' => 'P-1', 'image_path' => 'products/p.png']);
    $saree = $world->product(['name' => 'Saree', 'sku' => 'S-1']);
    receiveInto($world, $world->branch, $panjabi, 3);
    receiveInto($world, $second, $panjabi, 2);
    receiveInto($world, $second, $saree, 4);

    $this->actingAs($world->user)->withSession(['branch_id' => $world->branch->id])->get('/app/stock?sort=sku')
        ->assertInertia(fn (Assert $page) => $page->component('stock/index')
            ->has('branches', 2)->where('branches.0.current', true)->where('branches.1.name', 'Branch 2')
            ->where('rows.0.sku', 'P-1')->where('rows.0.stock', [3000, 2000])->where('rows.0.total_stock', 5000)
            ->where('rows.0.on_hand', 3000)->where('rows.0.value', 50000)->where('rows.0.cost_price', 10000)
            ->where('rows.0.image_url', fn ($url) => is_string($url) && str_ends_with($url, 'products/p.png'))
            ->where('rows.1.stock', [0, 4000])->where('rows.1.value', 40000)
            ->where('totals.stock', [3000, 6000])->where('totals.total_stock', 9000)
            ->where('totals.value', 90000)->where('totals.cost_price', 20000)
            ->where('valuation', 90000));

    $this->actingAs($world->user)->withSession(['branch_id' => $world->branch->id])->get('/app/stock?q=saree')
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('totals.stock', [0, 4000])->where('totals.value', 40000));
});

it('shows a manager only the current branch and a cashier no money', function (): void {
    $world = CatalogWorld::make(SystemRoles::MANAGER);
    $second = stockBranch($world);
    $item = $world->product(['name' => 'Kurti']);
    receiveInto($world, $world->branch, $item, 1);
    receiveInto($world, $second, $item, 7);

    $this->actingAs($world->user)->withSession(['branch_id' => $world->branch->id])->get('/app/stock')
        ->assertInertia(fn (Assert $page) => $page->has('branches', 1)->where('rows.0.stock', [1000])->where('totals.total_stock', 1000)->where('totals.value', 10000));

    $cashier = CatalogWorld::make(SystemRoles::CASHIER);
    receiveInto($cashier, $cashier->branch, $cashier->product(), 2);
    $this->actingAs($cashier->user)->get('/app/stock')
        ->assertInertia(fn (Assert $page) => $page->where('totals.value', null)->where('totals.cost_price', null)->where('rows.0.cost_price', null)->where('canAdjust', false));
});

it('filters sufficient stock and never counts another tenant\'s stock', function (): void {
    $world = CatalogWorld::make();
    $other = CatalogWorld::make();
    $plenty = $world->product(['name' => 'Plenty', 'reorder_level' => Quantity::ofUnits(2)]);
    $short = $world->product(['name' => 'Short', 'reorder_level' => Quantity::ofUnits(5)]);
    receiveInto($world, $world->branch, $plenty, 10);
    receiveInto($world, $world->branch, $short, 3);
    receiveInto($other, $other->branch, $other->product(), 99);

    $this->actingAs($world->user)->get('/app/stock?filter[status]=ok')
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.name', 'Plenty'));
    $this->actingAs($world->user)->get('/app/stock')
        ->assertInertia(fn (Assert $page) => $page->has('rows', 2)->where('totals.total_stock', 13000));
    expect(BranchStock::query()->withoutGlobalScopes()->count())->toBe(3);
});

it('prefills a stock adjustment from the stock row, but never with another tenant\'s product', function (): void {
    $world = CatalogWorld::make();
    $item = $world->product(['name' => 'Adjust Me', 'sku' => 'AM-1']);
    $service = $world->product(['name' => 'Service', 'track_stock' => false]);
    $theirs = CatalogWorld::make()->product(['name' => 'Theirs']);

    $this->actingAs($world->user)->get("/app/adjustments/create?product={$item->uuid}")
        ->assertInertia(fn (Assert $page) => $page->component('adjustments/create')
            ->where('prefill.id', $item->id)->where('prefill.sku', 'AM-1')->where('prefill.name', 'Adjust Me'));
    $this->actingAs($world->user)->get("/app/adjustments/create?product={$theirs->uuid}")
        ->assertInertia(fn (Assert $page) => $page->where('prefill', null));
    $this->actingAs($world->user)->get("/app/adjustments/create?product={$service->uuid}")
        ->assertInertia(fn (Assert $page) => $page->where('prefill', null));
    $this->actingAs($world->user)->get('/app/adjustments/create')
        ->assertInertia(fn (Assert $page) => $page->where('prefill', null));
});

it('filters by the date of the last stock movement at the current branch', function (): void {
    $world = CatalogWorld::make();
    $second = stockBranch($world);
    $moved = $world->product(['name' => 'Moved', 'sku' => 'MV-1']);
    $elsewhere = $world->product(['name' => 'Elsewhere', 'sku' => 'EW-1']);
    $world->product(['name' => 'Still', 'sku' => 'ST-1']);
    receiveInto($world, $world->branch, $moved, 2);
    receiveInto($world, $second, $elsewhere, 3);
    $other = CatalogWorld::make();
    receiveInto($other, $other->branch, $other->product(['name' => 'Foreign', 'sku' => 'FX-1']), 1);

    $names = fn (string $filter): array => array_column($this->actingAs($world->user)->withSession(['branch_id' => $world->branch->id])
        ->get('/app/stock?filter[moved]='.$filter)->viewData('page')['props']['rows'], 'name');

    expect($names('today'))->toBe(['Moved'])
        ->and($names('30d'))->toBe(['Moved'])
        // Movement at another branch does not count for this one.
        ->and($names('idle_90d'))->toBe(['Elsewhere', 'Still'])
        ->and($names(''))->toBe(['Elsewhere', 'Moved', 'Still']);
});
