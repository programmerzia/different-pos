<?php

declare(strict_types=1);

namespace App\Support\Integrity;

use App\Models\Tenant;
use App\Support\Integrity\Checks\ActiveCards;
use App\Support\Integrity\Checks\BranchStockBatches;
use App\Support\Integrity\Checks\BranchStockMovements;
use App\Support\Integrity\Checks\CustomerBalances;
use App\Support\Integrity\Checks\CustomerNumbers;
use App\Support\Integrity\Checks\DocumentSequences;
use App\Support\Integrity\Checks\SaleCogs;
use App\Support\Integrity\Checks\SalePayments;
use App\Support\Integrity\Checks\SaleTotals;
use App\Support\Integrity\Checks\SyncVersions;
use App\Support\Integrity\Checks\VendorBalances;
use App\Support\Tenancy\TenantContext;

/**
 * Runs every invariant check for one tenant. The checks are plain set-based SQL with an explicit
 * tenant_id filter, so they run outside any tenant scope.
 */
final readonly class IntegrityChecker
{
    public function __construct(private TenantContext $context) {}

    /** @return list<Check> */
    public function checks(): array
    {
        return [
            new SaleTotals,
            new SalePayments,
            new SaleCogs,
            new BranchStockMovements,
            new BranchStockBatches,
            new CustomerBalances,
            new VendorBalances,
            new ActiveCards,
            new CustomerNumbers,
            new SyncVersions,
            new DocumentSequences,
        ];
    }

    /** @return list<CheckResult> */
    public function run(Tenant $tenant): array
    {
        return $this->context->withoutTenancy(
            fn (): array => array_map(static fn (Check $check): CheckResult => $check->run($tenant->id), $this->checks()),
        );
    }
}
