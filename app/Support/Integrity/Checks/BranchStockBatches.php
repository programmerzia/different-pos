<?php

declare(strict_types=1);

namespace App\Support\Integrity\Checks;

use App\Support\Integrity\Check;
use App\Support\Integrity\CheckResult;

/**
 * branch_stocks.qty_on_hand = Σ stock_batches.qty_remaining per (branch, product). Pairs that
 * ever had a backorder are left out: a backorder is stock taken without a batch.
 */
final class BranchStockBatches extends Check
{
    public function name(): string
    {
        return 'stock_vs_batches';
    }

    public function run(int $tenantId): CheckResult
    {
        return $this->failing("
            SELECT CONCAT(k.branch_id, ':', k.product_id) AS ref
            FROM (
                SELECT branch_id, product_id FROM branch_stocks WHERE tenant_id = {$tenantId}
                UNION
                SELECT branch_id, product_id FROM stock_batches WHERE tenant_id = {$tenantId}
            ) k
            LEFT JOIN branch_stocks bs ON bs.branch_id = k.branch_id AND bs.product_id = k.product_id
            LEFT JOIN (
                SELECT branch_id, product_id, SUM(qty_remaining) AS qty
                FROM stock_batches WHERE tenant_id = {$tenantId}
                GROUP BY branch_id, product_id
            ) b ON b.branch_id = k.branch_id AND b.product_id = k.product_id
            WHERE COALESCE(bs.qty_on_hand, 0) <> COALESCE(b.qty, 0)
              AND NOT EXISTS (
                  SELECT 1 FROM stock_movements m
                  WHERE m.tenant_id = {$tenantId} AND m.branch_id = k.branch_id
                    AND m.product_id = k.product_id AND m.is_backorder = 1
              )
        ");
    }
}
