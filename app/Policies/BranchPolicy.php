<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;

final class BranchPolicy
{
    use ChecksTenantPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, []);
    }

    public function view(User $user, Branch $model): bool
    {
        return $this->allows($user, [], $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'settings.manage');
    }

    public function update(User $user, Branch $model): bool
    {
        return $this->allows($user, 'settings.manage', $model);
    }

    public function delete(User $user, Branch $model): bool
    {
        return $this->allows($user, 'settings.manage', $model);
    }
}
