<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Backup\BackupArchive;
use App\Backup\BackupRunner;
use RuntimeException;

/** Never dumps anything: returns a fixed archive or throws, and counts calls. */
final class FakeBackupRunner implements BackupRunner
{
    public int $runs = 0;

    public int $cleanups = 0;

    public function __construct(public ?string $failWith = null, public string $disk = 'local', public string $path = 'POS/2026-11-03-02-00-00.zip', public int $size = 14_200_000) {}

    public function run(): BackupArchive
    {
        $this->runs++;
        if ($this->failWith !== null) {
            throw new RuntimeException($this->failWith);
        }

        return new BackupArchive($this->disk, $this->path, $this->size);
    }

    public function cleanup(): void
    {
        $this->cleanups++;
    }
}
