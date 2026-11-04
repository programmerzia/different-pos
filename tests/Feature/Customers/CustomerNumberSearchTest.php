<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Support\Customers\CustomerSearch;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\SalesUi;

beforeEach(function (): void {
    $this->world = SalesUi::world();
    $this->shirt = $this->world->product(100_000, 40_000);
    $this->world->stock($this->shirt, 20, 40_000);
    $this->rahim = $this->world->customer(['name' => 'Rahim', 'phone' => '+8801711000111']);
    $this->karim = $this->world->customer(['name' => 'Karim', 'phone' => '+8801711000222']);
    Customer::query()->whereKey($this->rahim->id)->update(['customer_no' => 10009231]);
    Customer::query()->whereKey($this->karim->id)->update(['customer_no' => 10004567]);
    $this->sale = SalesUi::sale($this->world, $this->shirt, Customer::query()->findOrFail($this->rahim->id), 1);
    $this->names = fn (string $term): array => app(CustomerSearch::class)->search($term)->pluck('name')->all();
});

afterEach(function (): void {
    app(TenantContext::class)->forget();
});

it('finds a customer by the last digits of the number', function (): void {
    expect(($this->names)('9231'))->toBe(['Rahim'])
        ->and(($this->names)('231'))->toBe(['Rahim'])
        ->and(($this->names)('4567'))->toBe(['Karim']);
});

it('still finds by the start and by the whole number, with or without spaces', function (): void {
    expect(($this->names)('10009'))->toBe(['Rahim'])
        ->and(($this->names)('10009231'))->toBe(['Rahim'])
        ->and(($this->names)('1000 9231'))->toBe(['Rahim']);
});

it('does not match on one or two digits, or digits in the middle', function (): void {
    expect(($this->names)('31'))->toBe([])
        ->and(($this->names)('092'))->toBe([]);
});

it('never finds another tenant\'s customer by last digits', function (): void {
    $other = SalesUi::world();
    $foreign = $other->customer(['name' => 'Foreign', 'phone' => '+8801711000333']);
    Customer::query()->whereKey($foreign->id)->update(['customer_no' => 20007777]);
    app(TenantContext::class)->set($this->world->tenant, $this->world->branch);

    expect(($this->names)('7777'))->toBe([]);
});

it('finds by last digits on the customer list, cards, sales, dues and returns screens', function (): void {
    $this->actingAs($this->world->user('Owner'));

    $this->get('/app/customers?q=9231')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->has('customers', 1)->where('customers.0.name', 'Rahim'));
    $this->get('/app/membership-cards?q=9231')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->has('members', 1)->where('members.0.name', 'Rahim'));
    $this->get('/app/sales?q=9231')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->has('rows', 1));
    $this->get('/app/sales?q=4567')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->has('rows', 0));
    $this->get('/app/returns/create?q=9231')->assertOk()
        ->assertInertia(fn (Assert $p) => $p->has('candidates', 1));
    $this->get('/app/dues?q=9231')->assertOk();
});
