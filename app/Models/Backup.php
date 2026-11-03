<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One backup run of the installation (database dump + tenant files, one encrypted archive).
 * Deliberately NOT tenant-scoped: the archive holds every tenant, so the history is shared and read-only
 * for tenant Owners (backup.view); only backup.run may start one or download an archive.
 *
 * @property int $id
 * @property string $status
 * @property string $trigger
 * @property string|null $disk
 * @property string|null $path
 * @property int|null $size_bytes
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property string|null $error
 * @property int|null $triggered_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Backup extends Model
{
    public const string STATUS_QUEUED = 'queued';

    public const string STATUS_RUNNING = 'running';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_FAILED = 'failed';

    public const string TRIGGER_MANUAL = 'manual';

    public const string TRIGGER_SCHEDULED = 'scheduled';

    protected $fillable = ['status', 'trigger', 'disk', 'path', 'size_bytes', 'started_at', 'finished_at', 'error', 'triggered_by'];

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    /** Only archives on a local disk are served through the app; cloud archives stay in the bucket. */
    public function isDownloadable(): bool
    {
        return $this->status === self::STATUS_COMPLETED && $this->path !== null && $this->disk !== null
            && config('filesystems.disks.'.$this->disk.'.driver') === 'local';
    }

    /** @return BelongsTo<User, $this> */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
