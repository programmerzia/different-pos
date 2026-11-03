<?php

declare(strict_types=1);

namespace App\Actions\Backup;

use App\Exceptions\Domain\InvalidAdminData;
use App\Jobs\RunBackupJob;
use App\Models\Backup;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;

/** Records a queued backup run and dispatches the job that performs it. One run at a time. */
final class RequestBackup
{
    public function handle(?User $actor, string $trigger = Backup::TRIGGER_MANUAL): Backup
    {
        $backup = Transaction::run(function () use ($actor, $trigger): Backup {
            if (Backup::query()->whereIn('status', [Backup::STATUS_QUEUED, Backup::STATUS_RUNNING])->lockForUpdate()->exists()) {
                throw InvalidAdminData::invalid('A backup is already running. Wait for it to finish.');
            }

            $backup = Backup::query()->create([
                'status' => Backup::STATUS_QUEUED,
                'trigger' => $trigger,
                'triggered_by' => $actor?->id,
            ]);
            Audit::log('backup.requested', $backup, ['trigger' => $trigger], $actor);

            return $backup;
        });

        RunBackupJob::dispatch($backup->id);

        return $backup;
    }
}
