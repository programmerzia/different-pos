<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;
use App\Policies\Concerns\ChecksTenantPermissions;

final class VendorPolicy
{
    use ChecksTenantPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'vendors.view');
    }

    public function view(User $user, Vendor $model): bool
    {
        return $this->allows($user, 'vendors.view', $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'vendors.create');
    }

    public function update(User $user, Vendor $model): bool
    {
        return $this->allows($user, 'vendors.update', $model);
    }

    public function delete(User $user, Vendor $model): bool
    {
        return $this->allows($user, 'vendors.delete', $model);
    }
}
