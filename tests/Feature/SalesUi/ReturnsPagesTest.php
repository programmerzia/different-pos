<?php

declare(strict_types=1);

use App\Enums\RefundMode;
use App\Models\BranchStock;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\SalesUi;

beforeEach(function (): void {
    $this->world = SalesUi::world();
    $this->shirt = $this->world->product(100_000, 40_000, ['name' => 'Panjabi']);
    $this->pants = $this->world->product(150_000, 60_000, ['name' => 'Trousers']);
    $this->world->stock($this->shirt, 20, 40_000);
    $this->world->stock($this->pants, 20, 60_000);
    $this->customer = $this->world->customer(['name' => 'Karim', 'shop_name' => 'Karim Fashion']);
    $this->sale = SalesUi::sale($this->world, $this->shirt, $this->customer, 3);
    $this->item = $this->sale->items->sole();
    $this->onHand = fn ($product): string => (string) BranchStock::query()->where('product_id', $product->id)->first()?->qty_on_hand->toDecimalString();
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('needs returns.view for the ledger and returns.create for the wizard', function (): void {
    $this->actingAs($this->world->user('Stock Keeper'))->get(route('returns.index'))->assertForbidden();
    $this->actingAs($this->world->user('Cashier'))->get(route('returns.index'))->assertOk();
    $this->actingAs($this->world->user('Cashier'))->get(route('returns.create'))->assertForbidden();
    $this->actingAs($this->world->user('Cashier'))->post(route('returns.store'), [])->assertForbidden();
});

it('finds the sale and lists its returnable lines', function (): void {
    $user = $this->world->user('Manager');

    $this->actingAs($user)->get(route('returns.create', ['q' => 'Karim']))
        ->assertInertia(fn (Assert $page) => $page->component('returns/create')->has('candidates', 1)->where('candidates.0.id', $this->sale->id));

    $this->actingAs($user)->get(route('returns.create', ['sale' => $this->sale->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'return')
            ->where('sale.lines.0.id', $this->item->id)
            ->where('sale.lines.0.returnable', 3000)
            ->where('sale.lines.0.payable', 300_000));

    $this->actingAs($user)->get(route('exchanges.create', ['sale' => $this->sale->id]))
        ->assertInertia(fn (Assert $page) => $page->where('mode', 'exchange')->where('can.exchange', true));
});

it('saves a refund return, restocks and opens the return page', function (): void {
    $response = $this->actingAs($this->world->user('Manager'))->post(route('returns.store'), [
        'sale_id' => $this->sale->id,
        'lines' => [['sale_item_id' => $this->item->id, 'qty' => 1000, 'restock' => true]],
        'refund_mode' => 'refund',
        'reason' => 'size',
    ]);

    $return = SaleReturn::query()->sole();
    $response->assertRedirect(route('returns.show', $return->id));
    expect($return->total->paisa)->toBe(100_000)
        ->and($return->refunded->paisa)->toBe(100_000)
        ->and($this->item->refresh()->qty_returned->milli)->toBe(1000)
        ->and(($this->onHand)($this->shirt))->toBe('18.000');

    $this->get(route('returns.show', $return->id))
        ->assertInertia(fn (Assert $page) => $page->component('returns/show')->where('saleReturn.number', $return->number)->where('lines.0.restock', true)->where('lines.0.cogs_reversed', 40_000));
    $this->actingAs($this->world->user('Cashier'))->get(route('returns.show', $return->id))->assertInertia(fn (Assert $page) => $page->where('lines.0.cogs_reversed', null));
    $this->actingAs($this->world->user('Manager'));
    $this->get(route('returns.receipt', $return->id))->assertOk()->assertSee($return->number)->assertSee('Panjabi');
});

it('refuses more than the returnable quantity with a 422', function (): void {
    $this->actingAs($this->world->user('Manager'))->postJson(route('returns.store'), [
        'sale_id' => $this->sale->id,
        'lines' => [['sale_item_id' => $this->item->id, 'qty' => 4000, 'restock' => true]],
        'refund_mode' => 'refund',
        'reason' => 'size',
    ])->assertUnprocessable()->assertJsonPath('errors.lines.0', sprintf('Sale line %d: 4.000 requested, only 3.000 can be returned.', $this->item->id));

    expect(SaleReturn::query()->count())->toBe(0);
});

it('rejects a sale of another branch at validation', function (): void {
    $branch = SalesUi::otherBranch($this->world);
    $other = SalesUi::inBranch($this->world, $branch, fn () => Sale::factory()->create(['tenant_id' => $this->world->tenant->id, 'branch_id' => $branch->id, 'customer_id' => $this->customer->id, 'number' => 'DF2-9']));

    $this->actingAs($this->world->user('Manager'))->postJson(route('returns.store'), [
        'sale_id' => $other->id,
        'lines' => [['sale_item_id' => $this->item->id, 'qty' => 1000, 'restock' => true]],
        'refund_mode' => 'refund',
        'reason' => 'size',
    ])->assertUnprocessable()->assertJsonValidationErrors('sale_id');
    $this->get(route('returns.create', ['sale' => $other->id]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('sale', null));
});

it('exchanges a shirt for trousers and settles the difference', function (): void {
    $this->actingAs($this->world->user('Manager'))->post(route('exchanges.store'), [
        'sale_id' => $this->sale->id,
        'lines' => [['sale_item_id' => $this->item->id, 'qty' => 1000, 'restock' => true]],
        'items' => [['product_id' => $this->pants->id, 'qty' => 1000]],
        'tenders' => [['payment_method_id' => $this->world->methods['cash']->id, 'amount' => 50_000]],
        'surplus_mode' => 'refund',
        'reason' => 'size',
    ])->assertRedirect();

    $return = SaleReturn::query()->sole();
    $replacement = Sale::query()->findOrFail($return->exchange_sale_id);
    expect($return->refund_mode)->toBe(RefundMode::Exchange)
        ->and($replacement->total->paisa)->toBe(150_000)
        ->and($replacement->due->paisa)->toBe(0)
        ->and(($this->onHand)($this->pants))->toBe('19.000')
        ->and(($this->onHand)($this->shirt))->toBe('18.000');

    $this->get(route('returns.show', $return->id))->assertInertia(fn (Assert $page) => $page->where('exchange_sale.id', $replacement->id)->where('exchange_sale.difference', 50_000));
});

it('needs pos.sell for an exchange', function (): void {
    $this->world->user('Accountant')->givePermissionTo('returns.create');

    $this->actingAs($this->world->user('Accountant'))->post(route('exchanges.store'), [])->assertForbidden();
    $this->actingAs($this->world->user('Accountant'))->get(route('exchanges.create'))->assertForbidden();
});
