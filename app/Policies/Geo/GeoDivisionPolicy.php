<?php

declare(strict_types=1);

namespace App\Policies\Geo;

use App\Models\Geo\GeoDivision;
use App\Models\User;

/** The geo reference is global, read-only data: every authenticated tenant user may read it. */
final class GeoDivisionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->is_active;
    }

    public function view(User $user, GeoDivision $model): bool
    {
        return $this->viewAny($user);
    }
}
