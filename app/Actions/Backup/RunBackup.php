<?php

declare(strict_types=1);

namespace App\Actions\Backup;

use App\Backup\BackupRunner;
use App\Models\Backup;
use App\Support\Database\Transaction;
use Throwable;

/** Performs a queued backup run and records the outcome on its row (never throws: the row carries the error). */
final readonly class RunBackup
{
    public function __construct(private BackupRunner $runner) {}

    public function handle(Backup $backup): Backup
    {
        $backup = Transaction::run(function () use ($backup): Backup {
            $backup = Backup::query()->lockForUpdate()->findOrFail($backup->id);
            if ($backup->status !== Backup::STATUS_QUEUED) {
                return $backup;
            }
            $backup->forceFill(['status' => Backup::STATUS_RUNNING, 'started_at' => now()])->save();

            return $backup;
        });

        if ($backup->status !== Backup::STATUS_RUNNING) {
            return $backup;
        }

        try {
            $archive = $this->runner->run();
            $this->runner->cleanup();
            $attributes = [
                'status' => Backup::STATUS_COMPLETED,
                'disk' => $archive->disk,
                'path' => $archive->path,
                'size_bytes' => $archive->sizeBytes,
                'finished_at' => now(),
            ];
        } catch (Throwable $e) {
            $attributes = ['status' => Backup::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => now()];
        }

        return Transaction::run(function () use ($backup, $attributes): Backup {
            $backup->forceFill($attributes)->save();

            return $backup;
        });
    }
}
