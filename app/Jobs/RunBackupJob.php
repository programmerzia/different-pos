<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Backup\RunBackup;
use App\Models\Backup;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Platform-level (no tenant context): the archive holds every tenant. */
final class RunBackupJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public readonly int $backupId) {}

    public function uniqueId(): string
    {
        return (string) $this->backupId;
    }

    public function handle(RunBackup $run): void
    {
        $backup = Backup::query()->find($this->backupId);
        if ($backup instanceof Backup) {
            $run->handle($backup);
        }
    }
}
