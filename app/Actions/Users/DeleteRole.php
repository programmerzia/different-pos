<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Exceptions\Domain\InvalidAdminData;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Deletes a custom role that nobody holds. System roles stay. */
final readonly class DeleteRole
{
    public function handle(User $actor, Role $role): void
    {
        Transaction::run(function () use ($actor, $role): void {
            $tenantId = app(TenantContext::class)->tenantId();
            $role = Role::query()->where('tenant_id', $tenantId)->findOrFail($role->id);

            if ((bool) $role->getAttribute('is_system')) {
                throw InvalidAdminData::invalid('A system role cannot be deleted.');
            }
            if (DB::table((string) config('permission.table_names.model_has_roles'))->where('role_id', $role->id)->exists()) {
                throw InvalidAdminData::invalid('Remove this role from its users before deleting it.');
            }

            Audit::log('role.deleted', $role, ['name' => $role->name], $actor);
            $role->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }
}
