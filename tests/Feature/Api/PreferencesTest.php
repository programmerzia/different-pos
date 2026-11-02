<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

function prefUser(?Tenant $tenant = null): User
{
    $tenant ??= Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create(['preferences' => ['bn_digits' => true]]);
    $user->branches()->attach(makeBranch($tenant));

    return $user;
}

it('requires authentication', function (): void {
    $this->patchJson('/app/api/preferences', ['locale' => 'bn'])->assertUnauthorized();
});

it('stores and merges the user\'s own preferences, without needing any role', function (): void {
    $user = prefUser();

    $this->actingAs($user)->patchJson('/app/api/preferences', ['locale' => 'bn', 'scheme' => 'indigo', 'density' => 'compact', 'theme' => 'dark'])
        ->assertOk()
        ->assertJsonPath('preferences.locale', 'bn');

    $this->patchJson('/app/api/preferences', ['scheme' => 'rose'])->assertOk();

    expect($user->fresh()->preferences)->toEqualCanonicalizing([
        'bn_digits' => true, 'locale' => 'bn', 'scheme' => 'rose', 'density' => 'compact', 'theme' => 'dark',
    ]);
});

it('rejects unknown values and ignores unknown keys', function (): void {
    $user = prefUser();

    $this->actingAs($user)->patchJson('/app/api/preferences', ['locale' => 'fr'])->assertUnprocessable()->assertJsonValidationErrors('locale');
    $this->patchJson('/app/api/preferences', ['scheme' => 'neon'])->assertUnprocessable();
    $this->patchJson('/app/api/preferences', ['density' => 'huge'])->assertUnprocessable();

    $this->patchJson('/app/api/preferences', ['locale' => 'en', 'is_platform_admin' => true, 'tenant_id' => 99])->assertOk();
    expect($user->fresh()->preferences)->not->toHaveKeys(['is_platform_admin', 'tenant_id'])
        ->and($user->fresh()->is_platform_admin)->toBeFalse();
});

it('only ever touches the signed-in user', function (): void {
    $tenant = Tenant::factory()->create();
    $me = prefUser($tenant);
    $other = prefUser($tenant);

    $this->actingAs($me)->patchJson('/app/api/preferences', ['locale' => 'bn', 'id' => $other->id])->assertOk();

    expect($other->fresh()->preferences)->toBe(['bn_digits' => true]);
});

it('shares the new locale with the server-side SetLocale middleware on the next request', function (): void {
    $user = prefUser();
    $this->actingAs($user)->patchJson('/app/api/preferences', ['locale' => 'bn'])->assertOk();

    $this->getJson('/app/api/search?q=zz');
    expect(app()->getLocale())->toBe('bn');
});
