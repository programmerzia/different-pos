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
 * Deploy hook: creates permission names added to Permission::ALL and grants every tenant's Owner role whatever
 * of Permission::ALL it lacks (so a run that crashed between the two steps is repaired by the next one;
 * idempotent: a second run changes nothing). --reset-system-roles also re-applies the default permission
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

        /** @var list<string> $granted */
        $granted = [];
        $context->withoutTenancy(function () use ($seedRoles, &$granted): void {
            foreach (Tenant::query()->get() as $tenant) {
                if ($this->option('reset-system-roles')) {
                    $seedRoles->handle($tenant);

                    continue;
                }
                $owner = Role::query()->where('tenant_id', $tenant->id)->where('name', SystemRoles::OWNER)->first();
                if ($owner === null) {
                    continue;
                }
                $missing = array_values(array_diff(Permission::ALL, $owner->permissions()->pluck('name')->all()));
                if ($missing !== []) {
                    $owner->givePermissionTo($missing);
                    $granted = array_values(array_unique([...$granted, ...$missing]));
                }
            }
        });

        $this->info(match (true) {
            $added !== [] => 'Added: '.implode(', ', $added),
            $granted !== [] => 'Granted to Owner: '.implode(', ', $granted),
            default => 'Permissions already in sync.',
        });

        return self::SUCCESS;
    }
}
