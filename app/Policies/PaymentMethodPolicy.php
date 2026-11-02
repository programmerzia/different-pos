<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;

final class PaymentMethodPolicy
{
    use ChecksTenantPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, ['pos.sell', 'payments.collect', 'payments.pay', 'settings.manage']);
    }

    public function view(User $user, PaymentMethod $model): bool
    {
        return $this->allows($user, ['pos.sell', 'payments.collect', 'payments.pay', 'settings.manage'], $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'settings.manage');
    }

    public function update(User $user, PaymentMethod $model): bool
    {
        return $this->allows($user, 'settings.manage', $model);
    }

    public function delete(User $user, PaymentMethod $model): bool
    {
        return $this->allows($user, 'settings.manage', $model);
    }
}
