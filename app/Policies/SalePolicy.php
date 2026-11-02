<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;

final class SalePolicy
{
    use ChecksTenantPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'sales.view');
    }

    public function view(User $user, Sale $model): bool
    {
        return $this->allows($user, 'sales.view', $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'pos.sell');
    }

    public function void(User $user, Sale $model): bool
    {
        return $this->allows($user, 'sales.void', $model);
    }
}
