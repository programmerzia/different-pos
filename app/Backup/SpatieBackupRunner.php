<?php

declare(strict_types=1);

namespace App\Backup;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;

/** Runs `backup:run` (DB dump + storage/app/public, encrypted when BACKUP_ARCHIVE_PASSWORD is set) and reports the newest archive. */
final class SpatieBackupRunner implements BackupRunner
{
    public function run(): BackupArchive
    {
        $disks = $this->disks();
        $primary = $disks[0];
        $before = $this->newest($primary)?->path();

        $exit = Artisan::call('backup:run', ['--disable-notifications' => true, '--no-interaction' => true]);
        $output = trim(Artisan::output());
        $newest = $this->newest($primary);

        if ($exit !== 0 || ! $newest instanceof Backup || $newest->path() === $before) {
            throw new RuntimeException($this->failureMessage($output));
        }

        return new BackupArchive($primary, $newest->path(), (int) $newest->sizeInBytes());
    }

    public function cleanup(): void
    {
        Artisan::call('backup:clean', ['--disable-notifications' => true, '--no-interaction' => true]);
    }

    /** @return non-empty-list<string> */
    private function disks(): array
    {
        /** @var list<string> $disks */
        $disks = array_values((array) config('backup.backup.destination.disks', []));

        return $disks === [] ? ['local'] : $disks;
    }

    private function newest(string $disk): ?Backup
    {
        return BackupDestination::create($disk, (string) config('backup.backup.name'))->fresh()->newestBackup();
    }

    private function failureMessage(string $output): string
    {
        $lines = array_values(array_filter(array_map(trim(...), explode("\n", $output)), static fn (string $l): bool => $l !== ''));
        $error = array_values(array_filter($lines, static fn (string $l): bool => stripos($l, 'fail') !== false || stripos($l, 'error') !== false || stripos($l, 'exception') !== false));

        return mb_substr(($error === [] ? end($lines) : $error[0]) ?: 'Backup failed.', 0, 1000);
    }
}
