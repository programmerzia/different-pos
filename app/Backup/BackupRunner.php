<?php

declare(strict_types=1);

namespace App\Backup;

/**
 * Produces one backup archive. The real implementation drives spatie/laravel-backup;
 * tests bind a fake so no database is ever dumped.
 */
interface BackupRunner
{
    /** @throws \RuntimeException when the backup fails */
    public function run(): BackupArchive;

    /** Deletes archives past the retention policy (config/backup.php `cleanup`). */
    public function cleanup(): void;
}
