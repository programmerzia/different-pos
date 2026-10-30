<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Exceptions\Tenancy\MissingTenantContext;
use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Support\Facades\Context;

/**
 * The tenant (and optionally branch) the current request, job or command acts for.
 * Registered as a *scoped* singleton, so it is reset for every request and queued job.
 *
 * Tenant ids are also put into Laravel's Context, which travels with queued jobs
 * (restored by the WithTenantContext job middleware) and is attached to log entries.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    private ?Branch $branch = null;

    private int $bypassDepth = 0;

    public function set(Tenant $tenant, ?Branch $branch = null): void
    {
        if ($branch instanceof Branch && $branch->tenant_id !== $tenant->id) {
            throw new MissingTenantContext('Branch '.$branch->id.' does not belong to tenant '.$tenant->id.'.');
        }

        $this->tenant = $tenant;
        $this->branch = $branch;
        Context::add('tenant_id', $tenant->id);
        $branch instanceof Branch ? Context::add('branch_id', $branch->id) : Context::forget('branch_id');
    }

    public function forget(): void
    {
        $this->tenant = null;
        $this->branch = null;
        Context::forget(['tenant_id', 'branch_id']);
    }

    /**
     * Runs the callback for a tenant/branch and restores the previous context afterwards.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(Tenant $tenant, ?Branch $branch, callable $callback): mixed
    {
        [$previousTenant, $previousBranch] = [$this->tenant, $this->branch];
        $this->set($tenant, $branch);

        try {
            return $callback();
        } finally {
            $previousTenant instanceof Tenant ? $this->set($previousTenant, $previousBranch) : $this->forget();
        }
    }

    /**
     * Runs platform-level code (tenant provisioning, auth lookups, legacy import bootstrap) with the
     * tenant/branch scopes switched off. Keep these blocks small and explicit.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withoutTenancy(callable $callback): mixed
    {
        $this->bypassDepth++;

        try {
            return $callback();
        } finally {
            $this->bypassDepth--;
        }
    }

    public function isBypassed(): bool
    {
        return $this->bypassDepth > 0;
    }

    public function hasTenant(): bool
    {
        return $this->tenant instanceof Tenant;
    }

    public function tenant(): Tenant
    {
        return $this->tenant ?? throw MissingTenantContext::tenant();
    }

    public function tenantId(): int
    {
        return $this->tenant()->id;
    }

    public function hasBranch(): bool
    {
        return $this->branch instanceof Branch;
    }

    public function branch(): Branch
    {
        return $this->branch ?? throw MissingTenantContext::branch();
    }

    public function branchId(): int
    {
        return $this->branch()->id;
    }
}
