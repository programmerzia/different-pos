<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Sync\SyncVersionRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A model the POS pulls (04_TRD §3.9.2). Every create, update, soft delete and restore gets a new
 * sync_version from tenants.sync_counter. Versions are assigned by SyncVersionRecorder at the END
 * of the transaction (ADR 0001 D6), never inside the event, so the counter lock is taken last.
 *
 * Requirements: columns tenant_id + sync_version, and BelongsToTenant. Writes must not use
 * saveQuietly()/withoutEvents(), or the POS never sees the change.
 *
 * @property int|null $sync_version
 *
 * @mixin Model
 */
trait Syncable
{
    public static function bootSyncable(): void
    {
        $record = static function (Model $model): void {
            app(SyncVersionRecorder::class)->record($model);
        };

        static::saved($record);
        static::deleted($record);
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored($record);
        }
    }
}
