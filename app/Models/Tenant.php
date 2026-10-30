<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Services\Crypto\PinSealer;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One business (a customer of the SaaS). Not tenant-scoped itself. 05_Schema §1.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string|null $legal_name
 * @property string|null $vat_bin
 * @property string|null $vat_registered_address
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string $currency
 * @property string $timezone
 * @property string $locale
 * @property string $status
 * @property array<string, mixed> $settings
 * @property array<string, mixed>|null $branding
 * @property string $pin_box_public_key
 * @property string $pin_box_secret_key
 * @property int $sync_counter
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUuid;

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_SUSPENDED = 'suspended';

    /** Defaults merged under the stored settings (05_Schema §1). */
    public const array DEFAULT_SETTINGS = [
        'tax_inclusive' => true,
        'backdate_days' => 3,
        'customer_pin_policy' => 'always', // always|credit_only|off (open question 7)
        'offline_credit_cap' => 0,         // paisa
        'offline_login_days' => 7,
        'lapsed_days' => 90,
        'card_terms' => '',
    ];

    protected $fillable = [
        'name', 'slug', 'legal_name', 'vat_bin', 'vat_registered_address', 'phone', 'email', 'address',
        'currency', 'timezone', 'locale', 'settings', 'branding',
    ];

    /** The sealed-box secret never leaves the server (CLAUDE.md: customer PIN rules). */
    protected $hidden = ['pin_box_secret_key', 'sync_counter'];

    protected static function booted(): void
    {
        self::creating(static function (Tenant $tenant): void {
            $tenant->settings = array_replace(self::DEFAULT_SETTINGS, $tenant->getAttribute('settings') ?? []);
            $tenant->status ??= self::STATUS_ACTIVE;

            if (empty($tenant->pin_box_public_key)) {
                $keys = PinSealer::generateKeypair();
                $tenant->pin_box_public_key = $keys['public'];
                $tenant->pin_box_secret_key = $keys['secret'];
            }
        });
    }

    /** A tenant setting with its default applied. */
    public function setting(string $key): mixed
    {
        return $this->settings[$key] ?? self::DEFAULT_SETTINGS[$key] ?? null;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** @return HasMany<Branch, $this> */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'branding' => 'array',
            'pin_box_secret_key' => 'encrypted',
            'sync_counter' => 'integer',
        ];
    }
}
