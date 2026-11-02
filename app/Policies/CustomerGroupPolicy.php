<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerGroup;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;

final class CustomerGroupPolicy
{
    use ChecksTenantPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'customers.view');
    }

    public function view(User $user, CustomerGroup $model): bool
    {
        return $this->allows($user, 'customers.view', $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'customers.create');
    }

    public function update(User $user, CustomerGroup $model): bool
    {
        return $this->allows($user, 'customers.update', $model);
    }

    public function delete(User $user, CustomerGroup $model): bool
    {
        return $this->allows($user, 'customers.delete', $model);
    }
}
