<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use App\Support\Platform\Impersonation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\StatefulGuard;

/** Restores the platform admin from the session (used by the banner's Exit and by the 60-minute expiry). */
final readonly class EndImpersonation
{
    public function __construct(
        private Impersonation $impersonation,
        private StatefulGuard $guard,
        private TenantContext $context,
    ) {}

    /** @return User|null the restored admin, null when no impersonation was active */
    public function handle(string $reason = 'exit'): ?User
    {
        $adminId = $this->impersonation->adminId();
        $tenantId = $this->impersonation->tenantId();
        $this->impersonation->forget();
        if ($adminId === null) {
            return null;
        }

        $admin = User::query()->whereKey($adminId)->where('is_platform_admin', true)->whereNull('tenant_id')->where('is_active', true)->first();
        $tenant = $tenantId === null ? null : Tenant::query()->find($tenantId);

        if ($tenant instanceof Tenant) {
            $this->context->withoutTenancy(fn () => Transaction::run(function () use ($tenant, $admin, $reason): void {
                Audit::log('tenant.impersonation_ended', $tenant, ['reason' => $reason], $admin);
            }));
        }

        $this->guard->logout();
        if ($admin instanceof User) {
            $this->guard->login($admin);
        }

        return $admin;
    }
}
