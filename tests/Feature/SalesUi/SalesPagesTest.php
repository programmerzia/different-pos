<?php

declare(strict_types=1);

use App\Enums\SaleStatus;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\SalesUi;

beforeEach(function (): void {
    // 15:00 in Dhaka (09:00 UTC), so "tomorrow" is a clean business-day change.
    $this->travelTo(now()->setTimezone('Asia/Dhaka')->setDate(2026, 11, 3)->setTime(15, 0)->utc());
    $this->world = SalesUi::world();
    $this->shirt = $this->world->product(100_000, 40_000, ['name' => 'Panjabi']);
    $this->world->stock($this->shirt, 20, 40_000);
    $this->customer = $this->world->customer(['name' => 'Rahim', 'shop_name' => 'Rahim Store']);
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('needs sales.view for the list', function (): void {
    $this->get(route('sales.index'))->assertRedirect(route('login'));
    $this->actingAs($this->world->user('Stock Keeper'))->get(route('sales.index'))->assertForbidden();
    $this->actingAs($this->world->user('Cashier'))->get(route('sales.index'))->assertOk();
});

it('lists the branch sales with customer and shop name, never other branches or tenants', function (): void {
    $mine = SalesUi::sale($this->world, $this->shirt, $this->customer, 2, 150_000);
    $branch = SalesUi::otherBranch($this->world);
    SalesUi::inBranch($this->world, $branch, function () use ($branch): void {
        Sale::factory()->create(['tenant_id' => $this->world->tenant->id, 'branch_id' => $branch->id, 'customer_id' => $this->customer->id, 'number' => 'DF2-OTHER']);
    });
    SalesUi::foreignSale();

    $this->actingAs($this->world->user('Cashier'))->get(route('sales.index'))
        ->assertInertia(fn (Assert $page) => $page->component('sales/index')
            ->has('rows', 1)
            ->where('rows.0.id', $mine->id)
            ->where('rows.0.customer.shop_name', 'Rahim Store')
            ->where('rows.0.items', 1)
            ->where('rows.0.total', 200_000)
            ->where('rows.0.paid', 150_000)
            ->where('rows.0.due', 50_000)
            ->where('rows.0.payment_status', 'partial')
            ->where('meta.total', 1));
});

it('filters by status, payment status, customer and number', function (): void {
    $paid = SalesUi::sale($this->world, $this->shirt);
    $partial = SalesUi::sale($this->world, $this->shirt, $this->customer, 1, 30_000);
    $user = $this->world->user();

    $ids = fn (string $query): array => collect($this->actingAs($user)->get('/app/sales?'.$query)->viewData('page')['props']['rows'])->pluck('id')->all();

    expect($ids('filter[payment_status]=partial'))->toBe([$partial->id])
        ->and($ids('filter[customer]='.$this->customer->id))->toBe([$partial->id])
        ->and($ids('q='.urlencode((string) $paid->number)))->toBe([$paid->id])
        ->and($ids('q=Rahim'))->toBe([$partial->id])
        ->and($ids('filter[status]=voided'))->toBe([]);
});

it('shows a sale; another branch or tenant gets a 404', function (): void {
    $sale = SalesUi::sale($this->world, $this->shirt, $this->customer);
    $branch = SalesUi::otherBranch($this->world);
    $other = SalesUi::inBranch($this->world, $branch, fn () => Sale::factory()->create(['tenant_id' => $this->world->tenant->id, 'branch_id' => $branch->id, 'customer_id' => $this->customer->id, 'number' => 'DF2-1']));
    $foreign = SalesUi::foreignSale();
    $user = $this->world->user('Cashier');

    $this->actingAs($user)->get(route('sales.show', $sale->id))
        ->assertInertia(fn (Assert $page) => $page->component('sales/show')
            ->where('sale.number', $sale->number)
            ->where('customer.name', 'Rahim')
            ->has('lines', 1)
            ->has('payments', 1)
            ->where('can.void', false));
    $this->actingAs($user)->get(route('sales.show', $other->id))->assertNotFound();
    $this->actingAs($user)->get(route('sales.show', $foreign->id))->assertNotFound();
    $this->actingAs($user)->get(route('sales.receipt', $foreign->id))->assertNotFound();
});

it('hides batches and cost without products.view_cost', function (): void {
    $sale = SalesUi::sale($this->world, $this->shirt);

    $this->actingAs($this->world->user('Cashier'))->get(route('sales.show', $sale->id))
        ->assertInertia(fn (Assert $page) => $page->where('lines.0.cogs', null)->where('lines.0.batches', null)->where('sale.cogs_total', null));
    $this->actingAs($this->world->user('Owner'))->get(route('sales.show', $sale->id))
        ->assertInertia(fn (Assert $page) => $page->where('lines.0.cogs', 40_000)->has('lines.0.batches', 1)->where('lines.0.batches.0.unit_cost', 40_000));
});

it('voids a same-day sale with sales.void only', function (): void {
    $sale = SalesUi::sale($this->world, $this->shirt);

    $this->actingAs($this->world->user('Cashier'))->post(route('sales.void', $sale->id), ['reason' => 'wrong size'])->assertForbidden();
    $this->actingAs($this->world->user('Manager'))->post(route('sales.void', $sale->id), ['reason' => ''])->assertSessionHasErrors('reason');

    $this->actingAs($this->world->user('Manager'))->post(route('sales.void', $sale->id), ['reason' => 'wrong size'])
        ->assertRedirect(route('sales.show', $sale->id));
    expect($sale->refresh()->status)->toBe(SaleStatus::Voided)->and($sale->void_reason)->toBe('wrong size');
});

it('refuses a void after midnight with the domain message', function (): void {
    $sale = SalesUi::sale($this->world, $this->shirt);
    $this->travelTo(now()->addDay());

    $this->actingAs($this->world->user('Manager'))->postJson(route('sales.void', $sale->id), ['reason' => 'late'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.reason.0', sprintf('Sale %s is not from today and can no longer be voided. Use a return.', $sale->number));
    expect($sale->refresh()->status)->toBe(SaleStatus::Completed);
});

it('collects the due of one invoice; the rest stays as an advance', function (): void {
    $sale = SalesUi::sale($this->world, $this->shirt, $this->customer, 1, 40_000);

    $this->actingAs($this->world->user('Stock Keeper'))->post(route('sales.payments.store', $sale->id), [
        'amount' => 10_000, 'payment_method_id' => $this->world->methods['cash']->id,
    ])->assertForbidden();

    $this->actingAs($this->world->user('Cashier'))->post(route('sales.payments.store', $sale->id), [
        'amount' => 70_000, 'payment_method_id' => $this->world->methods['cash']->id,
    ])->assertRedirect();

    $payment = Payment::query()->latest('id')->firstOrFail();
    expect($sale->refresh()->due->paisa)->toBe(0)
        ->and($payment->amount->paisa)->toBe(70_000)
        ->and($payment->allocations()->sole()->amount->paisa)->toBe(60_000)
        ->and($this->customer->refresh()->balance->paisa)->toBe(-10_000);
});

it('refuses another tenant\'s payment method', function (): void {
    $sale = SalesUi::sale($this->world, $this->shirt, $this->customer, 1, 40_000);
    $foreign = SalesUi::foreignSale();
    $method = app(TenantContext::class)->withoutTenancy(fn () => PaymentMethod::factory()->create(['tenant_id' => $foreign->tenant_id]));

    $this->actingAs($this->world->user('Cashier'))->postJson(route('sales.payments.store', $sale->id), [
        'amount' => 10_000, 'payment_method_id' => $method->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('payment_method_id');
});

it('creates a share link for an imported sale without a token', function (): void {
    $sale = SalesUi::sale($this->world, $this->shirt);
    Sale::query()->whereKey($sale->id)->update(['public_token' => null]);

    $url = $this->actingAs($this->world->user('Cashier'))->postJson(route('sales.share', $sale->id))->assertOk()->json('url');

    expect($sale->refresh()->public_token)->toHaveLength(32)
        ->and($url)->toBe(route('receipts.public', $sale->public_token));
    $this->postJson(route('sales.share', $sale->id))->assertOk()->assertJsonPath('url', $url);
});
