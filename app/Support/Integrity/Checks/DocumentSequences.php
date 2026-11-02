<?php

declare(strict_types=1);

namespace App\Support\Integrity\Checks;

use App\Support\Integrity\Check;
use App\Support\Integrity\CheckResult;

/**
 * A document sequence must be ahead of the highest number already used for its type and branch.
 * Numbers read prefix + (yy when the sequence resets yearly) + digits; only numbers of the
 * sequence's current year count. Customer numbers are the 7-digit body of customer_no.
 * Refs read "type@branch".
 */
final class DocumentSequences extends Check
{
    /** Sequence type => table holding its numbers. */
    private const array TABLES = [
        'sale' => 'sales',
        'return' => 'sale_returns',
        'payment' => 'payments',
        'purchase' => 'purchases',
        'adjustment' => 'stock_adjustments',
    ];

    public function name(): string
    {
        return 'document_sequences';
    }

    public function run(int $tenantId): CheckResult
    {
        $selects = [];
        foreach (self::TABLES as $type => $table) {
            $selects[] = "
                SELECT CONCAT(ds.type, '@', COALESCE(ds.branch_id, '-')) AS ref
                FROM document_sequences ds
                WHERE ds.tenant_id = {$tenantId} AND ds.type = '{$type}'
                  AND ds.next_number <= COALESCE((
                      SELECT MAX(CAST(SUBSTRING(t.number, CHAR_LENGTH(ds.prefix) + IF(ds.reset_yearly, 2, 0) + 1) AS UNSIGNED))
                      FROM `{$table}` t
                      WHERE t.tenant_id = ds.tenant_id AND t.branch_id <=> ds.branch_id
                        AND LEFT(t.number, CHAR_LENGTH(ds.prefix)) = ds.prefix
                        AND (ds.reset_yearly = 0 OR SUBSTRING(t.number, CHAR_LENGTH(ds.prefix) + 1, 2) = RIGHT(ds.current_year, 2))
                        AND SUBSTRING(t.number, CHAR_LENGTH(ds.prefix) + IF(ds.reset_yearly, 2, 0) + 1) REGEXP '^[0-9]+\$'
                  ), 0)
            ";
        }

        $selects[] = "
            SELECT CONCAT(ds.type, '@-') AS ref
            FROM document_sequences ds
            WHERE ds.tenant_id = {$tenantId} AND ds.type = 'customer'
              AND ds.next_number <= COALESCE((SELECT MAX(c.customer_no DIV 10) FROM customers c WHERE c.tenant_id = ds.tenant_id), 0)
        ";

        return $this->failing(implode(' UNION ALL ', $selects));
    }
}
