<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;

final class PurchasePolicy
{
    use ChecksTenantPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'purchases.view');
    }

    public function view(User $user, Purchase $model): bool
    {
        return $this->allows($user, 'purchases.view', $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'purchases.create');
    }

    public function update(User $user, Purchase $model): bool
    {
        return $this->allows($user, 'purchases.create', $model);
    }

    public function receive(User $user, Purchase $model): bool
    {
        return $this->allows($user, 'purchases.receive', $model);
    }

    public function cancel(User $user, Purchase $model): bool
    {
        return $this->allows($user, 'purchases.cancel', $model);
    }
}
