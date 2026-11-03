<?php

declare(strict_types=1);

namespace App\Support\Pos;

use App\Models\Customer;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Proof that a member typed their PIN at the register (02_AppFlow row 25): an encrypted token bound
 * to the tenant, the customer, the cashier and the cart (client_uuid), valid for ten minutes. The sale
 * request hands it back and CompleteSale records member_auth = pin_online. It carries no PIN data.
 */
final readonly class MemberToken
{
    public const int TTL_SECONDS = 600;

    public function __construct(private TenantContext $context) {}

    public function issue(Customer $customer, User $cashier, string $clientUuid): string
    {
        return Crypt::encryptString((string) json_encode([
            't' => $this->context->tenantId(),
            'k' => $customer->id,
            'c' => $cashier->id,
            'u' => $clientUuid,
            'x' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
        ]));
    }

    /** True when $token proves this customer verified for this cashier's cart and has not expired. */
    public function verifies(?string $token, User $cashier, int $customerId, ?string $clientUuid): bool
    {
        if ($token === null || $token === '' || $clientUuid === null) {
            return false;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return false;
        }

        return is_array($payload)
            && ($payload['t'] ?? null) === $this->context->tenantId()
            && ($payload['k'] ?? null) === $customerId
            && ($payload['c'] ?? null) === $cashier->id
            && ($payload['u'] ?? null) === $clientUuid
            && is_int($payload['x'] ?? null)
            && $payload['x'] >= now()->getTimestamp();
    }
}
