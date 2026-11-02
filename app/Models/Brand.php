<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\Syncable;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A product brand.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property bool $is_active
 * @property int|null $sync_version
 * @property int|null $legacy_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
final class Brand extends Model
{
    use BelongsToTenant, SoftDeletes, Syncable;

    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'is_active', 'legacy_id', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
