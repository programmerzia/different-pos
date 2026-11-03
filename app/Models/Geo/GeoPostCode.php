<?php

declare(strict_types=1);

namespace App\Models\Geo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A post office with its 4-digit post code, under a district and (when it could be matched) an upazila.
 *
 * @property int $id
 * @property int|null $upazila_id
 * @property int $district_id
 * @property string $post_office_en
 * @property string|null $post_office_bn
 * @property string $post_code
 */
final class GeoPostCode extends Model
{
    protected $table = 'geo_post_codes';

    protected $guarded = [];

    /** @return BelongsTo<GeoDistrict, $this> */
    public function district(): BelongsTo
    {
        return $this->belongsTo(GeoDistrict::class, 'district_id');
    }

    /** @return BelongsTo<GeoUpazila, $this> */
    public function upazila(): BelongsTo
    {
        return $this->belongsTo(GeoUpazila::class, 'upazila_id');
    }
}
