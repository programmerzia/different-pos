<?php

declare(strict_types=1);

use App\Actions\Tenancy\SeedTenantRoles;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
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

it('shares the role names inside the tenant and none for a platform admin', function (): void {
    $this->withoutVite();
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();
    $user->branches()->attach(makeBranch($tenant));
    app(SeedTenantRoles::class)->handle($tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $user->assignRole(SystemRoles::CASHIER);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.roles', [SystemRoles::CASHIER]));

    $platform = app(TenantContext::class)->withoutTenancy(static fn (): User => User::factory()->create(['tenant_id' => null, 'is_platform_admin' => true]));
    // The platform console has no Inertia page yet: resolve the shared prop outside any tenant context.
    app(TenantContext::class)->forget();
    $request = Request::create('/platform');
    $request->setUserResolver(static fn (): User => $platform);
    $roles = app(HandleInertiaRequests::class)->share($request)['auth']['roles'];

    expect($roles())->toBe([]);
});
