<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Register;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;

final class RegisterPolicy
{
    use ChecksTenantPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, ['pos.sell', 'settings.manage']);
    }

    public function view(User $user, Register $model): bool
    {
        return $this->allows($user, ['pos.sell', 'settings.manage'], $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'settings.manage');
    }

    public function update(User $user, Register $model): bool
    {
        return $this->allows($user, 'settings.manage', $model);
    }

    public function delete(User $user, Register $model): bool
    {
        return $this->allows($user, 'settings.manage', $model);
    }
}
