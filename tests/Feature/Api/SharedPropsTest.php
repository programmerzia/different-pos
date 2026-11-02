<?php

declare(strict_types=1);

use App\Actions\Tenancy\SeedTenantRoles;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Permissions\SystemRoles;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

it('shares the user\'s permissions, own branches and preferences with every page', function (): void {
    $this->withoutVite();
    $tenant = Tenant::factory()->create();
    $branch = makeBranch($tenant);
    $otherTenantBranch = makeBranch(Tenant::factory()->create());
    $user = User::factory()->for($tenant)->create(['preferences' => ['locale' => 'bn', 'scheme' => 'rose']]);
    $user->branches()->attach($branch);
    app(SeedTenantRoles::class)->handle($tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $user->assignRole(SystemRoles::CASHIER);

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('auth.permissions', fn ($permissions) => in_array('pos.sell', $permissions->all(), true)
            && ! in_array('users.manage', $permissions->all(), true))
        ->where('auth.branches', [['id' => $branch->id, 'name' => $branch->name]])
        ->where('auth.branch_id', $branch->id)
        ->where('preferences.locale', 'bn')
        ->where('preferences.scheme', 'rose')
        ->where('preferences.density', 'comfortable')
        ->missing('auth.branches.1')
    );

    expect($otherTenantBranch->tenant_id)->not->toBe($tenant->id);
});

it('renders the root view with the scheme and density attributes from the preferences', function (): void {
    $this->withoutVite();
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create(['preferences' => ['scheme' => 'ocean', 'density' => 'compact']]);
    $user->branches()->attach(makeBranch($tenant));
    app(SeedTenantRoles::class)->handle($tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $user->assignRole(SystemRoles::OWNER);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-scheme="ocean"', false)
        ->assertSee('data-density="compact"', false);
});
