<?php

declare(strict_types=1);

use App\Actions\Tenancy\SeedTenantRoles;
use App\Models\Geo\GeoDistrict;
use App\Models\Geo\GeoUpazila;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Spatie\Permission\PermissionRegistrar;

/*
| /app/api/geo/*: readable by any active tenant user of any tenant (global data), never by guests,
| ETag / 304 caching, unknown levels 404.
*/

beforeEach(function (): void {
    $this->artisan('geo:import', ['--path' => __DIR__.'/../../fixtures/geo'])->assertSuccessful();
});

/** @return array{0: User, 1: Tenant} */
function geoActor(string $role = SystemRoles::CASHIER): array
{
    app(TenantContext::class)->forget();
    $tenant = Tenant::factory()->create();
    $branch = makeBranch($tenant);
    $user = User::factory()->for($tenant)->create();
    $user->branches()->attach($branch);
    app(SeedTenantRoles::class)->handle($tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $user->assignRole($role);

    return [$user, $tenant];
}

it('requires authentication', function (): void {
    $this->getJson('/app/api/geo/divisions')->assertUnauthorized();
    $this->getJson('/app/api/geo/search?q=Dhaka')->assertUnauthorized();
});

it('lists a level under a parent for a user of any tenant', function (): void {
    [$user] = geoActor();
    $this->actingAs($user);

    $divisions = $this->getJson('/app/api/geo/divisions')->assertOk()->assertHeader('ETag')->assertHeader('Cache-Control', 'max-age=2592000, private');
    expect($divisions->json('divisions'))->toHaveCount(3)->and($divisions->json('divisions.0'))->toHaveKeys(['id', 'name_en', 'name_bn']);

    $dhakaDivision = GeoDistrict::query()->where('name_en', 'Dhaka')->firstOrFail()->division_id;
    $districts = $this->getJson('/app/api/geo/districts?parent='.$dhakaDivision)->assertOk()->json('districts');
    expect(collect($districts)->pluck('name_en')->sort()->values()->all())->toBe(['Dhaka', 'Gazipur']);

    $dhaka = GeoDistrict::query()->where('name_en', 'Dhaka')->firstOrFail();
    $upazilas = $this->getJson('/app/api/geo/upazilas?parent='.$dhaka->id)->assertOk()->json('upazilas');
    expect(collect($upazilas)->pluck('name_en'))->toContain('Mirpur', 'Savar')
        ->and(collect($upazilas)->firstWhere('name_en', 'Mirpur')['type'])->toBe('metro_thana');

    $savar = GeoUpazila::query()->where('name_en', 'Savar')->firstOrFail();
    $unions = $this->getJson('/app/api/geo/unions?parent='.$savar->id)->assertOk()->json('unions');
    expect($unions)->not->toBeEmpty()->and(collect($unions)->pluck('upazila_id')->unique()->all())->toBe([$savar->id]);

    // Post codes of an upazila fall back to its district when the office list has none for it.
    $codes = $this->getJson('/app/api/geo/post_codes?parent='.$savar->id)->assertOk()->json('post_codes');
    expect($codes)->not->toBeEmpty()->and($codes[0]['post_code'])->toMatch('/^[0-9]{4}$/');

    [$otherTenantUser] = geoActor(SystemRoles::OWNER);
    $this->actingAs($otherTenantUser)->getJson('/app/api/geo/divisions')->assertOk()->assertJsonCount(3, 'divisions');
});

it('searches across levels with the parent chain and honours level and parent', function (): void {
    [$user] = geoActor();
    $this->actingAs($user);

    $hits = $this->getJson('/app/api/geo/search?q=Mirpur')->assertOk()->json('results');
    expect($hits[0])->toMatchArray(['level' => 'upazilas', 'name_en' => 'Mirpur'])
        ->and($hits[0]['chain']['district']['name_en'])->toBe('Dhaka')
        ->and($hits[0]['chain']['division']['name_en'])->toBe('Dhaka');

    $districts = $this->getJson('/app/api/geo/search?q=Chittagong&level=districts')->assertOk()->json('results');
    expect($districts)->toHaveCount(1)->and($districts[0]['name_en'])->toBe('Chattogram');

    expect($this->getJson('/app/api/geo/search?q=')->assertOk()->json('results'))->toBe([]);
    expect($this->getJson('/app/api/geo/search?q=Mirpur&level=bogus')->assertOk()->json('results.0.level'))->toBe('upazilas');
});

it('answers 304 to a matching If-None-Match and 404 for an unknown level', function (): void {
    [$user] = geoActor();
    $this->actingAs($user);

    $first = $this->getJson('/app/api/geo/districts')->assertOk();
    $etag = $first->headers->get('ETag');
    expect($etag)->toStartWith('"geo-');

    $this->getJson('/app/api/geo/districts', ['If-None-Match' => (string) $etag])->assertStatus(304);
    $this->getJson('/app/api/geo/districts?parent=1', ['If-None-Match' => (string) $etag])->assertOk();
    $this->getJson('/app/api/geo/villages')->assertNotFound();
});

it('refuses an inactive user', function (): void {
    [$user] = geoActor();
    $user->forceFill(['is_active' => false])->save();
    expect($this->actingAs($user)->getJson('/app/api/geo/divisions')->status())->toBeIn([401, 403]);
});
