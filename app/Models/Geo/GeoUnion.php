<?php

declare(strict_types=1);

namespace App\Models\Geo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The "Union / Ward / Pourashava" level (optional on forms). Global reference data.
 *
 * @property int $id
 * @property int $upazila_id
 * @property string $code
 * @property string $type union | ward | pourashava
 * @property string $name_en
 * @property string $name_bn
 */
final class GeoUnion extends Model
{
    protected $table = 'geo_unions';

    protected $guarded = [];

    /** @return BelongsTo<GeoUpazila, $this> */
    public function upazila(): BelongsTo
    {
        return $this->belongsTo(GeoUpazila::class, 'upazila_id');
    }
}
