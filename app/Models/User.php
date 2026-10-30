<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\Tenancy\CrossTenantWrite;
use App\Models\Concerns\HasUuid;
use App\Support\Tenancy\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Users are NOT globally tenant-scoped: authentication loads them before any tenant context exists.
 * Tenant UI must query through scopeInCurrentTenant(); writes are guarded against other tenants.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $tenant_id
 * @property string $name
 * @property string|null $username
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $legacy_password_md5
 * @property bool $must_change_password
 * @property string|null $pin_hash
 * @property int $pin_failed_attempts
 * @property Carbon|null $pin_locked_until
 * @property bool $is_platform_admin
 * @property bool $is_active
 * @property array<string, mixed>|null $preferences
 * @property Carbon|null $last_login_at
 * @property int|null $legacy_id
 * @property Carbon|null $deleted_at
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'username', 'email', 'phone', 'password', 'preferences'])]
#[Hidden(['password', 'legacy_password_md5', 'pin_hash', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
final class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuid, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        $guard = static function (User $user): void {
            $context = app(TenantContext::class);
            if ($context->isBypassed() || ! $context->hasTenant()) {
                return; // auth, provisioning and console paths
            }
            $current = $context->tenantId();
            $original = $user->exists ? $user->getOriginal('tenant_id') : $user->tenant_id;
            if ($user->tenant_id !== $current || $original !== $current) {
                throw CrossTenantWrite::for(self::class, 'tenant_id', $current, $user->tenant_id);
            }
        };

        self::saving($guard);
        self::deleting($guard);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeInCurrentTenant(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('tenant_id'), app(TenantContext::class)->tenantId());
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsToMany<Branch, $this> */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withPivot('is_default');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'pin_failed_attempts' => 'integer',
            'pin_locked_until' => 'datetime',
            'is_platform_admin' => 'boolean',
            'is_active' => 'boolean',
            'preferences' => 'array',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
