<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;

final class UserPolicy
{
    use ChecksTenantPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'users.manage');
    }

    public function view(User $user, User $model): bool
    {
        return $this->allows($user, 'users.manage', $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'users.manage');
    }

    public function update(User $user, User $model): bool
    {
        return $this->allows($user, 'users.manage', $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->allows($user, 'users.manage', $model);
    }
}
