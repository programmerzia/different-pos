<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Audit\Audit;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Audit row. Every entry, whether from a model event or Audit::log(), passes Audit::prepare() here,
 * which stamps tenant_id and scrubs secrets. Unscoped on purpose: readers filter on tenant_id, and
 * platform events have none.
 */
final class Activity extends SpatieActivity
{
    protected static function booted(): void
    {
        self::creating(Audit::prepare(...));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['attribute_changes' => 'collection'];
    }
}
