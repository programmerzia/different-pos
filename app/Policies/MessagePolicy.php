<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Message;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;

final class MessagePolicy
{
    use ChecksTenantPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'customers.view');
    }

    public function view(User $user, Message $model): bool
    {
        return $this->allows($user, 'customers.view', $model);
    }

    public function configure(User $user): bool
    {
        return $this->allows($user, 'settings.manage');
    }

    public function send(User $user): bool
    {
        return $this->allows($user, 'settings.manage');
    }
}
