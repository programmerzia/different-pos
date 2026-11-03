<?php

declare(strict_types=1);

namespace App\Models\Geo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The "City / Upazila / Thana" level: an upazila, a metro thana (with its city corporation) or a city
 * corporation. Global reference data.
 *
 * @property int $id
 * @property int $district_id
 * @property string $code
 * @property string $type upazila | metro_thana | city_corporation
 * @property string $name_en
 * @property string $name_bn
 * @property string|null $city_name_en
 * @property string|null $aliases
 */
final class GeoUpazila extends Model
{
    public const string TYPE_UPAZILA = 'upazila';

    public const string TYPE_METRO_THANA = 'metro_thana';

    public const string TYPE_CITY_CORPORATION = 'city_corporation';

    protected $table = 'geo_upazilas';

    protected $guarded = [];

    /** @return BelongsTo<GeoDistrict, $this> */
    public function district(): BelongsTo
    {
        return $this->belongsTo(GeoDistrict::class, 'district_id');
    }

    /** @return HasMany<GeoUnion, $this> */
    public function unions(): HasMany
    {
        return $this->hasMany(GeoUnion::class, 'upazila_id');
    }

    /** @return HasMany<GeoPostCode, $this> */
    public function postCodes(): HasMany
    {
        return $this->hasMany(GeoPostCode::class, 'upazila_id');
    }
}
