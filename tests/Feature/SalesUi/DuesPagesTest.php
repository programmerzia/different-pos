<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Customer;
use App\Models\CustomerGroup;
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

it('adds the last purchase, debtors per bucket, group and credit-use filters', function (): void {
    $group = CustomerGroup::factory()->create(['tenant_id' => $this->world->tenant->id, 'name' => 'Wholesale']);
    $balance = $this->customer->refresh()->balance->paisa;
    Customer::query()->whereKey($this->customer->id)->update(['credit_limit' => intdiv($balance * 100, 90)]);
    $user = $this->world->user('Cashier');

    $this->actingAs($user)->get(route('dues.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.last_sale_at', $this->new->refresh()->sold_at->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))
            ->where('totals.debtors', ['d0_30' => 1, 'd31_60' => 1, 'd61_90' => 0, 'd90_plus' => 1])
            ->where('groups', [['id' => $group->id, 'name' => 'Wholesale']])
            ->missing('collect'));

    $count = fn (array $filter): int => count($this->actingAs($user)->get(route('dues.index', ['filter' => $filter]))->viewData('page')['props']['rows']);
    expect($count(['group' => (string) $group->id]))->toBe(0)
        ->and($count(['credit' => 'near_limit']))->toBe(1)
        ->and($count(['credit' => 'over_limit']))->toBe(0);

    Customer::query()->whereKey($this->customer->id)->update(['customer_group_id' => $group->id, 'credit_limit' => 1]);
    expect($count(['group' => (string) $group->id]))->toBe(1)
        ->and($count(['credit' => 'over_limit']))->toBe(1);
});

it('uses the tenant default credit limit when the customer has none, in the filters and the payloads', function (): void {
    $balance = $this->customer->refresh()->balance->paisa; // ৳1,900
    Customer::query()->whereKey($this->customer->id)->update(['credit_limit' => null]);
    $tenant = $this->world->tenant;
    $user = $this->world->user('Cashier');
    $count = fn (array $filter): int => count($this->actingAs($user)->get(route('dues.index', ['filter' => $filter]))->viewData('page')['props']['rows']);

    // No own limit and no tenant default: never near or over a limit.
    expect($count(['credit' => 'near_limit']))->toBe(0)->and($count(['credit' => 'over_limit']))->toBe(0);

    $tenant->forceFill(['settings' => [...$tenant->settings, 'default_credit_limit' => $balance - 100]])->save();
    $user->unsetRelation('tenant'); // the next requests resolve the saved tenant
    expect($count(['credit' => 'over_limit']))->toBe(1)->and($count(['credit' => 'near_limit']))->toBe(1);
    $this->actingAs($user)->get(route('dues.index'))->assertInertia(fn (Assert $page) => $page->where('rows.0.customer.credit_limit', $balance - 100));
    $this->actingAs($user)->get(route('dues.show', $this->customer->uuid))->assertInertia(fn (Assert $page) => $page->where('customer.credit_limit', $balance - 100));

    // The customer's own limit wins over the default.
    Customer::query()->whereKey($this->customer->id)->update(['credit_limit' => $balance * 10]);
    expect($count(['credit' => 'over_limit']))->toBe(0)->and($count(['credit' => 'near_limit']))->toBe(0);
    $this->actingAs($user)->get(route('dues.index'))->assertInertia(fn (Assert $page) => $page->where('rows.0.customer.credit_limit', $balance * 10));
});

it('loads the collect dialog for one customer alone, never another tenant\'s', function (): void {
    $foreign = SalesUi::foreignSale();
    $foreignUuid = app(TenantContext::class)->withoutTenancy(fn () => Customer::query()->findOrFail($foreign->customer_id)->uuid);
    $partial = fn (string $uuid) => $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'dues/index',
        'X-Inertia-Partial-Data' => 'collect',
    ])->get(route('dues.index', ['collect' => $uuid]))->assertOk()->json('props');

    $this->actingAs($this->world->user('Cashier'));
    $mine = $partial($this->customer->uuid);
    expect($mine)->not->toHaveKey('rows')
        ->and(array_column($mine['collect']['invoices'], 'id'))->toBe([$this->old->id, $this->mid->id, $this->new->id])
        ->and($mine['collect']['customer']['name'])->toBe('Selim')
        ->and($mine['collect']['can']['collect'])->toBeTrue()
        ->and($partial($foreignUuid)['collect'])->toBeNull();
});

it('filters sales and dues by the Bangladesh week (Saturday start), not a rolling 7 days', function (): void {
    // Today is Tuesday 3 Nov 2026: this week began on Saturday 31 Oct. 3 days ago = Saturday (in), 4 days ago = Friday (out).
    $sat = SalesUi::age(SalesUi::sale($this->world, $this->shirt, $this->customer, 1, 0), 3);
    $fri = SalesUi::age(SalesUi::sale($this->world, $this->shirt, $this->customer, 1, 0), 4);
    $user = $this->world->user('Cashier');

    $this->actingAs($user)->get(route('dues.index', ['filter' => ['period' => 'week']]))
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.invoices', 1));

    $ids = $this->get(route('sales.index', ['filter' => ['period' => 'week']]))->viewData('page')['props']['rows'];
    expect(collect($ids)->pluck('id')->all())->toBe([$sat->id])->not->toContain($fri->id);
});
