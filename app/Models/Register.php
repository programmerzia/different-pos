<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\RegisterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A till of a branch; holds the device sale-number high-water mark.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property string $name
 * @property string $code
 * @property int $last_number
 * @property int $number_year
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Register extends Model
{
    use BelongsToBranch, BelongsToTenant;

    /** @use HasFactory<RegisterFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'last_number', 'number_year', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
