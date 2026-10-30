<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use App\Exceptions\Tenancy\MissingTenantContext;
use App\Models\Branch;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\Context;

/**
 * Restores the tenant/branch a job was dispatched under (04_TRD §3.3).
 * The ids travel in Laravel's Context, which TenantContext::set() fills at dispatch time.
 * A tenant job dispatched without a context fails instead of running unscoped.
 */
final class WithTenantContext
{
    public function handle(object $job, Closure $next): mixed
    {
        $tenantId = Context::get('tenant_id');
        $branchId = Context::get('branch_id');

        if (! is_int($tenantId)) {
            throw MissingTenantContext::tenant();
        }

        $context = app(TenantContext::class);
        $tenant = Tenant::query()->findOrFail($tenantId);
        $branch = is_int($branchId)
            ? $context->withoutTenancy(fn () => Branch::query()->where('tenant_id', $tenantId)->findOrFail($branchId))
            : null;

        return $context->run($tenant, $branch, fn (): mixed => $next($job));
    }
}
