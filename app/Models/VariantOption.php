<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\Syncable;
use Database\Factories\VariantOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A variant axis such as Size or Colour.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $name_bn
 * @property int $sort_order
 * @property int|null $sync_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class VariantOption extends Model
{
    use BelongsToTenant, Syncable;

    /** @use HasFactory<VariantOptionFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'name_bn', 'sort_order',
    ];

    /** @return HasMany<VariantOptionValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(VariantOptionValue::class, 'variant_option_id');
    }

    protected function casts(): array
    {
        return [
        ];
    }
}
