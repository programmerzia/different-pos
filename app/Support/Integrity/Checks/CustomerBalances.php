<?php

declare(strict_types=1);

namespace App\Support\Integrity\Checks;

use App\Support\Integrity\Check;
use App\Support\Integrity\CheckResult;

/**
 * customers.balance = Σ completed sales.total − Σ return credits − Σ money received (live,
 * non-credit payments in). Walk-in customers carry no balance.
 */
final class CustomerBalances extends Check
{
    public function name(): string
    {
        return 'customer_balances';
    }

    public function run(int $tenantId): CheckResult
    {
        return $this->failing("
            SELECT c.id AS ref
            FROM customers c
            LEFT JOIN (
                SELECT customer_id, SUM(total) AS amount FROM sales
                WHERE tenant_id = {$tenantId} AND status = 'completed' GROUP BY customer_id
            ) s ON s.customer_id = c.id
            LEFT JOIN (
                SELECT customer_id, SUM(credited) AS amount FROM sale_returns
                WHERE tenant_id = {$tenantId} GROUP BY customer_id
            ) r ON r.customer_id = c.id
            LEFT JOIN (
                SELECT p.party_id, SUM(p.amount) AS amount
                FROM payments p
                JOIN payment_methods pm ON pm.id = p.payment_method_id
                WHERE p.tenant_id = {$tenantId} AND p.party_type = 'customer' AND p.direction = 'in'
                  AND p.voided_at IS NULL AND pm.type NOT IN ('customer_credit', 'exchange_credit')
                GROUP BY p.party_id
            ) pay ON pay.party_id = c.id
            WHERE c.tenant_id = {$tenantId}
              AND c.is_walk_in = 0
              AND c.balance <> COALESCE(s.amount, 0) - COALESCE(r.amount, 0) - COALESCE(pay.amount, 0)
        ");
    }
}
