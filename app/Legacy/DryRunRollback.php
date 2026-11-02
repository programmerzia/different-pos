<?php

declare(strict_types=1);

namespace App\Legacy;

use RuntimeException;

/** Thrown at the end of a --dry-run import so Transaction::run() rolls everything back. */
final class DryRunRollback extends RuntimeException
{
    public function __construct(public readonly ImportResult $result)
    {
        parent::__construct('Legacy import dry run: rolled back.');
    }
}
