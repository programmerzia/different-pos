<?php

declare(strict_types=1);

use App\Enums\RefundMode;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\BranchStock;
use App\Models\CustomerGroup;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\TaxRate;
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

it('lists the ledger with items, refund method, cashier, reason chips and totals', function (): void {
    $manager = $this->world->user('Manager');
    $this->actingAs($manager)->post(route('returns.store'), [
        'sale_id' => $this->sale->id,
        'lines' => [['sale_item_id' => $this->item->id, 'qty' => 1000, 'restock' => false]],
        'refund_mode' => 'refund',
        'refund_method_id' => $this->world->methods['cash']->id,
        'reason' => 'defect',
    ])->assertRedirect();

    $this->actingAs($this->world->user('Cashier'))->get(route('returns.index'))
        ->assertInertia(fn (Assert $page) => $page->component('returns/index')
            ->has('rows', 1)
            ->where('rows.0.lines.0.name', 'Panjabi')
            ->where('rows.0.lines.0.qty', 1000)
            ->where('rows.0.restocked', 0)
            ->where('rows.0.refund_method', $this->world->methods['cash']->name)
            ->where('rows.0.cashier', $manager->name)
            ->where('rows.0.reason', 'defect')
            ->where('totals.total', 100_000)
            ->where('totals.refunded', 100_000)
            ->missing('wizard'));

    $this->get(route('returns.index', ['filter' => ['reason' => 'size']]))->assertInertia(fn (Assert $page) => $page->has('rows', 0));
    $this->get(route('returns.index', ['filter' => ['reason' => 'defect']]))->assertInertia(fn (Assert $page) => $page->has('rows', 1));

    // Named period (Bangladesh week, Saturday to today): today's return is in this week; last month's range is empty.
    $this->get(route('returns.index', ['filter' => ['period' => 'week']]))->assertInertia(fn (Assert $page) => $page->has('rows', 1));
    $this->get(route('returns.index', ['filter' => ['period' => 'last_month']]))->assertInertia(fn (Assert $page) => $page->has('rows', 0));
});

it('loads the wizard dialog alone, never another tenant\'s sale', function (): void {
    $foreign = SalesUi::foreignSale();
    $partial = fn (array $query) => $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'returns/index',
        'X-Inertia-Partial-Data' => 'wizard',
    ])->get(route('returns.index', $query))->assertOk()->json('props');

    $this->actingAs($this->world->user('Manager'));
    $found = $partial(['wizard_q' => 'Karim']);
    expect($found)->not->toHaveKey('rows')
        ->and($found['wizard']['candidates'][0]['id'])->toBe($this->sale->id)
        ->and($partial(['wizard_sale' => $this->sale->id])['wizard']['sale']['lines'][0]['returnable'])->toBe(3000)
        ->and($partial(['wizard_sale' => $foreign->id])['wizard']['sale'])->toBeNull();

    $this->actingAs($this->world->user('Cashier'));
    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'returns/index',
        'X-Inertia-Partial-Data' => 'wizard',
    ])->get(route('returns.index', ['wizard_q' => 'Karim']))->assertForbidden();
});

it('quotes exactly what the exchange then saves, with group discount and VAT', function (): void {
    $group = CustomerGroup::factory()->create(['tenant_id' => $this->world->tenant->id, 'discount_bp' => 1000]);
    $vat = TaxRate::factory()->create(['tenant_id' => $this->world->tenant->id, 'rate_bp' => 1500]);
    $taxed = $this->world->product(150_000, 60_000, ['name' => 'Taxed coat', 'tax_rate_id' => $vat->id]);
    $this->world->stock($taxed, 5, 60_000);
    $this->customer->update(['customer_group_id' => $group->id]);
    $payload = [
        'sale_id' => $this->sale->id,
        'lines' => [['sale_item_id' => $this->item->id, 'qty' => 1000, 'restock' => true]],
        'items' => [['product_id' => $taxed->id, 'qty' => 1000]],
    ];
    $manager = $this->world->user('Manager');

    $quote = $this->actingAs($manager)->postJson(route('exchanges.quote'), $payload)->assertOk();
    expect(SaleReturn::query()->count())->toBe(0);

    $this->actingAs($manager)->post(route('exchanges.store'), [
        ...$payload,
        'tenders' => [['payment_method_id' => $this->world->methods['cash']->id, 'amount' => $quote->json('pays')]],
        'surplus_mode' => 'refund',
        'reason' => 'size',
    ])->assertRedirect();

    $return = SaleReturn::query()->sole();
    $replacement = Sale::query()->findOrFail($return->exchange_sale_id);
    expect($quote->json('replacement_total'))->toBe($replacement->total->paisa)
        ->and($quote->json('return_value'))->toBe($return->total->paisa)
        ->and($quote->json('group_discount'))->toBeGreaterThan(0)
        ->and($replacement->due->paisa)->toBe(0)
        ->and($replacement->sync_flags ?? [])->not->toContain('price_mismatch');
});

it('quotes stock shortages, the due cleared and what comes back', function (): void {
    $scarce = $this->world->product(10_000, 4_000, ['name' => 'Scarce']);
    $this->world->stock($scarce, 1, 4_000);

    $quote = $this->actingAs($this->world->user('Manager'))->postJson(route('exchanges.quote'), [
        'sale_id' => $this->sale->id,
        'lines' => [['sale_item_id' => $this->item->id, 'qty' => 1000, 'restock' => true]],
        'items' => [['product_id' => $scarce->id, 'qty' => 2000]],
    ])->assertOk();

    expect($quote->json('lines.0.short'))->toBeTrue()
        ->and($quote->json('lines.0.stock'))->toBe(1000)
        ->and($quote->json('surplus'))->toBe($quote->json('due_cleared') + $quote->json('gets_back'));
});

it('refuses a raw material in the quote and guards who and which sale can be quoted', function (): void {
    $fabric = $this->world->product(0, 25_000, ['name' => 'Fabric', 'item_type' => 'raw_material']);
    $payload = [
        'sale_id' => $this->sale->id,
        'lines' => [['sale_item_id' => $this->item->id, 'qty' => 1000, 'restock' => true]],
        'items' => [['product_id' => $fabric->id, 'qty' => 1000]],
    ];

    $this->actingAs($this->world->user('Manager'))->postJson(route('exchanges.quote'), $payload)->assertUnprocessable()->assertJsonValidationErrors('lines');
    $this->actingAs($this->world->user('Stock Keeper'))->postJson(route('exchanges.quote'), $payload)->assertForbidden();
    $this->actingAs($this->world->user('Manager'))->postJson(route('exchanges.quote'), [...$payload, 'sale_id' => 99_999_999])->assertUnprocessable()->assertJsonValidationErrors('sale_id');
});
