<?php

declare(strict_types=1);

namespace App\Models\Geo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A district (zila). Global reference data.
 *
 * @property int $id
 * @property int $division_id
 * @property string $code
 * @property string $name_en
 * @property string $name_bn
 * @property string|null $aliases
 */
final class GeoDistrict extends Model
{
    protected $table = 'geo_districts';

    protected $guarded = [];

    /** @return BelongsTo<GeoDivision, $this> */
    public function division(): BelongsTo
    {
        return $this->belongsTo(GeoDivision::class, 'division_id');
    }

    /** @return HasMany<GeoUpazila, $this> */
    public function upazilas(): HasMany
    {
        return $this->hasMany(GeoUpazila::class, 'district_id');
    }
}
