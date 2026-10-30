<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Tenancy\SeedPermissions;
use App\Actions\Tenancy\SeedTenantRoles;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/** Seeds the global permissions and the system roles of every existing tenant. Idempotent. */
final class RolesAndPermissionsSeeder extends Seeder
{
    public function run(SeedPermissions $permissions, SeedTenantRoles $roles): void
    {
        $permissions->handle();

        foreach (Tenant::query()->cursor() as $tenant) {
            $roles->handle($tenant);
        }
    }
}
