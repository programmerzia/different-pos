<?php

declare(strict_types=1);

namespace App\Support\Backup;

use App\Models\Backup;

/** Backup rows and the retention/schedule summary for the Backup & Disaster Recovery page. */
final class BackupPresenter
{
    /** @return array{id: int, filename: string|null, status: string, trigger: string, disk: string|null, size_bytes: int|null, started_at: string|null, finished_at: string|null, created_at: string|null, duration_seconds: int|null, error: string|null, triggered_by: string|null, download_url: string|null} */
    public function row(Backup $b, bool $canDownload): array
    {
        $duration = $b->started_at !== null && $b->finished_at !== null ? max(0, (int) $b->started_at->diffInSeconds($b->finished_at)) : null;

        return [
            'id' => $b->id,
            'filename' => $b->path !== null ? basename($b->path) : null,
            'status' => $b->status,
            'trigger' => $b->trigger,
            'disk' => $b->disk,
            'size_bytes' => $b->size_bytes,
            'started_at' => $b->started_at?->toIso8601String(),
            'finished_at' => $b->finished_at?->toIso8601String(),
            'created_at' => $b->created_at?->toIso8601String(),
            'duration_seconds' => $duration,
            'error' => $b->error,
            'triggered_by' => $b->triggeredBy?->name,
            'download_url' => $canDownload && $b->isDownloadable() ? route('settings.backups.download', $b) : null,
        ];
    }

    /** @return array{daily_at: string, timezone: string, retention_days: int, disks: list<string>, encrypted: bool, includes_files: bool} */
    public function policy(): array
    {
        /** @var list<string> $disks */
        $disks = array_values((array) config('backup.backup.destination.disks', []));

        return [
            'daily_at' => (string) config('backup.schedule.daily_at', '02:00'),
            'timezone' => (string) config('backup.schedule.timezone', 'Asia/Dhaka'),
            'retention_days' => (int) config('backup.schedule.retention_days', 30),
            'disks' => $disks,
            'encrypted' => filled(config('backup.backup.password')),
            'includes_files' => (array) config('backup.backup.source.files.include', []) !== [],
        ];
    }
}
