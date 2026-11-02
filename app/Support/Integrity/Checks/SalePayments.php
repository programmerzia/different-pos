<?php

declare(strict_types=1);

namespace App\Support\Integrity\Checks;

use App\Support\Integrity\Check;
use App\Support\Integrity\CheckResult;

/** Completed sales: paid = Σ allocations of live payments, due = total − paid, due ≥ 0. */
final class SalePayments extends Check
{
    public function name(): string
    {
        return 'sale_payments';
    }

    public function run(int $tenantId): CheckResult
    {
        return $this->failing("
            SELECT s.id AS ref
            FROM sales s
            LEFT JOIN (
                SELECT pa.allocatable_id AS sale_id, SUM(pa.amount) AS allocated
                FROM payment_allocations pa
                JOIN payments p ON p.id = pa.payment_id
                WHERE p.tenant_id = {$tenantId} AND pa.allocatable_type = 'sale' AND p.voided_at IS NULL
                GROUP BY pa.allocatable_id
            ) a ON a.sale_id = s.id
            WHERE s.tenant_id = {$tenantId}
              AND s.status = 'completed'
              AND (s.paid <> COALESCE(a.allocated, 0) OR s.due <> s.total - s.paid OR s.due < 0)
        ");
    }
}
