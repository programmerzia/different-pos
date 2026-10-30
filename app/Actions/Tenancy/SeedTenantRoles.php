<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Models\Tenant;
use App\Support\Database\Transaction;
use App\Support\Permissions\Permission;
use App\Support\Permissions\SystemRoles;
use Spatie\Permission\Models\Role;

/**
 * Creates (or refreshes the permission set of) the five system roles for a tenant. Idempotent.
 * An existing role keeps its max_discount_pct_bp, so owner customisations survive a re-run.
 */
final readonly class SeedTenantRoles
{
    public function __construct(private SeedPermissions $seedPermissions) {}

    /** @return list<Role> */
    public function handle(Tenant $tenant): array
    {
        return Transaction::run(function () use ($tenant): array {
            $this->seedPermissions->handle();

            $roles = [];
            foreach (SystemRoles::definitions() as $name => $definition) {
                $role = Role::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $name, 'guard_name' => Permission::GUARD],
                    ['max_discount_pct_bp' => $definition['max_discount_pct_bp'], 'is_system' => true],
                );
                $role->syncPermissions($definition['permissions']);
                $roles[] = $role;
            }

            return $roles;
        });
    }
}
