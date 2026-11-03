<?php

declare(strict_types=1);

use App\Actions\Customers\SaveCustomer;
use App\Exceptions\Domain\InvalidPartyData;
use App\Models\Customer;
use App\Models\Geo\GeoDistrict;
use App\Models\Geo\GeoUnion;
use App\Models\Geo\GeoUpazila;
use App\Support\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\CustomerWorld;

/*
| Customer location (LocationFields): validation and parent auto-fill on save, the list's
| district / upazila column and filter, the profile's location names.
*/

beforeEach(function (): void {
    $this->artisan('geo:import', ['--path' => __DIR__.'/../../fixtures/geo'])->assertSuccessful();
    $this->kalai = GeoUpazila::query()->where('name_en', 'Kalai')->firstOrFail();
    $this->joypurhat = $this->kalai->district;
    $this->dhaka = GeoDistrict::query()->where('name_en', 'Dhaka')->firstOrFail();
    $this->mirpur = GeoUpazila::query()->where('name_en', 'Mirpur')->where('district_id', $this->dhaka->id)->firstOrFail();
});

it('saves a location from the upazila alone, filling division and district, and marks it verified', function (): void {
    [$user, $tenant] = CustomerWorld::actor();

    $this->actingAs($user)->post('/app/customers', ['name' => 'Located', 'phone' => '01712345678', 'geo_upazila_id' => $this->kalai->id, 'post_code' => '5930'])->assertRedirect();

    $customer = app(TenantContext::class)->withoutTenancy(fn () => Customer::query()->where('tenant_id', $tenant->id)->where('name', 'Located')->firstOrFail());
    expect($customer->geo_upazila_id)->toBe($this->kalai->id)
        ->and($customer->geo_district_id)->toBe($this->joypurhat->id)
        ->and($customer->geo_division_id)->toBe($this->joypurhat->division_id)
        ->and($customer->geo_union_id)->toBeNull()
        ->and($customer->location_verified)->toBeTrue();
});

it('rejects unknown ids and a district that does not belong to the division or upazila', function (): void {
    [$user] = CustomerWorld::actor();
    $this->actingAs($user);

    $this->postJson('/app/customers', ['name' => 'X', 'phone' => '01712345678', 'geo_district_id' => 65000])->assertStatus(422)->assertJsonValidationErrors(['geo_district_id']);
    $this->postJson('/app/customers', ['name' => 'X', 'phone' => '01712345678', 'geo_district_id' => $this->dhaka->id, 'geo_upazila_id' => $this->kalai->id])
        ->assertStatus(422)->assertJsonValidationErrors(['geo_upazila_id']);
    $this->postJson('/app/customers', ['name' => 'X', 'phone' => '01712345678', 'geo_division_id' => $this->dhaka->division_id, 'geo_district_id' => $this->joypurhat->id])
        ->assertStatus(422)->assertJsonValidationErrors(['geo_upazila_id']);
    expect(Customer::query()->count())->toBe(0);
});

it('the action itself refuses a broken chain and accepts a union', function (): void {
    [$user, $tenant] = CustomerWorld::actor();
    $union = GeoUnion::query()->firstOrFail();
    $upazila = $union->upazila;
    $save = app(SaveCustomer::class);

    expect(fn () => app(TenantContext::class)->run($tenant, null, fn () => $save->handle($user, ['name' => 'A', 'phone' => '01712345678', 'geo_district_id' => $this->joypurhat->id, 'geo_union_id' => $union->id])))
        ->toThrow(InvalidPartyData::class);

    $customer = app(TenantContext::class)->run($tenant, null, fn () => $save->handle($user, ['name' => 'B', 'phone' => '01712345679', 'geo_union_id' => $union->id]));
    expect($customer->geo_union_id)->toBe($union->id)->and($customer->geo_upazila_id)->toBe($upazila->id)->and($customer->geo_district_id)->toBe($upazila->district_id);

    // A location is replaced as a whole: changing the district alone drops the old upazila and union and re-derives the division.
    $moved = app(TenantContext::class)->run($tenant, null, fn () => $save->handle($user, ['geo_district_id' => $this->joypurhat->id], $customer));
    expect($moved->geo_district_id)->toBe($this->joypurhat->id)->and($moved->geo_upazila_id)->toBeNull()->and($moved->geo_union_id)->toBeNull()->and($moved->geo_division_id)->toBe($this->joypurhat->division_id);
});

it('shows district and upazila names in the list and profile and filters by district', function (): void {
    [$user, $tenant] = CustomerWorld::actor();
    $a = CustomerWorld::make($tenant, ['name' => 'Kalai Buyer', 'geo_division_id' => $this->joypurhat->division_id, 'geo_district_id' => $this->joypurhat->id, 'geo_upazila_id' => $this->kalai->id]);
    CustomerWorld::make($tenant, ['name' => 'Dhaka Buyer', 'geo_division_id' => $this->dhaka->division_id, 'geo_district_id' => $this->dhaka->id, 'geo_upazila_id' => $this->mirpur->id]);
    CustomerWorld::make($tenant, ['name' => 'Nowhere']);

    $this->actingAs($user);
    $this->get('/app/customers')->assertOk()->assertInertia(fn (Assert $p) => $p->component('customers/index')
        ->has('customers', 3)->has('districts', 5)
        ->where('customers.1.location.district.name_en', 'Joypurhat')->where('customers.1.location.upazila.name_bn', 'কালাই')
        ->where('customers.2.location.district', null));
    $this->get('/app/customers?filter[district]='.$this->dhaka->id)->assertInertia(fn (Assert $p) => $p->has('customers', 1)->where('customers.0.name', 'Dhaka Buyer'));
    $this->get('/app/customers?filter[upazila]='.$this->kalai->id)->assertInertia(fn (Assert $p) => $p->has('customers', 1)->where('customers.0.name', 'Kalai Buyer'));

    $this->get('/app/customers/'.$a->uuid)->assertInertia(fn (Assert $p) => $p->component('customers/show')
        ->where('customer.geo_upazila_id', $this->kalai->id)->where('customer.location.division.name_en', 'Rajshahi')
        ->where('customer.location.upazila.name_en', 'Kalai'));
});
