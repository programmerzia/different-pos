<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Actions\Tenancy\SeedTenantRoles;
use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Spatie\Permission\PermissionRegistrar;

/** Tenant, branch, role-holding user, customers and sales for the customer, statement and card page tests. */
final class CustomerWorld
{
    /** @return array{0: User, 1: Tenant, 2: Branch} */
    public static function actor(?string $role = SystemRoles::OWNER): array
    {
        app(TenantContext::class)->forget();
        $tenant = Tenant::factory()->create();
        $branch = app(TenantContext::class)->withoutTenancy(fn (): Branch => Branch::factory()->for($tenant)->create());
        $user = User::factory()->for($tenant)->create();
        $user->branches()->attach($branch);
        app(SeedTenantRoles::class)->handle($tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        if ($role !== null) {
            $user->assignRole($role);
        }

        return [$user, $tenant, $branch];
    }

    /** @param array<string, mixed> $attributes */
    public static function make(Tenant $tenant, array $attributes = []): Customer
    {
        return app(TenantContext::class)->withoutTenancy(
            fn (): Customer => Customer::factory()->for($tenant)->create($attributes),
        );
    }

    /** @param array<string, mixed> $attributes */
    public static function sale(Tenant $tenant, Branch $branch, Customer $customer, array $attributes = []): Sale
    {
        return app(TenantContext::class)->withoutTenancy(fn (): Sale => Sale::factory()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'customer_id' => $customer->id,
            'status' => SaleStatus::Completed, ...$attributes,
        ]));
    }
}
