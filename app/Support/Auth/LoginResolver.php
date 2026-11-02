<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Phone\BdPhone;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turns what the user typed on the login form into a user (or nothing).
 * An email is globally unique; a username or phone is only unique inside a business, so those
 * need the business code (the tenant slug).
 */
final class LoginResolver
{
    public function resolve(string $login, ?string $businessCode): ?User
    {
        $login = trim($login);
        if ($login === '') {
            return null;
        }

        if (str_contains($login, '@')) {
            return User::query()->where('email', mb_strtolower($login))->first();
        }

        $code = trim((string) $businessCode);
        if ($code === '') {
            return null;
        }

        $tenant = Tenant::query()->whereRaw('LOWER(slug) = ?', [mb_strtolower($code)])->first();
        if (! $tenant instanceof Tenant) {
            return null;
        }

        $phone = BdPhone::toE164($login);

        return User::query()
            ->where('tenant_id', $tenant->id)
            ->where(function (Builder $query) use ($login, $phone): void {
                $query->where('username', $login);
                if ($phone !== null) {
                    $query->orWhere('phone', $phone);
                }
            })
            ->first();
    }
}
