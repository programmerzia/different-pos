<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;

final class PaymentPolicy
{
    use ChecksTenantPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, ['payments.collect', 'payments.pay']);
    }

    public function view(User $user, Payment $model): bool
    {
        return $this->allows($user, ['payments.collect', 'payments.pay'], $model);
    }

    public function collect(User $user): bool
    {
        return $this->allows($user, 'payments.collect');
    }

    public function pay(User $user): bool
    {
        return $this->allows($user, 'payments.pay');
    }
}
