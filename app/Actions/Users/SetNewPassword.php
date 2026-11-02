<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/** Sets a new password and clears the forced-change flag (migrated legacy users). */
final readonly class SetNewPassword
{
    public function handle(User $user, string $password): void
    {
        Transaction::run(function () use ($user, $password): void {
            $user->forceFill([
                'password' => $password,
                'legacy_password_md5' => null,
                'must_change_password' => false,
            ])->save();

            Audit::log('user.password_changed', $user, [], $user);
        });
    }
}
