<?php

declare(strict_types=1);

namespace App\Backup;

/** The archive a backup run produced on its primary destination. */
final readonly class BackupArchive
{
    public function __construct(
        public string $disk,
        public string $path,
        public int $sizeBytes,
    ) {}
}
