<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenancy\SeedPermissions;
use App\Actions\Tenancy\SeedTenantRoles;
use App\Models\Tenant;
use App\Support\Permissions\Permission;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;

/**
 * Deploy hook: creates permission names added to Permission::ALL, grants each newly created one to every tenant's
 * Owner role (idempotent: a second run changes nothing). --reset-system-roles also re-applies the default permission
 * sets of the five system roles (overwrites owner customisations of those roles).
 */
final class PermissionsSync extends Command
{
    protected $signature = 'permissions:sync {--reset-system-roles : Re-apply the default permission sets of the system roles}';

    protected $description = 'Create missing permissions and grant new ones to each tenant Owner role';

    public function handle(SeedPermissions $seedPermissions, SeedTenantRoles $seedRoles, TenantContext $context): int
    {
        $existing = PermissionModel::query()->where('guard_name', Permission::GUARD)->pluck('name')->all();
        $added = array_values(array_diff(Permission::ALL, $existing));

        $seedPermissions->handle();

        $context->withoutTenancy(function () use ($added, $seedRoles): void {
            foreach (Tenant::query()->get() as $tenant) {
                if ($this->option('reset-system-roles')) {
                    $seedRoles->handle($tenant);

                    continue;
                }
                $owner = Role::query()->where('tenant_id', $tenant->id)->where('name', SystemRoles::OWNER)->first();
                if ($owner !== null && $added !== []) {
                    $owner->givePermissionTo($added);
                }
            }
        });

        $this->info($added === [] ? 'Permissions already in sync.' : 'Added: '.implode(', ', $added));

        return self::SUCCESS;
    }
}
