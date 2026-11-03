<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Audit\Audit;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Audit trail for create/update/delete: fillable attributes only, only what changed, nothing when
 * nothing changed. Secrets are excluded here and scrubbed again by Audit::prepare(). tenant_id is
 * stamped on the row by that same hook (App\Models\Activity).
 */
trait LogsTenantActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(Audit::FORBIDDEN)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName(strtolower(class_basename($this)));
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        Audit::separateChanges($activity);
    }
}
