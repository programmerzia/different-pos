<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Legacy importer tests (tests/Feature/Legacy) use Tests\Fixtures\LegacyFixture::install(): the
| `legacy` connection is re-pointed at the testing database with the table prefix `legacy_`, so the
| fixture's store_* tables sit next to the app tables (legacy_store_product_list ...). That works the
| same under --parallel, because each process has its own testing database.
*/
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
