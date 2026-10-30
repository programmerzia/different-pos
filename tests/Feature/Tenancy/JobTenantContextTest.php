<?php

declare(strict_types=1);

use App\Exceptions\Tenancy\MissingTenantContext;
use App\Jobs\Middleware\WithTenantContext;
use App\Models\Branch;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Context;

it('fails a tenant job that has no tenant context', function (): void {
    Context::forget(['tenant_id', 'branch_id']);

    expect(fn () => (new WithTenantContext)->handle(new stdClass, fn () => 'ran'))
        ->toThrow(MissingTenantContext::class);
});

it('restores the dispatching tenant and branch for the job, then clears them', function (): void {
    $ctx = app(TenantContext::class);
    $tenant = Tenant::factory()->create();
    $branch = $ctx->withoutTenancy(fn () => Branch::factory()->for($tenant)->create());

    // What dispatching under a request leaves in Context (and what a queue worker hydrates).
    $ctx->set($tenant, $branch);
    $ctx->forget();
    Context::add(['tenant_id' => $tenant->id, 'branch_id' => $branch->id]);

    $seen = (new WithTenantContext)->handle(new stdClass, fn (): array => [$ctx->tenantId(), $ctx->branchId()]);

    expect($seen)->toBe([$tenant->id, $branch->id])
        ->and($ctx->hasTenant())->toBeFalse();
});

it('refuses a branch id that belongs to another tenant', function (): void {
    $ctx = app(TenantContext::class);
    $a = Tenant::factory()->create();
    $foreignBranch = $ctx->withoutTenancy(fn () => Branch::factory()->for(Tenant::factory())->create());
    Context::add(['tenant_id' => $a->id, 'branch_id' => $foreignBranch->id]);

    expect(fn () => (new WithTenantContext)->handle(new stdClass, fn () => 'ran'))->toThrow(Exception::class);
});
