<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\Branch;
use App\Models\User;
use App\Support\Database\Transaction;

/** Remembers the branch the user last worked in (users.preferences.last_branch_id). The session part is the controller's. */
final readonly class SwitchBranch
{
    public function handle(User $user, Branch $branch): void
    {
        Transaction::run(function () use ($user, $branch): void {
            $user->forceFill([
                'preferences' => [...($user->preferences ?? []), 'last_branch_id' => $branch->id],
            ])->save();
        });
    }
}
