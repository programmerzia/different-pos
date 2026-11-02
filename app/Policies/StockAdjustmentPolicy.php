<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockAdjustment;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;

final class StockAdjustmentPolicy
{
    use ChecksTenantPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'stock.view');
    }

    public function view(User $user, StockAdjustment $model): bool
    {
        return $this->allows($user, 'stock.view', $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'stock.adjust');
    }
}
