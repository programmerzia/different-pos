<?php

declare(strict_types=1);

use App\Actions\Cards\IssueCard;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Support\Money\Money;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\CustomerWorld;

it('keeps guests out and forbids users without customers.view', function (): void {
    $this->get('/app/customers')->assertRedirect(route('login'));

    [$keeper] = CustomerWorld::actor(SystemRoles::STOCK_KEEPER);
    $this->actingAs($keeper)->get('/app/customers')->assertForbidden();
    $this->get('/app/customers/statement')->assertForbidden();
});

it('lists customers with shop name, balance and search across name, shop, phone and number', function (): void {
    [$user, $tenant] = CustomerWorld::actor();
    $rahim = CustomerWorld::make($tenant, ['name' => 'Rahim', 'shop_name' => 'Rahim Fashion House', 'phone' => '+8801711111111', 'customer_no' => 10000017, 'balance' => Money::ofPaisa(250000)]);
    CustomerWorld::make($tenant, ['name' => 'Karim', 'shop_name' => 'Karim Traders', 'phone' => '+8801822222222', 'customer_no' => 10000025]);
    CustomerWorld::make($tenant, ['name' => 'Walk-in', 'is_walk_in' => true]);

    $this->actingAs($user);
    $all = $this->get('/app/customers')->assertOk()->assertInertia(fn (Assert $p) => $p->component('customers/index')->has('customers', 2)->has('meta'));
    expect($all->viewData('page')['props']['can']['create'])->toBeTrue();

    foreach (['Fashion', '1711111', '1000 0017', 'rahim'] as $term) {
        $this->get('/app/customers?q='.urlencode($term))->assertInertia(
            fn (Assert $p) => $p->has('customers', 1)->where('customers.0.uuid', $rahim->uuid)->where('customers.0.shop_name', 'Rahim Fashion House')
                ->where('customers.0.balance', 250000)->where('customers.0.customer_no', '1000 0017'),
        );
    }
});

it('filters by group, card and outstanding balance and never shows another tenant', function (): void {
    [$user, $tenant] = CustomerWorld::actor();
    $group = app(TenantContext::class)->withoutTenancy(fn () => CustomerGroup::factory()->for($tenant)->create(['name' => 'Wholesale']));
    $carded = CustomerWorld::make($tenant, ['name' => 'Carded', 'customer_no' => 10000017, 'customer_group_id' => $group->id, 'balance' => Money::ofPaisa(500)]);
    CustomerWorld::make($tenant, ['name' => 'Plain', 'customer_no' => 10000025]);
    [, $other] = CustomerWorld::actor();
    CustomerWorld::make($other, ['name' => 'Foreign', 'customer_no' => 10000033]);
    app(TenantContext::class)->set($tenant);
    app(IssueCard::class)->handle($user, $carded);

    $this->actingAs($user);
    $this->get('/app/customers?filter[card]=yes')->assertInertia(fn (Assert $p) => $p->has('customers', 1)->where('customers.0.name', 'Carded')->where('customers.0.card_status', 'active'));
    $this->get('/app/customers?filter[card]=no')->assertInertia(fn (Assert $p) => $p->has('customers', 1)->where('customers.0.name', 'Plain'));
    $this->get('/app/customers?filter[group]='.$group->id)->assertInertia(fn (Assert $p) => $p->has('customers', 1)->where('customers.0.name', 'Carded'));
    $this->get('/app/customers?filter[due]=1')->assertInertia(fn (Assert $p) => $p->has('customers', 1)->where('customers.0.name', 'Carded'));
    $this->get('/app/customers?q=Foreign')->assertInertia(fn (Assert $p) => $p->has('customers', 0));
});

it('creates a customer through the action and redirects to the profile', function (): void {
    [$user, $tenant] = CustomerWorld::actor();

    $response = $this->actingAs($user)->post('/app/customers', [
        'name' => 'New Buyer', 'phone' => '01712345678', 'shop_name' => 'Buyer Shop', 'credit_limit' => 500000, 'post_code' => '1216',
    ]);

    $customer = app(TenantContext::class)->withoutTenancy(fn () => Customer::query()->where('tenant_id', $tenant->id)->where('phone', '+8801712345678')->firstOrFail());
    $response->assertRedirect(route('customers.show', $customer->uuid));
    expect($customer->credit_limit?->paisa)->toBe(500000)->and($customer->customer_no)->not->toBeNull();
});

it('answers a duplicate phone with 422 carrying the existing customer', function (): void {
    [$user, $tenant] = CustomerWorld::actor();
    $existing = CustomerWorld::make($tenant, ['name' => 'Already Here', 'phone' => '+8801712345678', 'customer_no' => 10000017]);

    $json = $this->actingAs($user)->postJson('/app/customers', ['name' => 'Copy', 'phone' => '01712345678'])->assertStatus(422);
    $json->assertJsonPath('existing.uuid', $existing->uuid)->assertJsonPath('existing.name', 'Already Here')->assertJsonPath('existing.customer_no', '1000 0017')
        ->assertJsonValidationErrors('phone');

    // A normal form post goes back with the error on the phone field.
    $this->from('/app/customers/create')->post('/app/customers', ['name' => 'Copy', 'phone' => '01712345678'])
        ->assertRedirect('/app/customers/create')->assertSessionHasErrors('phone');
    expect(Customer::query()->count())->toBe(1);
});

it('checks a phone live, ignoring the customer being edited', function (): void {
    [$user, $tenant] = CustomerWorld::actor();
    $existing = CustomerWorld::make($tenant, ['name' => 'Holder', 'phone' => '+8801712345678']);

    $this->actingAs($user);
    $this->getJson('/app/customers/check-phone?phone=01712345678')->assertOk()->assertJsonPath('existing.uuid', $existing->uuid);
    $this->getJson('/app/customers/check-phone?phone=01712345678&except='.$existing->uuid)->assertOk()->assertJsonPath('existing', null);
    $this->getJson('/app/customers/check-phone?phone=01999999999')->assertOk()->assertJsonPath('existing', null);
});

it('validates input and tenant-scopes the group', function (): void {
    [$user] = CustomerWorld::actor();
    [, $other] = CustomerWorld::actor();
    $foreign = app(TenantContext::class)->withoutTenancy(fn () => CustomerGroup::factory()->for($other)->create());

    $this->actingAs($user)->postJson('/app/customers', ['name' => '', 'phone' => '', 'customer_group_id' => $foreign->id, 'credit_limit' => -5])
        ->assertStatus(422)->assertJsonValidationErrors(['name', 'phone', 'customer_group_id', 'credit_limit']);
    $this->postJson('/app/customers', ['name' => 'X', 'phone' => '12345'])->assertStatus(422)->assertJsonPath('errors.form.0', 'Enter a valid Bangladeshi mobile number.');
});

it('authorizes create, update and deactivate per permission', function (): void {
    [$cashier, $tenant] = CustomerWorld::actor(SystemRoles::CASHIER);
    $customer = CustomerWorld::make($tenant, ['customer_no' => 10000017]);

    $this->actingAs($cashier);
    $this->post('/app/customers', ['name' => 'Ok', 'phone' => '01712345678'])->assertRedirect();
    $this->put("/app/customers/{$customer->uuid}", ['name' => 'Changed', 'phone' => '01812345678'])->assertForbidden();
    $this->get("/app/customers/{$customer->uuid}/edit")->assertForbidden();
    $this->post("/app/customers/{$customer->uuid}/deactivate")->assertForbidden();
    $this->post("/app/customers/{$customer->uuid}/merge", ['into' => $customer->uuid])->assertForbidden();
    $this->get('/app/customers/create')->assertOk();
});

it('updates, deactivates and merges', function (): void {
    [$user, $tenant] = CustomerWorld::actor();
    $a = CustomerWorld::make($tenant, ['name' => 'Survivor', 'customer_no' => 10000017, 'phone' => '+8801711111111']);
    $b = CustomerWorld::make($tenant, ['name' => 'Duplicate', 'customer_no' => 10000025, 'phone' => '+8801822222222', 'balance' => Money::ofPaisa(1000)]);
    $c = CustomerWorld::make($tenant, ['name' => 'Leaver', 'customer_no' => 10000033, 'phone' => '+8801933333333']);

    $this->actingAs($user);
    $this->put("/app/customers/{$a->uuid}", ['name' => 'Survivor Two', 'phone' => '01711111111'])->assertRedirect(route('customers.show', $a->uuid));
    expect($a->refresh()->name)->toBe('Survivor Two');

    $this->post("/app/customers/{$c->uuid}/deactivate")->assertRedirect(route('customers.index'));
    expect($c->refresh()->is_active)->toBeFalse();

    $this->post("/app/customers/{$b->uuid}/merge", ['into' => $a->uuid])->assertRedirect(route('customers.show', $a->uuid));
    expect($a->refresh()->balance->paisa)->toBe(1000)->and(Customer::query()->whereKey($b->id)->exists())->toBeFalse();

    $this->post("/app/customers/{$a->uuid}/merge", ['into' => $a->uuid])->assertSessionHasErrors('form');
});

it('returns 404 for another tenant\'s customer on every route', function (): void {
    [$user] = CustomerWorld::actor();
    [, $other] = CustomerWorld::actor();
    $foreign = CustomerWorld::make($other, ['customer_no' => 10000017]);
    $mine = CustomerWorld::make($user->tenant()->firstOrFail(), ['customer_no' => 10000025]);

    $this->actingAs($user);
    $this->get("/app/customers/{$foreign->uuid}")->assertNotFound();
    $this->get("/app/customers/{$foreign->uuid}/edit")->assertNotFound();
    $this->put("/app/customers/{$foreign->uuid}", ['name' => 'X', 'phone' => '01712345678'])->assertNotFound();
    $this->post("/app/customers/{$foreign->uuid}/deactivate")->assertNotFound();
    $this->post("/app/customers/{$foreign->uuid}/merge", ['into' => $mine->uuid])->assertNotFound();
    $this->get("/app/customers/{$foreign->uuid}/statement")->assertNotFound();
    $this->post("/app/customers/{$foreign->uuid}/statement/sms")->assertNotFound();
    // Merging into a foreign survivor fails validation instead of leaking it.
    $this->postJson("/app/customers/{$mine->uuid}/merge", ['into' => $foreign->uuid])->assertStatus(422)->assertJsonValidationErrors('into');
});

it('shows the profile with card, history and no PIN data', function (): void {
    [$user, $tenant, $branch] = CustomerWorld::actor();
    $customer = CustomerWorld::make($tenant, ['name' => 'Profile', 'customer_no' => 10000017, 'pin_hash' => 'secret-hash', 'balance' => Money::ofPaisa(7000)]);
    CustomerWorld::sale($tenant, $branch, $customer, ['number' => 'S-1', 'total' => Money::ofPaisa(7000), 'due' => Money::ofPaisa(7000)]);
    app(TenantContext::class)->set($tenant, $branch);
    app(IssueCard::class)->handle($user, $customer);

    $response = $this->actingAs($user)->get("/app/customers/{$customer->uuid}")->assertOk()->assertInertia(
        fn (Assert $p) => $p->component('customers/show')->where('customer.customer_no', '1000 0017')->where('customer.balance', 7000)
            ->where('card.card_code', 'MC1000001701')->where('card.status', 'active')->has('sales', 1)->where('sales.0.number', 'S-1')
            ->where('can.sell', true)->where('can.issue_card', true),
    );
    expect(json_encode($response->viewData('page')['props']))->not->toContain('secret-hash')->not->toContain('pin_hash');
});

it('searches customers for the picker by number, phone and shop name within the tenant', function (): void {
    [$user, $tenant] = CustomerWorld::actor();
    CustomerWorld::make($tenant, ['name' => 'Rahim', 'shop_name' => 'Moon Store', 'phone' => '+8801711111111', 'customer_no' => 10000017]);
    [, $other] = CustomerWorld::actor();
    CustomerWorld::make($other, ['name' => 'Rahim Foreign', 'customer_no' => 10000025]);

    $this->actingAs($user);
    foreach (['Moon', '01711111111', '10000017', '#1000 0017'] as $term) {
        $this->getJson('/app/customers/search?q='.urlencode($term))->assertOk()->assertJsonCount(1, 'customers')->assertJsonPath('customers.0.name', 'Rahim');
    }
    expect(json_encode($this->getJson('/app/customers/search?q=Rahim')->json()))->not->toContain('Foreign')->not->toContain('pin');
});
