<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Audit\Audit;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Audit trail for create/update/delete: fillable attributes only, only what changed, nothing when
 * nothing changed. Secrets are excluded here and scrubbed again by Audit::prepare(). tenant_id is
 * stamped on the row by that same hook (registered in AppServiceProvider).
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
            ->dontLogEmptyChanges()
            ->useLogName(strtolower(class_basename($this)));
    }
}
