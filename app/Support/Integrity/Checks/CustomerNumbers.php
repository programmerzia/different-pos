<?php

declare(strict_types=1);

namespace App\Support\Integrity\Checks;

use App\Support\Customers\CustomerNumber;
use App\Support\Integrity\Check;
use App\Support\Integrity\CheckResult;
use App\Support\Integrity\CheckStatus;
use Illuminate\Support\Facades\DB;

/** Every assigned customer_no (deleted customers included) passes the Luhn check and is unique in the tenant. */
final class CustomerNumbers extends Check
{
    public function name(): string
    {
        return 'customer_numbers';
    }

    public function run(int $tenantId): CheckResult
    {
        $bad = [];
        foreach (DB::table('customers')->where('tenant_id', $tenantId)->whereNotNull('customer_no')->orderBy('id')->select(['id', 'customer_no'])->cursor() as $row) {
            if (! CustomerNumber::isValid(str_pad((string) $row->customer_no, 8, '0', STR_PAD_LEFT))) {
                $bad[] = (int) $row->id;
            }
        }

        $duplicates = DB::table('customers')->where('tenant_id', $tenantId)->whereNotNull('customer_no')
            ->groupBy('customer_no')->havingRaw('COUNT(*) > 1')->pluck('customer_no')
            ->map(static fn (mixed $number): string => (string) $number)->all();

        $sample = array_values([...$bad, ...$duplicates]);

        return $this->result($sample === [] ? CheckStatus::Ok : CheckStatus::Fail, count($bad) + count($duplicates), $sample);
    }
}
