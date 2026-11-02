<?php

declare(strict_types=1);

use App\Actions\Tenancy\SeedTenantRoles;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Spatie\Permission\PermissionRegistrar;

/** A tenant with a branch, the five system roles and one user holding $role at that branch. */
function adminActor(string $role = SystemRoles::OWNER, ?Tenant $tenant = null): array
{
    $tenant ??= Tenant::factory()->create();
    $branch = makeBranch($tenant);
    app(SeedTenantRoles::class)->handle($tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $user = User::factory()->for($tenant)->create();
    $user->branches()->attach($branch);
    $user->assignRole($role);

    return [$user, $tenant, $branch];
}

function inTenant(Tenant $tenant, callable $callback): mixed
{
    return app(TenantContext::class)->withoutTenancy($callback);
}

function addBranch(Tenant $tenant, array $attributes = []): Branch
{
    return inTenant($tenant, fn (): Branch => Branch::factory()->for($tenant)->create($attributes));
}
