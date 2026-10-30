<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Opaque public id: a UUIDv7 in the `uuid` column (the primary key stays a BIGINT).
 * URLs bind by uuid, so internal ids are never exposed.
 *
 * @property string $uuid
 *
 * @mixin Model
 */
trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(static function (Model $model): void {
            if (empty($model->getAttribute('uuid'))) {
                $model->setAttribute('uuid', (string) Str::uuid7());
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
