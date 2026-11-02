<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Payment;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\SalesUi;

beforeEach(function (): void {
    $this->travelTo(now()->setTimezone('Asia/Dhaka')->setDate(2026, 11, 3)->setTime(12, 0)->utc());
    $this->world = SalesUi::world();
    $this->shirt = $this->world->product(100_000, 40_000);
    $this->world->stock($this->shirt, 50, 40_000, 60 * 24 * 200);
    $this->customer = $this->world->customer(['name' => 'Selim', 'shop_name' => 'Selim Traders']);
    // Dues of ৳600 (100 days old), ৳300 (45 days) and ৳1,000 (10 days).
    $this->old = SalesUi::age(SalesUi::sale($this->world, $this->shirt, $this->customer, 1, 40_000), 100);
    $this->mid = SalesUi::age(SalesUi::sale($this->world, $this->shirt, $this->customer, 1, 70_000), 45);
    $this->new = SalesUi::age(SalesUi::sale($this->world, $this->shirt, $this->customer, 1, 0), 10);
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('lists customers with open dues in aging buckets', function (): void {
    $paidUp = $this->world->customer(['name' => 'Nobody owes']);
    SalesUi::sale($this->world, $this->shirt, $paidUp);

    $this->actingAs($this->world->user('Cashier'))->get(route('dues.index'))
        ->assertInertia(fn (Assert $page) => $page->component('dues/index')
            ->has('rows', 1)
            ->where('rows.0.customer.shop_name', 'Selim Traders')
            ->where('rows.0.invoices', 3)
            ->where('rows.0.total_due', 190_000)
            ->where('rows.0.buckets', ['d0_30' => 100_000, 'd31_60' => 30_000, 'd61_90' => 0, 'd90_plus' => 60_000])
            ->where('totals.total_due', 190_000)
            ->where('totals.customers', 1));

    $this->get(route('dues.index', ['filter' => ['bucket' => 'd61_90']]))->assertInertia(fn (Assert $page) => $page->has('rows', 0));
    $this->get(route('dues.index', ['filter' => ['bucket' => 'd90_plus']]))->assertInertia(fn (Assert $page) => $page->has('rows', 1));
});

it('needs customers.view; another tenant\'s customer is a 404', function (): void {
    $this->actingAs($this->world->user('Stock Keeper'))->get(route('dues.index'))->assertForbidden();
    $foreign = SalesUi::foreignSale();
    $uuid = app(TenantContext::class)->withoutTenancy(fn () => Customer::query()->findOrFail($foreign->customer_id)->uuid);

    $this->actingAs($this->world->user('Cashier'))->get(route('dues.show', $uuid))->assertNotFound();
});

it('previews an oldest-first allocation', function (): void {
    $this->actingAs($this->world->user('Cashier'))->get(route('dues.show', ['customer' => $this->customer->uuid, 'amount' => 70_000]))
        ->assertInertia(fn (Assert $page) => $page->component('dues/show')
            ->has('invoices', 3)
            ->where('invoices.0.id', $this->old->id)
            ->where('invoices.0.bucket', 'd90_plus')
            ->where('invoices.0.age', 100)
            ->where('preview.allocations', [$this->old->id => 60_000, $this->mid->id => 10_000])
            ->where('preview.advance', 0));

    $this->get(route('dues.show', ['customer' => $this->customer->uuid, 'amount' => 250_000]))
        ->assertInertia(fn (Assert $page) => $page->where('preview.advance', 60_000));
});

it('collects oldest first and prints a money receipt', function (): void {
    $this->actingAs($this->world->user('Cashier'))->post(route('dues.payments.store', $this->customer->uuid), [
        'amount' => 70_000, 'payment_method_id' => $this->world->methods['cash']->id,
    ])->assertRedirect();

    $payment = Payment::query()->latest('id')->firstOrFail();
    expect($this->old->refresh()->due->paisa)->toBe(0)
        ->and($this->mid->refresh()->due->paisa)->toBe(20_000)
        ->and($this->new->refresh()->due->paisa)->toBe(100_000);

    $this->get(route('payments.receipt', $payment->id))->assertOk()->assertSee($payment->number)->assertSee('৳700.00')->assertSee((string) $this->old->number);
});

it('collects against chosen invoices', function (): void {
    $this->actingAs($this->world->user('Cashier'))->post(route('dues.payments.store', $this->customer->uuid), [
        'amount' => 50_000,
        'payment_method_id' => $this->world->methods['cash']->id,
        'allocations' => [['sale_id' => $this->new->id, 'amount' => 50_000]],
    ])->assertRedirect();

    expect($this->new->refresh()->due->paisa)->toBe(50_000)->and($this->old->refresh()->due->paisa)->toBe(60_000);
});

it('refuses an allocation above the invoice due and users without payments.collect', function (): void {
    $this->actingAs($this->world->user('Cashier'))->postJson(route('dues.payments.store', $this->customer->uuid), [
        'amount' => 50_000,
        'payment_method_id' => $this->world->methods['cash']->id,
        'allocations' => [['sale_id' => $this->mid->id, 'amount' => 40_000]],
    ])->assertUnprocessable()->assertJsonValidationErrors('amount');

    $this->actingAs(SalesUi::nobody($this->world))->post(route('dues.payments.store', $this->customer->uuid), [
        'amount' => 1_000, 'payment_method_id' => $this->world->methods['cash']->id,
    ])->assertForbidden();
});
