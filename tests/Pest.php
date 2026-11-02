<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** Factories have no tenant context, so create the branch with tenancy bypassed. */
function makeBranch(Tenant $tenant): Branch
{
    return app(TenantContext::class)->withoutTenancy(
        fn (): Branch => Branch::factory()->for($tenant)->create(),
    );
}
