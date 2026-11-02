<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CustomerGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A customer pricing group.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property int $discount_bp
 * @property bool $is_active
 * @property int|null $legacy_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
final class CustomerGroup extends Model
{
    use BelongsToTenant, SoftDeletes;

    /** @use HasFactory<CustomerGroupFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'discount_bp', 'is_active', 'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
