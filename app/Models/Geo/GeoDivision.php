<?php

declare(strict_types=1);

namespace App\Models\Geo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A division of Bangladesh (global reference data, read-only, no tenant).
 *
 * @property int $id
 * @property string $code
 * @property string $name_en
 * @property string $name_bn
 * @property string|null $aliases
 */
final class GeoDivision extends Model
{
    protected $table = 'geo_divisions';

    protected $guarded = [];

    /** @return HasMany<GeoDistrict, $this> */
    public function districts(): HasMany
    {
        return $this->hasMany(GeoDistrict::class, 'division_id');
    }
}
