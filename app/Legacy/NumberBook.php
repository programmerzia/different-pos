<?php

declare(strict_types=1);

namespace App\Legacy;

use Illuminate\Support\Facades\DB;

/**
 * Document numbers already used in the tenant for one table. A legacy number is kept as-is. When
 * another branch already uses it, it gets the branch code prefix ("DF2-S-1"); a true duplicate within
 * the same branch gets "-D2", "-D3"... (ADR 0002 M3). Both cases are reported.
 */
final class NumberBook
{
    /** @var array<string, int> lower-cased number => branch id that holds it */
    private array $used = [];

    public function __construct(string $table, int $tenantId)
    {
        foreach (DB::table($table)->where('tenant_id', $tenantId)->get(['number', 'branch_id']) as $row) {
            $this->used[mb_strtolower((string) $row->number)] = (int) $row->branch_id;
        }
    }

    public function claim(ImportContext $context, ?string $number, int $branchId, string $branchCode, string $legacyTable, int $legacyId): string
    {
        $base = $number === null || $number === '' ? 'L'.$legacyId : mb_substr($number, 0, 20);
        $candidate = $base;

        $holder = $this->used[mb_strtolower($base)] ?? null;
        if ($holder !== null && $holder !== $branchId) {
            $candidate = $branchCode.'-'.$base;
            $context->report->add('number_prefixed', $legacyTable, $legacyId, "Number [{$base}] is used by another branch; imported as [{$candidate}]");
        }

        if (isset($this->used[mb_strtolower($candidate)])) {
            $stem = $candidate;
            $suffix = 2;
            do {
                $candidate = $stem.'-D'.$suffix++;
            } while (isset($this->used[mb_strtolower($candidate)]));
            $context->report->add('duplicate_number', $legacyTable, $legacyId, "Number [{$base}] is duplicated; imported as [{$candidate}]");
        }

        $this->used[mb_strtolower($candidate)] = $branchId;

        return $candidate;
    }
}
