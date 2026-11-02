<?php

declare(strict_types=1);

namespace App\Legacy;

use InvalidArgumentException;

final readonly class ImportOptions
{
    /** @param list<int> $stores */
    public function __construct(
        public string $connection,
        public array $stores,
        public string $tenantSlug,
        public bool $dryRun,
        public string $reportDir,
        public int $chunk = 500,
    ) {
        if ($stores === []) {
            throw new InvalidArgumentException('At least one legacy store id is required.');
        }
        if (preg_match('/^[a-z0-9][a-z0-9-]{1,79}$/', $tenantSlug) !== 1) {
            throw new InvalidArgumentException("Invalid tenant slug [{$tenantSlug}].");
        }
    }
}
