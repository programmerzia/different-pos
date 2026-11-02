<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Sync\SyncVersionRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A model the POS pulls. Every create, update, soft delete and restore gets a new sync_version
 * from tenants.sync_counter, assigned by SyncVersionRecorder at the end of the transaction so the
 * counter lock is taken last.
 *
 * Needs tenant_id + sync_version columns and BelongsToTenant. Don't write with
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
            static::registerModelEvent('restored', $record);
        }
    }
}
