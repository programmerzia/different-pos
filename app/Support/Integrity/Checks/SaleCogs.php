<?php

declare(strict_types=1);

namespace App\Support\Integrity\Checks;

use App\Support\Integrity\Check;
use App\Support\Integrity\CheckResult;
use App\Support\Integrity\CheckStatus;

/**
 * sale_items.cogs = Σ batch qty × unit_cost and Σ batch qty = qty. A tracked line with no (or too
 * few) batch rows and no backorder is a failure, except on imported sales, where legacy lines
 * without a resolvable stock invoice are only a warning.
 */
final class SaleCogs extends Check
{
    public function name(): string
    {
        return 'sale_cogs';
    }

    public function run(int $tenantId): CheckResult
    {
        $lines = "
            SELECT si.id AS ref, s.origin,
                   (p.track_stock = 1 AND COALESCE(b.qty, 0) < si.qty AND NOT EXISTS (
                       SELECT 1 FROM stock_movements m
                       WHERE m.tenant_id = {$tenantId} AND m.reference_type = 'sale_items'
                         AND m.reference_id = si.id AND m.is_backorder = 1
                   )) AS unlinked,
                   (COALESCE(b.qty, 0) > si.qty OR (COALESCE(b.qty, 0) = si.qty AND si.cogs <> b.cost)) AS broken
            FROM sale_items si
            JOIN sales s ON s.id = si.sale_id AND s.tenant_id = {$tenantId}
            JOIN products p ON p.id = si.product_id
            LEFT JOIN (
                SELECT sib.sale_item_id, SUM(sib.qty) AS qty, SUM(ROUND(sib.qty * sib.unit_cost)) AS cost
                FROM sale_item_batches sib
                JOIN sale_items si2 ON si2.id = sib.sale_item_id
                JOIN sales s2 ON s2.id = si2.sale_id
                WHERE s2.tenant_id = {$tenantId}
                GROUP BY sib.sale_item_id
            ) b ON b.sale_item_id = si.id
        ";

        [$failed, $failedSample] = $this->scan("SELECT ref FROM ({$lines}) l WHERE broken = 1 OR (unlinked = 1 AND origin <> 'import')");
        if ($failed > 0) {
            return $this->result(CheckStatus::Fail, $failed, $failedSample);
        }

        [$warned, $warnedSample] = $this->scan("SELECT ref FROM ({$lines}) l WHERE unlinked = 1");

        return $this->result($warned > 0 ? CheckStatus::Warn : CheckStatus::Ok, $warned, $warnedSample);
    }
}
