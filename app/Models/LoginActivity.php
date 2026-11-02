<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One sign-in attempt (success, failed, throttled). Written before a tenant context exists, so it
 * is deliberately not tenant-scoped; nothing in the UI lists it across tenants.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $user_id
 * @property string $login
 * @property string $event
 * @property string $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
final class LoginActivity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'user_id', 'login', 'event', 'ip_address', 'user_agent'];
}
