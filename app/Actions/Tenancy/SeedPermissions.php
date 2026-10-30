<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Support\Database\Transaction;
use App\Support\Permissions\Permission;
use Spatie\Permission\Models\Permission as PermissionModel;

/** Creates the global permission rows (idempotent). */
final class SeedPermissions
{
    public function handle(): void
    {
        Transaction::run(function (): void {
            foreach (Permission::ALL as $name) {
                PermissionModel::findOrCreate($name, Permission::GUARD);
            }
        });
    }
}
