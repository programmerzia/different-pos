<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\RefundMode;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use App\Models\Concerns\LogsTenantActivity;
use App\Support\Money\Money;
use Database\Factories\SaleReturnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Goods returned against a sale.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $branch_id
 * @property string $number
 * @property int $sale_id
 * @property int $customer_id
 * @property Carbon $returned_at
 * @property Money $subtotal
 * @property Money $tax_total
 * @property Money $total
 * @property RefundMode $refund_mode
 * @property int|null $exchange_sale_id
 * @property Money $refunded
 * @property Money $credited
 * @property string $reason
 * @property string|null $note
 * @property int|null $legacy_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class SaleReturn extends Model
{
    use BelongsToBranch, BelongsToTenant, HasUuid, LogsTenantActivity;

    /** @use HasFactory<SaleReturnFactory> */
    use HasFactory;

    protected $fillable = [
        'number', 'sale_id', 'customer_id', 'returned_at', 'subtotal', 'tax_total', 'total', 'refund_mode',
        'exchange_sale_id', 'refunded', 'credited', 'reason', 'note', 'legacy_id', 'created_by',
    ];

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** @return BelongsTo<Sale, $this> */
    public function exchangeSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'exchange_sale_id');
    }

    /** @return HasMany<SaleReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class, 'sale_return_id');
    }

    protected function casts(): array
    {
        return [
            'returned_at' => 'datetime',
            'subtotal' => MoneyCast::class,
            'tax_total' => MoneyCast::class,
            'total' => MoneyCast::class,
            'refund_mode' => RefundMode::class,
            'refunded' => MoneyCast::class,
            'credited' => MoneyCast::class,
        ];
    }
}
