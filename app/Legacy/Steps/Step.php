<?php

declare(strict_types=1);

namespace App\Legacy\Steps;

use App\Legacy\ImportContext;

/** One mapping step of 05_Schema §12. Must be idempotent: rows already in legacy_id_maps are skipped. */
interface Step
{
    public function name(): string;

    public function run(ImportContext $context): void;
}
