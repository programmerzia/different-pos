<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\Gender;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuid;
use App\Models\Concerns\Syncable;
use App\Support\Money\Money;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A customer; the PIN hash never leaves the server.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int|null $customer_group_id
 * @property int|null $customer_no
 * @property string $name
 * @property string|null $name_bn
 * @property string|null $shop_name
 * @property string|null $phone
 * @property string|null $alt_phone
 * @property string|null $email
 * @property Gender|null $gender
 * @property Carbon|null $dob
 * @property Carbon|null $anniversary_date
 * @property string|null $vat_bin
 * @property int|null $geo_division_id
 * @property int|null $geo_district_id
 * @property int|null $geo_upazila_id
 * @property int|null $geo_union_id
 * @property string|null $area
 * @property string|null $post_code
 * @property string|null $address_line
 * @property bool $location_verified
 * @property string|null $pin_hash
 * @property Carbon|null $pin_set_at
 * @property int $pin_failed_attempts
 * @property Carbon|null $pin_locked_until
 * @property Carbon|null $member_since
 * @property Money|null $credit_limit
 * @property Money $balance
 * @property Carbon|null $last_purchase_at
 * @property string|null $notes
 * @property bool $is_walk_in
 * @property bool $is_active
 * @property string|null $origin_client_uuid
 * @property int|null $merged_into_id
 * @property int|null $sync_version
 * @property int|null $legacy_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
final class Customer extends Model
{
    use BelongsToTenant, HasUuid, SoftDeletes, Syncable;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_group_id', 'customer_no', 'name', 'name_bn', 'shop_name', 'phone', 'alt_phone', 'email', 'gender',
        'dob', 'anniversary_date', 'vat_bin', 'geo_division_id', 'geo_district_id', 'geo_upazila_id', 'geo_union_id',
        'area', 'post_code', 'address_line', 'location_verified', 'member_since', 'credit_limit', 'balance',
        'last_purchase_at', 'notes', 'is_walk_in', 'is_active', 'origin_client_uuid', 'merged_into_id', 'legacy_id',
        'created_by', 'updated_by',
    ];

    protected $hidden = ['pin_hash'];

    /** @return BelongsTo<CustomerGroup, $this> */
    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'merged_into_id');
    }

    /** @return HasMany<MembershipCard, $this> */
    public function membershipCards(): HasMany
    {
        return $this->hasMany(MembershipCard::class, 'customer_id');
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'dob' => 'date',
            'anniversary_date' => 'date',
            'location_verified' => 'boolean',
            'pin_set_at' => 'datetime',
            'pin_locked_until' => 'datetime',
            'member_since' => 'date',
            'credit_limit' => MoneyCast::class,
            'balance' => MoneyCast::class,
            'last_purchase_at' => 'datetime',
            'is_walk_in' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
