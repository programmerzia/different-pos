<?php

declare(strict_types=1);

use App\Actions\Tenancy\SeedTenantRoles;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Permissions\SystemRoles;
use Spatie\Permission\PermissionRegistrar;

it('redirects guests to the login page', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('shows the dashboard to a permitted user with a branch', function (): void {
    $tenant = Tenant::factory()->create();
    $branch = makeBranch($tenant);
    $user = User::factory()->for($tenant)->create();
    $user->branches()->attach($branch);
    app(SeedTenantRoles::class)->handle($tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $user->assignRole(SystemRoles::CASHIER);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    expect(route('dashboard', absolute: false))->toBe('/app');
});

it('forbids a user without dashboard.view', function (): void {
    $tenant = Tenant::factory()->create();
    $branch = makeBranch($tenant);
    $user = User::factory()->for($tenant)->create();
    $user->branches()->attach($branch);

    $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
});
