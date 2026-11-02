<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Exceptions\Domain\InvalidAdminData;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use App\Support\Permissions\Permission;
use App\Support\Permissions\SystemRoles;
use App\Support\Tenancy\TenantContext;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates a custom role or updates one. A system role keeps its name; the Owner keeps every
 * permission; every role's discount cap (basis points) can be changed.
 */
final readonly class SaveRole
{
    /** @param array{name?: string, permissions?: list<string>, max_discount_pct_bp?: int} $data */
    public function handle(User $actor, array $data, ?Role $role = null): Role
    {
        return Transaction::run(function () use ($actor, $data, $role): Role {
            $tenantId = app(TenantContext::class)->tenantId();
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

            $role = $role instanceof Role ? Role::query()->where('tenant_id', $tenantId)->findOrFail($role->id) : null;
            $created = ! $role instanceof Role;
            $system = $role instanceof Role && (bool) $role->getAttribute('is_system');

            $cap = $data['max_discount_pct_bp'] ?? ($role?->getAttribute('max_discount_pct_bp') ?? 0);
            if ($cap < 0 || $cap > 10_000) {
                throw InvalidAdminData::invalid('The discount cap must be between 0 and 100 percent.');
            }

            $permissions = $data['permissions'] ?? null;
            if ($permissions !== null) {
                $unknown = array_diff($permissions, Permission::ALL);
                if ($unknown !== []) {
                    throw InvalidAdminData::invalid('Unknown permission: '.reset($unknown));
                }
            }

            if ($created) {
                $name = trim((string) ($data['name'] ?? ''));
                if ($name === '') {
                    throw InvalidAdminData::required('Name');
                }
                $this->assertNameFree($tenantId, $name, null);
                $role = Role::query()->create([
                    'name' => $name,
                    'guard_name' => Permission::GUARD,
                    'tenant_id' => $tenantId,
                    'max_discount_pct_bp' => $cap,
                    'is_system' => false,
                ]);
            } else {
                $name = trim((string) ($data['name'] ?? $role->name));
                if ($system && $name !== $role->name) {
                    throw InvalidAdminData::invalid('A system role cannot be renamed.');
                }
                if (! $system && $name !== $role->name) {
                    $this->assertNameFree($tenantId, $name, (int) $role->id);
                }
                $role->forceFill(['name' => $name, 'max_discount_pct_bp' => $cap])->save();
            }

            $isOwner = $role->name === SystemRoles::OWNER;
            if ($permissions !== null && (! $system || ! $isOwner)) {
                $role->syncPermissions($permissions);
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            Audit::log($created ? 'role.created' : 'role.updated', $role, [
                'name' => $role->name,
                'max_discount_pct_bp' => $cap,
                'permissions' => $permissions,
            ], $actor);

            return $role;
        });
    }

    private function assertNameFree(int $tenantId, string $name, ?int $ignoreId): void
    {
        $exists = Role::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw InvalidAdminData::duplicate('role', $name);
        }
    }
}
