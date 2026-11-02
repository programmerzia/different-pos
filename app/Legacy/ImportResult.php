<?php

declare(strict_types=1);

namespace App\Legacy;

final readonly class ImportResult
{
    /**
     * @param  array<string, array{created: int, skipped: int, ms: int}>  $steps
     * @param  array<string, int>  $reportCounts
     * @param  list<array<string, string|int>>  $reconciliation
     */
    public function __construct(
        public int $tenantId,
        public bool $dryRun,
        public array $steps,
        public array $reportCounts,
        public array $reconciliation,
        public string $reportPath,
        public string $reconciliationPath,
        public float $seconds,
    ) {}
}
