<?php

declare(strict_types=1);

use App\Actions\Purchasing\ReceivePurchase;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\StockAdjustment;
use App\Support\Money\Money;
use App\Support\Permissions\SystemRoles;
use App\Support\Quantity\Quantity;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\CatalogWorld;

afterEach(fn () => app(TenantContext::class)->forget());

function stocked(CatalogWorld $world, int $units, array $product = []): array
{
    $world->enter();
    $item = $world->product($product);
    app(ReceivePurchase::class)->handle($world->user, [
        'vendor_id' => $world->vendor()->id,
        'items' => [['product_id' => $item->id, 'qty' => Quantity::ofUnits($units), 'unit_cost' => Money::ofPaisa(10000)]],
    ]);

    return [$item];
}

it('requires stock.view', function (): void {
    $world = CatalogWorld::make(role: null);
    $this->actingAs($world->user)->get('/app/stock')->assertForbidden();
    $this->actingAs($world->user)->get('/app/adjustments')->assertForbidden();
});

it('shows on-hand per branch with value, low-stock and out filters', function (): void {
    $world = CatalogWorld::make();
    [$low] = stocked($world, 2, ['name' => 'Low One', 'reorder_level' => Quantity::ofUnits(5)]);
    [$fine] = stocked($world, 20, ['name' => 'Fine One']);
    $world->product(['name' => 'Empty One']);

    $this->actingAs($world->user)->get('/app/stock')
        ->assertInertia(fn (Assert $page) => $page->component('stock/index')->has('rows', 3)->where('valuation', 220000)->where('branch.id', $world->branch->id));
    $this->actingAs($world->user)->get('/app/stock?filter[status]=low')
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.name', 'Low One')->where('rows.0.low_stock', true)->where('rows.0.on_hand', 2000));
    $this->actingAs($world->user)->get('/app/stock?filter[status]=out')
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.name', 'Empty One'));
});

it('hides stock value from users without products.view_cost', function (): void {
    $world = CatalogWorld::make(SystemRoles::CASHIER);
    stocked($world, 3);

    $this->actingAs($world->user)->get('/app/stock')
        ->assertInertia(fn (Assert $page) => $page->where('valuation', null)->where('rows.0.value', null));
});

it('keeps stock per branch: another branch has its own on-hand', function (): void {
    $world = CatalogWorld::make();
    [$item] = stocked($world, 6);
    $second = app(TenantContext::class)->withoutTenancy(fn () => Branch::factory()->for($world->tenant)->create());
    $world->user->branches()->attach($second);

    $this->actingAs($world->user)->withSession(['branch_id' => $second->id])->get('/app/stock')
        ->assertInertia(fn (Assert $page) => $page->where('branch.id', $second->id)->where('rows.0.on_hand', 0));
    expect(BranchStock::query()->withoutGlobalScopes()->where('product_id', $item->id)->count())->toBe(1);
});

it('lists a product\'s movements with a running balance, newest first', function (): void {
    $world = CatalogWorld::make();
    [$item] = stocked($world, 5);

    $this->actingAs($world->user)->post('/app/adjustments', ['reason' => 'damage', 'items' => [['product_id' => $item->id, 'qty' => -2000]]])->assertRedirect('/app/adjustments');

    $this->actingAs($world->user)->get("/app/stock/{$item->uuid}/movements")
        ->assertInertia(fn (Assert $page) => $page->component('stock/movements')
            ->where('onHand', 3000)->has('rows', 2)
            ->where('rows.0.type', 'adjustment_out')->where('rows.0.qty', -2000)->where('rows.0.balance', 3000)
            ->where('rows.1.type', 'purchase')->where('rows.1.balance', 5000));
});

it('404s movements of another tenant\'s product', function (): void {
    $mine = CatalogWorld::make();
    $theirs = CatalogWorld::make()->product();

    $this->actingAs($mine->user)->get("/app/stock/{$theirs->uuid}/movements")->assertNotFound();
});

it('adjusts stock up and down and lists the adjustments', function (): void {
    $world = CatalogWorld::make();
    $item = $world->product(['cost_price' => Money::ofPaisa(5000)]);

    $this->actingAs($world->user)->post('/app/adjustments', [
        'reason' => 'found', 'note' => 'Back room', 'items' => [['product_id' => $item->id, 'qty' => 4000]],
    ])->assertRedirect('/app/adjustments');

    $world->enter();
    expect(BranchStock::query()->where('product_id', $item->id)->firstOrFail()->qty_on_hand->milli)->toBe(4000)
        ->and(StockAdjustment::query()->count())->toBe(1);

    $this->actingAs($world->user)->get('/app/adjustments')
        ->assertInertia(fn (Assert $page) => $page->component('adjustments/index')->has('rows', 1)->where('rows.0.reason', 'found')->where('rows.0.lines', 1));
    $this->actingAs($world->user)->get('/app/adjustments?filter[reason]=loss')
        ->assertInertia(fn (Assert $page) => $page->has('rows', 0));
});

it('rejects invalid adjustments', function (): void {
    $world = CatalogWorld::make();
    $other = CatalogWorld::make();
    $item = $world->product();

    $this->actingAs($world->user)->post('/app/adjustments', ['reason' => 'bogus', 'items' => []])->assertSessionHasErrors(['reason', 'items']);
    $this->actingAs($world->user)->post('/app/adjustments', ['reason' => 'loss', 'items' => [['product_id' => $item->id, 'qty' => 0]]])->assertSessionHasErrors('items.0.qty');
    $this->actingAs($world->user)->post('/app/adjustments', ['reason' => 'loss', 'items' => [['product_id' => $other->product()->id, 'qty' => 1000]]])->assertSessionHasErrors('items.0.product_id');
    // Removing stock that is not there is refused by the action (allow_negative_stock is off by default).
    $this->actingAs($world->user)->post('/app/adjustments', ['reason' => 'loss', 'items' => [['product_id' => $item->id, 'qty' => -1000]]])->assertSessionHasErrors('items');
});

it('forbids adjusting without stock.adjust but allows viewing with stock.view', function (): void {
    $world = CatalogWorld::make(SystemRoles::CASHIER);
    $item = $world->product();

    $this->actingAs($world->user)->get('/app/stock')->assertOk();
    $this->actingAs($world->user)->get('/app/adjustments/create')->assertForbidden();
    $this->actingAs($world->user)->post('/app/adjustments', ['reason' => 'found', 'items' => [['product_id' => $item->id, 'qty' => 1000]]])->assertForbidden();
    $this->actingAs($world->user)->getJson('/app/adjustments/lookup?code=x')->assertForbidden();
});
