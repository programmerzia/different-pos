<?php

declare(strict_types=1);

namespace App\Support\Integrity\Checks;

use App\Support\Integrity\Check;
use App\Support\Integrity\CheckResult;

/** vendors.balance = Σ dues of live received purchases − vendor advances (payments out not allocated). */
final class VendorBalances extends Check
{
    public function name(): string
    {
        return 'vendor_balances';
    }

    public function run(int $tenantId): CheckResult
    {
        return $this->failing("
            SELECT v.id AS ref
            FROM vendors v
            LEFT JOIN (
                SELECT vendor_id, SUM(due) AS amount FROM purchases
                WHERE tenant_id = {$tenantId} AND status = 'received' AND deleted_at IS NULL
                GROUP BY vendor_id
            ) d ON d.vendor_id = v.id
            LEFT JOIN (
                SELECT p.party_id, SUM(p.amount - COALESCE(a.allocated, 0)) AS amount
                FROM payments p
                LEFT JOIN (
                    SELECT payment_id, SUM(amount) AS allocated FROM payment_allocations GROUP BY payment_id
                ) a ON a.payment_id = p.id
                WHERE p.tenant_id = {$tenantId} AND p.party_type = 'vendor' AND p.direction = 'out' AND p.voided_at IS NULL
                GROUP BY p.party_id
            ) adv ON adv.party_id = v.id
            WHERE v.tenant_id = {$tenantId}
              AND v.balance <> COALESCE(d.amount, 0) - COALESCE(adv.amount, 0)
        ");
    }
}
