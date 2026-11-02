<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\Syncable;
use Database\Factories\TaxRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A VAT rate, in basis points.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property int $rate_bp
 * @property bool $is_default
 * @property bool $is_active
 * @property int|null $sync_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class TaxRate extends Model
{
    use BelongsToTenant, Syncable;

    /** @use HasFactory<TaxRateFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'rate_bp', 'is_default', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
