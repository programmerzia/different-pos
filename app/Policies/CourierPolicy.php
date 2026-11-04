<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Courier;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;

final class CourierPolicy
{
    use ChecksTenantPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'settings.manage');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'settings.manage');
    }

    public function update(User $user, Courier $model): bool
    {
        return $this->allows($user, 'settings.manage', $model);
    }

    public function delete(User $user, Courier $model): bool
    {
        return $this->allows($user, 'settings.manage', $model);
    }
}
