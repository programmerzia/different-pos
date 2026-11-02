<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;

final class TenantPolicy
{
    use ChecksTenantPermissions;

    public function view(User $user, Tenant $model): bool
    {
        return $this->allows($user, [], $model);
    }

    public function update(User $user, Tenant $model): bool
    {
        return $this->allows($user, 'settings.manage', $model);
    }
}
