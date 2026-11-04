<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CourierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A delivery courier. The tenant's default one prints on the delivery chalan. Sales keep the courier
 * as plain text (sales.courier_name), so nothing references this table by id.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $phone
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Courier extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<CourierFactory> */
    use HasFactory;

    protected $fillable = ['name', 'phone', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }
}
