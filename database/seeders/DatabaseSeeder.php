<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(['slug' => 'demo-store'], [
            'name' => 'Demo Store',
            'locale' => 'en',
            'timezone' => 'Asia/Dhaka',
            'currency' => 'BDT',
        ]);

        $branch = app(TenantContext::class)->withoutTenancy(
            fn (): Branch => Branch::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'MAIN'],
                ['name' => 'Main Branch', 'is_active' => true],
            ),
        );

        $this->call(RolesAndPermissionsSeeder::class);

        $owner = User::query()->firstOrNew(['email' => 'owner@demo.test']);
        $owner->forceFill([
            'name' => 'Demo Owner',
            'password' => 'password',
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
            'is_active' => true,
        ])->save();
        $owner->branches()->syncWithoutDetaching([$branch->id => ['is_default' => true]]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $owner->assignRole(SystemRoles::OWNER);

        $admin = User::query()->firstOrNew(['email' => 'admin@demo.test']);
        $admin->forceFill([
            'name' => 'Platform Admin',
            'password' => 'password',
            'is_platform_admin' => true,
            'email_verified_at' => now(),
            'is_active' => true,
        ])->save();
    }
}
