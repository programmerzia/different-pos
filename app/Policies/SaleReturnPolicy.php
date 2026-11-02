<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SaleReturn;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;

final class SaleReturnPolicy
{
    use ChecksTenantPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'returns.view');
    }

    public function view(User $user, SaleReturn $model): bool
    {
        return $this->allows($user, 'returns.view', $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'returns.create');
    }
}
