<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use App\Models\Concerns\Syncable;
use Database\Factories\ProductFamilyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A style that groups variant products; not sellable itself.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property string|null $name_bn
 * @property int|null $category_id
 * @property int|null $brand_id
 * @property int $unit_id
 * @property int|null $tax_rate_id
 * @property int|null $option1_id
 * @property int|null $option2_id
 * @property string|null $image_path
 * @property string|null $description
 * @property bool $is_active
 * @property int|null $sync_version
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
final class ProductFamily extends Model
{
    use BelongsToTenant, HasUuid, SoftDeletes, Syncable;

    /** @use HasFactory<ProductFamilyFactory> */
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'name_bn', 'category_id', 'brand_id', 'unit_id', 'tax_rate_id', 'option1_id', 'option2_id',
        'image_path', 'description', 'is_active', 'created_by', 'updated_by',
    ];

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /** @return BelongsTo<TaxRate, $this> */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }

    /** @return BelongsTo<VariantOption, $this> */
    public function option1(): BelongsTo
    {
        return $this->belongsTo(VariantOption::class, 'option1_id');
    }

    /** @return BelongsTo<VariantOption, $this> */
    public function option2(): BelongsTo
    {
        return $this->belongsTo(VariantOption::class, 'option2_id');
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'family_id');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
