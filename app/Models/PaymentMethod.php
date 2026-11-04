<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethodType;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\Syncable;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A way a customer pays (cash, card, wallet, store credit).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property PaymentMethodType $type
 * @property int|null $account_id
 * @property bool $requires_reference
 * @property int $sort_order
 * @property bool $is_active
 * @property int|null $sync_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class PaymentMethod extends Model
{
    use BelongsToTenant, Syncable;

    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'type', 'account_id', 'requires_reference', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
            'requires_reference' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Methods a vendor can be paid with: active, and not customer-side store credit.
     *
     * @param  Builder<PaymentMethod>  $query
     * @return Builder<PaymentMethod>
     */
    public function scopeForVendorPayments(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNotIn('type', PaymentMethodType::storeCreditValues());
    }
}
