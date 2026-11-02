<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;

final class CustomerPolicy
{
    use ChecksTenantPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'customers.view');
    }

    public function view(User $user, Customer $model): bool
    {
        return $this->allows($user, 'customers.view', $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'customers.create');
    }

    public function update(User $user, Customer $model): bool
    {
        return $this->allows($user, 'customers.update', $model);
    }

    public function delete(User $user, Customer $model): bool
    {
        return $this->allows($user, 'customers.delete', $model);
    }

    public function resetPin(User $user, Customer $model): bool
    {
        return $this->allows($user, 'customers.reset_pin', $model);
    }
}
