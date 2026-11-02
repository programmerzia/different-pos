<?php

declare(strict_types=1);

namespace App\Support\Pos;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

/**
 * A manager authorises an override at the online register by typing their login and password (or
 * staff PIN). That yields a short-lived encrypted token bound to the tenant, the manager and the
 * cashier who asked; the sale request hands it back and CompleteSale gets the manager as approver.
 */
final readonly class ManagerApproval
{
    public const int TTL_SECONDS = 120;

    public function __construct(private TenantContext $context) {}

    /** The tenant user behind $login whose password or staff PIN is $secret, else null. */
    public function verify(string $login, string $secret): ?User
    {
        $login = trim($login);
        if ($login === '' || $secret === '') {
            return null;
        }

        $user = User::query()->inCurrentTenant()
            ->where(fn ($q) => $q->where('email', $login)->orWhere('username', $login)->orWhere('phone', $login))
            ->first();
        if (! $user instanceof User || ! $user->is_active) {
            return null;
        }

        $ok = Hash::check($secret, $user->password)
            || (is_string($user->pin_hash) && $user->pin_hash !== '' && Hash::check($secret, $user->pin_hash));

        return $ok ? $user : null;
    }

    public function issue(User $manager, User $cashier): string
    {
        return Crypt::encryptString((string) json_encode([
            't' => $this->context->tenantId(),
            'm' => $manager->id,
            'c' => $cashier->id,
            'x' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
        ]));
    }

    /** The approving manager for $cashier, or null when the token is missing, foreign or expired. */
    public function resolve(?string $token, User $cashier): ?User
    {
        if ($token === null || $token === '') {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($payload)
            || ($payload['t'] ?? null) !== $this->context->tenantId()
            || ($payload['c'] ?? null) !== $cashier->id
            || ! is_int($payload['x'] ?? null)
            || $payload['x'] < now()->getTimestamp()
            || ! is_int($payload['m'] ?? null)) {
            return null;
        }

        $manager = User::query()->inCurrentTenant()->whereKey($payload['m'])->first();

        return $manager instanceof User && $manager->is_active ? $manager : null;
    }
}
