<?php

declare(strict_types=1);

namespace App\Support\Integrity\Checks;

use App\Support\Integrity\Check;
use App\Support\Integrity\CheckResult;

/** At most one active membership card per customer. */
final class ActiveCards extends Check
{
    public function name(): string
    {
        return 'one_active_card';
    }

    public function run(int $tenantId): CheckResult
    {
        return $this->failing("
            SELECT customer_id AS ref FROM membership_cards
            WHERE tenant_id = {$tenantId} AND status = 'active'
            GROUP BY customer_id HAVING COUNT(*) > 1
        ");
    }
}
