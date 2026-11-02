<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TaxRate;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;

final class TaxRatePolicy
{
    use ChecksTenantPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, ['products.view', 'settings.manage']);
    }

    public function view(User $user, TaxRate $model): bool
    {
        return $this->allows($user, ['products.view', 'settings.manage'], $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'settings.manage');
    }

    public function update(User $user, TaxRate $model): bool
    {
        return $this->allows($user, 'settings.manage', $model);
    }

    public function delete(User $user, TaxRate $model): bool
    {
        return $this->allows($user, 'settings.manage', $model);
    }
}
