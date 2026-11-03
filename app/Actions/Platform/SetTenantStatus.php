<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Exceptions\Domain\InvalidAdminData;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/** Suspends or resumes a business. Suspended tenants' users are signed out by EnsureTenantActive on their next request. */
final class SetTenantStatus
{
    public function handle(User $actor, Tenant $tenant, string $status): Tenant
    {
        if (! in_array($status, [Tenant::STATUS_ACTIVE, Tenant::STATUS_SUSPENDED], true)) {
            throw InvalidAdminData::invalid('Unknown tenant status.');
        }

        return Transaction::run(function () use ($actor, $tenant, $status): Tenant {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            if ($tenant->status === $status) {
                return $tenant;
            }
            $previous = $tenant->status;
            $tenant->forceFill(['status' => $status])->save();
            Audit::log($status === Tenant::STATUS_SUSPENDED ? 'tenant.suspended' : 'tenant.resumed', $tenant, ['from' => $previous, 'to' => $status], $actor);

            return $tenant;
        });
    }
}
