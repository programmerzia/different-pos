<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\Syncable;
use Database\Factories\VariantOptionValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A value of a variant option, such as M or Black.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $variant_option_id
 * @property string $value
 * @property string|null $value_bn
 * @property string $code
 * @property int $sort_order
 * @property int|null $sync_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class VariantOptionValue extends Model
{
    use BelongsToTenant, Syncable;

    /** @use HasFactory<VariantOptionValueFactory> */
    use HasFactory;

    protected $fillable = [
        'variant_option_id', 'value', 'value_bn', 'code', 'sort_order',
    ];

    /** @return BelongsTo<VariantOption, $this> */
    public function variantOption(): BelongsTo
    {
        return $this->belongsTo(VariantOption::class, 'variant_option_id');
    }

    protected function casts(): array
    {
        return [
        ];
    }
}
