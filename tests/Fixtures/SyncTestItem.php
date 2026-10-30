<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 */
final class SyncTestItem extends Model
{
    use BelongsToTenant, SoftDeletes, Syncable;

    protected $fillable = ['name'];
}
