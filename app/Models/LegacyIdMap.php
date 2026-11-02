<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LegacyIdMapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Maps a legacy row to its new id during the import.
 *
 * @property int $id
 * @property string $entity
 * @property int $legacy_store_id
 * @property int $legacy_id
 * @property int $new_id
 * @property Carbon|null $created_at
 */
final class LegacyIdMap extends Model
{
    /** @use HasFactory<LegacyIdMapFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'entity', 'legacy_store_id', 'legacy_id', 'new_id',
    ];

    protected function casts(): array
    {
        return [
        ];
    }
}
