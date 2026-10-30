<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 */
final class BranchTestItem extends Model
{
    use BelongsToBranch, BelongsToTenant;

    protected $fillable = ['name'];
}
