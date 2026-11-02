<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MembershipCard;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantPermissions;

final class MembershipCardPolicy
{
    use ChecksTenantPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'cards.view');
    }

    public function view(User $user, MembershipCard $model): bool
    {
        return $this->allows($user, 'cards.view', $model);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'cards.issue');
    }

    public function reissue(User $user, MembershipCard $model): bool
    {
        return $this->allows($user, 'cards.issue', $model);
    }

    public function block(User $user, MembershipCard $model): bool
    {
        return $this->allows($user, 'cards.issue', $model);
    }
}
