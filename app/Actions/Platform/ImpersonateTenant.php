<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Exceptions\Domain\InvalidAdminData;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use App\Support\Permissions\SystemRoles;
use App\Support\Platform\Impersonation;
use Illuminate\Contracts\Auth\StatefulGuard;
use Spatie\Permission\PermissionRegistrar;

/** Signs a platform admin in as the business's (first) active Owner, remembering the admin in the session. Audited on the tenant. */
final readonly class ImpersonateTenant
{
    public function __construct(
        private Impersonation $impersonation,
        private PermissionRegistrar $permissions,
        private StatefulGuard $guard,
    ) {}

    public function handle(User $admin, Tenant $tenant): User
    {
        if (! $admin->is_platform_admin || $admin->tenant_id !== null) {
            throw InvalidAdminData::invalid('Only a platform admin can impersonate a business.');
        }
        if ($this->impersonation->isActive()) {
            throw InvalidAdminData::invalid('End the current impersonation first.');
        }
        if (! $tenant->isActive()) {
            throw InvalidAdminData::invalid('The business is suspended; resume it before signing in.');
        }

        $this->permissions->setPermissionsTeamId($tenant->id);
        $owner = User::query()->where('tenant_id', $tenant->id)->where('is_active', true)->role(SystemRoles::OWNER)->orderBy('id')->first();
        if (! $owner instanceof User) {
            throw InvalidAdminData::invalid(__('app.impersonation_no_owner'));
        }

        Transaction::run(function () use ($admin, $tenant, $owner): void {
            Audit::log('tenant.impersonated', $tenant, ['as_user_id' => $owner->id, 'as_user' => $owner->name, 'admin_id' => $admin->id], $admin);
        });

        $this->impersonation->start($admin, $tenant);
        $this->guard->login($owner);

        return $owner;
    }
}
