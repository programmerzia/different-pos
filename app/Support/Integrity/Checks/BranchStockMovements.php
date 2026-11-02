<?php

declare(strict_types=1);

namespace App\Support\Integrity\Checks;

use App\Support\Integrity\Check;
use App\Support\Integrity\CheckResult;

/** branch_stocks.qty_on_hand = Σ stock_movements.qty per (branch, product). Refs read "branch:product". */
final class BranchStockMovements extends Check
{
    public function name(): string
    {
        return 'stock_vs_movements';
    }

    public function run(int $tenantId): CheckResult
    {
        return $this->failing("
            SELECT CONCAT(k.branch_id, ':', k.product_id) AS ref
            FROM (
                SELECT branch_id, product_id FROM branch_stocks WHERE tenant_id = {$tenantId}
                UNION
                SELECT branch_id, product_id FROM stock_movements WHERE tenant_id = {$tenantId}
            ) k
            LEFT JOIN branch_stocks bs ON bs.branch_id = k.branch_id AND bs.product_id = k.product_id
            LEFT JOIN (
                SELECT branch_id, product_id, SUM(qty) AS qty
                FROM stock_movements WHERE tenant_id = {$tenantId}
                GROUP BY branch_id, product_id
            ) m ON m.branch_id = k.branch_id AND m.product_id = k.product_id
            WHERE COALESCE(bs.qty_on_hand, 0) <> COALESCE(m.qty, 0)
        ");
    }
}
