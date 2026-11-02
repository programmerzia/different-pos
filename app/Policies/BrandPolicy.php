<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;

final class BrandPolicy
{
    use ChecksTenantPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'products.view');
    }

    public function view(User $user, Brand $model): bool
    {
        return $this->allows($user, 'products.view', $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'products.create');
    }

    public function update(User $user, Brand $model): bool
    {
        return $this->allows($user, 'products.update', $model);
    }

    public function delete(User $user, Brand $model): bool
    {
        return $this->allows($user, 'products.delete', $model);
    }
}
