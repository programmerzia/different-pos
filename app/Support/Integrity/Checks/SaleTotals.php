<?php

declare(strict_types=1);

namespace App\Support\Integrity\Checks;

use App\Support\Integrity\Check;
use App\Support\Integrity\CheckResult;

/** sales.total = Σ line_total − order_discount + shipping + rounding, and subtotal = Σ qty × unit_price. */
final class SaleTotals extends Check
{
    public function name(): string
    {
        return 'sale_totals';
    }

    public function run(int $tenantId): CheckResult
    {
        return $this->failing("
            SELECT s.id AS ref
            FROM sales s
            LEFT JOIN (
                SELECT si.sale_id, SUM(si.line_total) AS line_sum, SUM(ROUND(si.qty * si.unit_price)) AS gross
                FROM sale_items si
                JOIN sales s2 ON s2.id = si.sale_id
                WHERE s2.tenant_id = {$tenantId}
                GROUP BY si.sale_id
            ) i ON i.sale_id = s.id
            WHERE s.tenant_id = {$tenantId}
              AND (s.total <> COALESCE(i.line_sum, 0) - s.order_discount + s.shipping + s.rounding
                   OR s.subtotal <> COALESCE(i.gross, 0))
        ");
    }
}
