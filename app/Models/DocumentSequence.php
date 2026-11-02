<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DocumentSequenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The next number of a document type, optionally per branch.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $branch_id
 * @property string $type
 * @property string $prefix
 * @property int $next_number
 * @property int $pad
 * @property bool $reset_yearly
 * @property int|null $current_year
 */
final class DocumentSequence extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DocumentSequenceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'branch_id', 'type', 'prefix', 'next_number', 'pad', 'reset_yearly', 'current_year',
    ];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    protected function casts(): array
    {
        return [
            'reset_yearly' => 'boolean',
        ];
    }
}
