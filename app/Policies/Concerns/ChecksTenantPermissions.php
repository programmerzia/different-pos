<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared policy plumbing: a permission check that first insists the actor and the subject
 * both belong to the tenant of the current context (defence in depth next to the global scopes).
 */
trait ChecksTenantPermissions
{
    /**
     * @param  string|list<string>  $permissions  any one of them grants (an empty list = any tenant member)
     * @param  Model|class-string<Model>|null  $subject
     */
    protected function allows(User $user, string|array $permissions, Model|string|null $subject = null): bool
    {
        if (! $this->sameTenant($user, $subject)) {
            return false;
        }

        $permissions = (array) $permissions;
        if ($permissions === []) {
            return true;
        }

        return array_any($permissions, fn ($permission): bool => $user->checkPermissionTo($permission));
    }

    /** @param  Model|class-string<Model>|null  $subject */
    protected function sameTenant(User $user, Model|string|null $subject): bool
    {
        $context = app(TenantContext::class);
        if (! $context->hasTenant() || $user->tenant_id !== $context->tenantId()) {
            return false;
        }

        if (! $subject instanceof Model) {
            return true;
        }

        $subjectTenant = $subject instanceof Tenant ? $subject->id : $subject->getAttribute('tenant_id');

        return $subjectTenant === $context->tenantId();
    }
}
